<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\DebtClaimCaseRepository;
use Prisma\Sif\Repository\DebtSnapshotRepository;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Service\DebtClaimCoordinator;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class DebtClaimCoordinatorReconciliationTest
{
    public function testPartialThenFullPaymentRecalculatesAndClosesClaim(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );
        $service = $this->service($db);
        $actor = $this->actor();

        $service->recordNotice($actor, [
            'uuid_factura' => $invoice['uuid_factura'],
            'action' => 'FIRST_CLAIM',
            'idempotency_key' => 'CLAIM|NOTICE|PAY',
            'reason_code' => 'DEBT_DUE',
            'request_id' => 'REQ-NOTICE',
            'correlation_id' => 'CORR-NOTICE',
        ]);

        $this->pay($db, $invoice['uuid_factura'], '40.00', 'CLAIM|PAY|40');
        $partial = $service->reconcileAfterPayment($actor, [
            'uuid_factura' => $invoice['uuid_factura'],
            'idempotency_key' => 'CLAIM|REC|40',
            'reason_code' => 'PAYMENT_RECEIVED',
            'request_id' => 'REQ-REC-40',
            'correlation_id' => 'CORR-REC-40',
        ]);
        Assert::same('OPEN', $partial['status']);
        Assert::same('80.00', $partial['outstanding']);
        Assert::same(0, $partial['cancelled_notifications']);

        $this->pay($db, $invoice['uuid_factura'], '80.00', 'CLAIM|PAY|80');
        $closed = $service->reconcileAfterPayment($actor, [
            'uuid_factura' => $invoice['uuid_factura'],
            'idempotency_key' => 'CLAIM|REC|120',
            'reason_code' => 'PAYMENT_COMPLETED',
            'request_id' => 'REQ-REC-120',
            'correlation_id' => 'CORR-REC-120',
        ]);

        Assert::same('CLOSED', $closed['status']);
        Assert::same('RESOLVED', $closed['stage']);
        Assert::same('0.00', $closed['outstanding']);
        Assert::same(1, $closed['cancelled_notifications']);
        Assert::same('CANCELLED', (string) $db->query(
            'SELECT STATUS FROM notification_outbox'
        )->fetchColumn());
        Assert::same('PAID', (string) $db->query(
            'SELECT ESTAT_COBRAMENT FROM factura'
        )->fetchColumn());
        Assert::same(3, (int) $db->query('SELECT COUNT(*) FROM debt_claim_event')->fetchColumn());
    }

    private function pay(\PDO $db, string $uuid, string $amount, string $key): void
    {
        RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => $key,
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => $amount,
            'movement_date' => '2026-10-03 12:00:00',
            'reference' => $key,
            'allocations' => [[
                'uuid_factura' => $uuid,
                'amount' => $amount,
                'allocation_type' => 'CLAIM_PAYMENT',
            ]],
        ]);
    }

    private function service(\PDO $db): DebtClaimCoordinator
    {
        $uuids = new UuidGenerator();
        return new DebtClaimCoordinator(
            $db,
            new TransactionRunner($db),
            new DebtSnapshotRepository(),
            new DebtClaimCaseRepository($uuids),
            new NotificationOutboxRepository($uuids),
            new OperationalEventRepository($uuids),
            ['GESTIO_COBRAMENTS'],
            ['GESTIO_COBRAMENTS']
        );
    }

    private function actor(): array
    {
        return [
            'actor_type' => 'USER',
            'actor_id' => 'admin-cobraments',
            'roles' => ['GESTIO_COBRAMENTS'],
            'request_id' => 'REQ-UC012',
        ];
    }
}
