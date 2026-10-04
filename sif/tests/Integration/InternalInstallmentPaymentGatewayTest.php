<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Repository\PaymentActionEventRepository;
use Prisma\Sif\Repository\SifAuditEventRepository;
use Prisma\Sif\Service\InstallmentPaymentAuditTrail;
use Prisma\Sif\Service\InternalInstallmentPaymentGateway;
use Prisma\Sif\Service\ManualInstallmentPaymentPayloadBuilder;
use Prisma\Sif\Service\ManualInstallmentPaymentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class InternalInstallmentPaymentGatewayTest
{
    public function testRegistersWithAuthenticatedActorAsAuthoritativeUser(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );

        $gateway = $this->gateway($db, ['FACTURACIO']);
        $result = $gateway->register(
            $db,
            $this->actor(
                'gestio-real',
                ['FACTURACIO'],
                '11111111-1111-4111-8111-111111111111'
            ),
            [
                'uuid_factura' => $invoice['uuid_factura'],
                'input' => [
                    'amount' => '40.00',
                    'movement_date' => '2026-06-12',
                    'id_insc' => 10,
                    'user' => 'browser-fals',
                    'operation_id' => 'MANUAL-EVENT-001',
                    'reference' => 'TRF-AUDIT-001',
                ],
            ]
        );

        Assert::same(true, $result['ok']);
        Assert::same(false, $result['idempotency_reused']);

        $providerRef = (string) $db->query(
            'SELECT PROVIDER_REF FROM payment_transaction LIMIT 1'
        )->fetchColumn();
        Assert::same(
            'FRACCIO|ID_INSC:10|EVENT:MANUAL-EVENT-001',
            $providerRef
        );

        $retry = $gateway->register(
            $db,
            $this->actor(
                'gestio-segon',
                ['FACTURACIO'],
                '22222222-2222-4222-8222-222222222222'
            ),
            [
                'uuid_factura' => $invoice['uuid_factura'],
                'input' => [
                    'amount' => '40.00',
                    'movement_date' => '2026-06-12',
                    'id_insc' => 10,
                    'operation_id' => 'MANUAL-EVENT-001',
                    'reference' => 'TRF-AUDIT-001',
                ],
            ]
        );

        Assert::same(true, $retry['idempotency_reused']);
        Assert::same($result['uuid_payment'], $retry['uuid_payment']);
        Assert::same(
            1,
            (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn()
        );

        Assert::same(
            4,
            (int) $db->query('SELECT COUNT(*) FROM payment_action_event')->fetchColumn()
        );
        Assert::same(
            'REQUESTED,SUCCEEDED,REQUESTED,REUSED',
            (string) $db->query(
                "SELECT GROUP_CONCAT(RESULT ORDER BY ID SEPARATOR ',')
                 FROM payment_action_event"
            )->fetchColumn()
        );
        Assert::same(
            2,
            (int) $db->query(
                'SELECT COUNT(DISTINCT REQUEST_ID) FROM payment_action_event'
            )->fetchColumn()
        );
        Assert::same(
            1,
            (int) $db->query(
                'SELECT COUNT(DISTINCT CORRELATION_ID) FROM payment_action_event'
            )->fetchColumn()
        );
        Assert::same(
            'MANUAL-EVENT-001',
            (string) $db->query(
                'SELECT CORRELATION_ID FROM payment_action_event LIMIT 1'
            )->fetchColumn()
        );

        Assert::same(
            2,
            (int) $db->query('SELECT COUNT(*) FROM operational_event')->fetchColumn()
        );
        Assert::same(
            'PAYMENT,NONE',
            (string) $db->query(
                "SELECT GROUP_CONCAT(ECONOMIC_IMPACT ORDER BY ID SEPARATOR ',')
                 FROM operational_event"
            )->fetchColumn()
        );
        Assert::same(
            4,
            (int) $db->query('SELECT COUNT(*) FROM sif_audit_event')->fetchColumn()
        );
    }

    public function testRejectsActorWithoutInstallmentRoleAndAuditsDenial(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );

        Assert::throws(SifException::class, function () use ($db, $invoice): void {
            $this->gateway($db, ['FACTURACIO'])->register(
                $db,
                $this->actor(
                    'lectura',
                    ['CONSULTA'],
                    '33333333-3333-4333-8333-333333333333'
                ),
                [
                    'uuid_factura' => $invoice['uuid_factura'],
                    'input' => [
                        'amount' => '40.00',
                        'movement_date' => '2026-06-12',
                        'id_insc' => 10,
                        'operation_id' => 'MANUAL-EVENT-002',
                        'reference' => 'TRF-AUDIT-002',
                    ],
                ]
            );
        }, 403);

        Assert::same(
            0,
            (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn()
        );
        Assert::same(
            'ACCESS_DENIED:REJECTED',
            (string) $db->query(
                "SELECT CONCAT(ACTION, ':', RESULT)
                 FROM payment_action_event LIMIT 1"
            )->fetchColumn()
        );
        Assert::same(
            1,
            (int) $db->query('SELECT COUNT(*) FROM sif_audit_event')->fetchColumn()
        );
        Assert::same(
            0,
            (int) $db->query('SELECT COUNT(*) FROM operational_event')->fetchColumn()
        );
    }

    public function testRequiresExactlyOneInvoiceIdentifierAndAuditsRejection(): void
    {
        $db = TestDatabase::fresh();

        Assert::throws(SifException::class, function () use ($db): void {
            $this->gateway($db, ['FACTURACIO'])->register(
                $db,
                $this->actor(
                    'gestio-real',
                    ['FACTURACIO'],
                    '44444444-4444-4444-8444-444444444444'
                ),
                [
                    'input' => [
                        'amount' => '40.00',
                        'movement_date' => '2026-06-12',
                        'id_insc' => 10,
                        'operation_id' => 'MANUAL-EVENT-003',
                        'reference' => 'TRF-AUDIT-003',
                    ],
                ]
            );
        }, 422);

        Assert::same(
            'REQUESTED,REJECTED',
            (string) $db->query(
                "SELECT GROUP_CONCAT(RESULT ORDER BY ID SEPARATOR ',')
                 FROM payment_action_event"
            )->fetchColumn()
        );
        Assert::same(
            0,
            (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn()
        );
    }

    private function gateway(\PDO $db, array $roles): InternalInstallmentPaymentGateway
    {
        $uuids = new UuidGenerator();

        return new InternalInstallmentPaymentGateway(
            new ManualInstallmentPaymentService(
                new ManualPaymentInvoiceRepository(),
                new ManualInstallmentPaymentPayloadBuilder(),
                RegisterPaymentTest::paymentServiceFor($db)
            ),
            $roles,
            new InstallmentPaymentAuditTrail(
                new PaymentActionEventRepository($uuids),
                new OperationalEventRepository($uuids),
                new SifAuditEventRepository($uuids),
                'test'
            )
        );
    }

    private function actor(string $id, array $roles, string $requestId): array
    {
        return [
            'actor_id' => $id,
            'roles' => $roles,
            'request_id' => $requestId,
            'source_channel' => 'INTERNAL_API',
        ];
    }
}
