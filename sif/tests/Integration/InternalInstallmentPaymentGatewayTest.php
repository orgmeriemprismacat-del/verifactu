<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
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
            ['actor_id' => 'gestio-real', 'roles' => ['FACTURACIO']],
            [
                'uuid_factura' => $invoice['uuid_factura'],
                'input' => [
                    'amount' => '40.00',
                    'movement_date' => '2026-06-12',
                    'id_insc' => 10,
                    'user' => 'browser-fals',
                    'operation_id' => 'MANUAL-EVENT-001',
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
            ['actor_id' => 'gestio-segon', 'roles' => ['FACTURACIO']],
            [
                'uuid_factura' => $invoice['uuid_factura'],
                'input' => [
                    'amount' => '40.00',
                    'movement_date' => '2026-06-12',
                    'id_insc' => 10,
                    'operation_id' => 'MANUAL-EVENT-001',
                ],
            ]
        );
        Assert::same(true, $retry['idempotency_reused']);
        Assert::same($result['uuid_payment'], $retry['uuid_payment']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
    }

    public function testRejectsActorWithoutInstallmentRole(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );

        Assert::throws(SifException::class, function () use ($db, $invoice): void {
            $this->gateway($db, ['FACTURACIO'])->register(
                $db,
                ['actor_id' => 'lectura', 'roles' => ['CONSULTA']],
                [
                    'uuid_factura' => $invoice['uuid_factura'],
                    'input' => [
                        'amount' => '40.00',
                        'movement_date' => '2026-06-12',
                        'id_insc' => 10,
                        'operation_id' => 'MANUAL-EVENT-002',
                    ],
                ]
            );
        }, 403);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
    }

    public function testRequiresExactlyOneInvoiceIdentifier(): void
    {
        $db = TestDatabase::fresh();

        Assert::throws(SifException::class, function () use ($db): void {
            $this->gateway($db, ['FACTURACIO'])->register(
                $db,
                ['actor_id' => 'gestio-real', 'roles' => ['FACTURACIO']],
                [
                    'input' => [
                        'amount' => '40.00',
                        'movement_date' => '2026-06-12',
                        'id_insc' => 10,
                        'operation_id' => 'MANUAL-EVENT-003',
                    ],
                ]
            );
        }, 422);
    }

    private function gateway(\PDO $db, array $roles): InternalInstallmentPaymentGateway
    {
        return new InternalInstallmentPaymentGateway(
            new ManualInstallmentPaymentService(
                new ManualPaymentInvoiceRepository(),
                new ManualInstallmentPaymentPayloadBuilder(),
                RegisterPaymentTest::paymentServiceFor($db)
            ),
            $roles
        );
    }
}
