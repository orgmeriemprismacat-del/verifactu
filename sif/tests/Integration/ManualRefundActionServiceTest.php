<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\PaymentActionEventRepository;
use Prisma\Sif\Service\CourseEnrollmentFundAllocationService;
use Prisma\Sif\Service\ManualRefundActionService;
use Prisma\Sif\Service\ManualRefundPayloadBuilder;
use Prisma\Sif\Service\ManualRefundService;
use Prisma\Sif\Service\PaymentActionGateway;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ManualRefundActionServiceTest
{
    public function testAuditedRefundWritesRequestedSucceededAndReused(): void
    {
        $db = TestDatabase::fresh();
        $invoice = $this->paidEnrollmentWithFunds($db, 'REFUND-AUDIT-OK');
        $service = $this->service($db);

        $input = [
            'idempotency_key' => 'REFUND|AUDIT|UC006|40',
            'amount' => '40.00',
            'movement_date' => '2026-10-04 04:30:00',
            'reference' => 'BANK-REF-AUDIT-40',
            'source_enrollment_id' => 10,
        ];

        $first = $service->registerByUuid(
            $this->audit('req-refund-1', 'corr-refund-1'),
            $invoice['uuid_factura'],
            $input
        );
        $second = $service->registerByUuid(
            $this->audit('req-refund-2', 'corr-refund-2'),
            $invoice['uuid_factura'],
            $input
        );

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);
        Assert::same('REFUND|AUDIT|UC006|40', $first['payment_idempotency_key']);

        $events = $db->query(
            "SELECT ACTION, RESULT, IS_TERMINAL, UUID_PAYMENT,
                    PAYMENT_IDEMPOTENCY_KEY
             FROM payment_action_event
             WHERE ACTION = 'LINK_REFUND'
             ORDER BY ID"
        )->fetchAll(\PDO::FETCH_ASSOC);

        Assert::same(4, count($events));
        Assert::same('REQUESTED', $events[0]['RESULT']);
        Assert::same('SUCCEEDED', $events[1]['RESULT']);
        Assert::same('REQUESTED', $events[2]['RESULT']);
        Assert::same('REUSED', $events[3]['RESULT']);
        Assert::same($first['uuid_payment'], $events[1]['UUID_PAYMENT']);
        Assert::same('REFUND|AUDIT|UC006|40', $events[1]['PAYMENT_IDEMPOTENCY_KEY']);

        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM payment_transaction
             WHERE TIPUS_MOVIMENT = 'REFUND'"
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM enrollment_fund_movement
             WHERE MOVEMENT_TYPE = 'REFUND_EXIT'"
        )->fetchColumn());
    }

    public function testFailedAuditedRefundRollsBackPaymentAndWritesFailed(): void
    {
        $db = TestDatabase::fresh();
        $invoice = $this->paidInvoiceWithoutEnrollmentLedger($db, 'REFUND-AUDIT-FAIL');
        $service = $this->service($db);

        Assert::throws(SifException::class, function () use ($service, $invoice): void {
            $service->registerByUuid(
                $this->audit('req-refund-fail', 'corr-refund-fail'),
                $invoice['uuid_factura'],
                [
                    'idempotency_key' => 'REFUND|AUDIT|UC006|FAIL',
                    'amount' => '40.00',
                    'movement_date' => '2026-10-04 04:31:00',
                    'reference' => 'BANK-REF-AUDIT-FAIL',
                    'source_enrollment_id' => 10,
                ]
            );
        }, 409);

        $events = $db->query(
            "SELECT ACTION, RESULT, IS_TERMINAL
             FROM payment_action_event
             WHERE ACTION = 'LINK_REFUND'
             ORDER BY ID"
        )->fetchAll(\PDO::FETCH_ASSOC);

        Assert::same(2, count($events));
        Assert::same('REQUESTED', $events[0]['RESULT']);
        Assert::same('FAILED', $events[1]['RESULT']);
        Assert::same(0, (int) $db->query(
            "SELECT COUNT(*) FROM payment_transaction
             WHERE TIPUS_MOVIMENT = 'REFUND'"
        )->fetchColumn());
        Assert::same(0, (int) $db->query(
            "SELECT COUNT(*) FROM enrollment_fund_movement
             WHERE MOVEMENT_TYPE = 'REFUND_EXIT'"
        )->fetchColumn());
    }

    private function service(\PDO $db): ManualRefundActionService
    {
        $refunds = new ManualRefundService(
            new ManualPaymentInvoiceRepository(),
            new ManualRefundPayloadBuilder(),
            RegisterPaymentTest::paymentServiceFor($db)
        );
        $gateway = new PaymentActionGateway(
            $db,
            new TransactionRunner($db),
            new PaymentActionEventRepository(new UuidGenerator())
        );

        return new ManualRefundActionService($gateway, $refunds);
    }

    private function audit(string $requestId, string $correlationId): array
    {
        return [
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'source_environment' => 'TEST',
            'source_channel' => 'CLI',
            'actor_type' => 'HUMAN',
            'actor_id' => 'uc006-refund-test',
            'actor_role' => 'GESTIO',
            'reason_code' => 'REFUND_DECISION',
            'occurred_at' => '2026-10-04 04:29:00.000000',
        ];
    }

    private function paidEnrollmentWithFunds(\PDO $db, string $order): array
    {
        $invoice = $this->paidInvoiceWithoutEnrollmentLedger($db, $order);

        (new CourseEnrollmentFundAllocationService(
            new EnrollmentFundMovementRepository(new UuidGenerator())
        ))->allocate(
            $db,
            $order,
            [
                'inscription' => ['ID' => 10, 'A_PAGAR' => '120.00'],
                'payment' => ['amount' => '120.00'],
            ],
            $invoice
        );

        return $invoice;
    }

    private function paidInvoiceWithoutEnrollmentLedger(\PDO $db, string $order): array
    {
        $payload = Fixtures::invoicePayload([
            'idempotency_key' => 'INVOICE|' . $order,
            'payment' => [
                'idempotency_key' => 'PAYMENT|' . $order,
                'movement_type' => 'CHARGE',
                'method' => 'REDSYS',
                'source_channel' => 'REDSYS',
                'amount' => '120.00',
                'movement_date' => '2026-10-04 04:20:00',
                'ds_order' => $order,
                'idpag' => 9930,
            ],
            'relations' => [[
                'source_type' => 'INSCRIPCIO',
                'source_id' => 10,
                'factura_relacionada' => 9930,
                'idpag' => 9930,
                'ds_order' => $order,
                'visible_alumne' => 1,
            ]],
        ]);

        return IssueInvoiceTest::serviceFor($db)->issueInvoice($payload);
    }
}
