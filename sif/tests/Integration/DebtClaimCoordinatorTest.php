<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\DebtClaimCaseRepository;
use Prisma\Sif\Repository\DebtSnapshotRepository;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Service\DebtClaimCoordinator;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class DebtClaimCoordinatorTest
{
    public function testPreviewUsesInvoiceAndConfirmedAllocationsAsDebtAuthority(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );

        $preview = $this->service($db)->preview(
            $this->manager(),
            ['uuid_factura' => $invoice['uuid_factura']]
        );

        Assert::same(true, $preview['ok']);
        Assert::same('OUTSTANDING', $preview['status']);
        Assert::same('120.00', $preview['snapshot']['total']);
        Assert::same('0.00', $preview['snapshot']['charged']);
        Assert::same('0.00', $preview['snapshot']['refunded']);
        Assert::same('120.00', $preview['snapshot']['outstanding']);
        Assert::same(true, $preview['can_notify']);
        Assert::same('BILLING_PARTY', $preview['recipient_type']);
        Assert::same(hash('sha256', 'client@example.test'), $preview['recipient_hash']);
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM debt_claim_case')->fetchColumn());
    }

    public function testNoticeIsPersistedAuditedEnqueuedAndIdempotent(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );
        $service = $this->service($db);
        $payload = $this->notice($invoice['uuid_factura'], 'FINAL_REMINDER', 'CLAIM|REMINDER|1');

        $first = $service->recordNotice($this->manager(), $payload);
        $second = $service->recordNotice($this->manager(), $payload);

        Assert::same('RECORDED', $first['status']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_claim'], $second['uuid_claim']);
        Assert::same($first['uuid_claim_event'], $second['uuid_claim_event']);
        Assert::same($first['uuid_notification'], $second['uuid_notification']);
        Assert::same('FINAL_REMINDER', $first['stage']);
        Assert::same('120.00', $first['outstanding']);

        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM debt_claim_case')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM debt_claim_event')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM notification_outbox')->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM operational_event WHERE OPERATION_TYPE = 'DEBT_CLAIM_NOTICE'"
        )->fetchColumn());

        $case = $db->query(
            'SELECT STATUS, CURRENT_STAGE, VERSION_NO, OUTSTANDING_AMOUNT FROM debt_claim_case'
        )->fetch(\PDO::FETCH_ASSOC);
        Assert::same('OPEN', (string) $case['STATUS']);
        Assert::same('FINAL_REMINDER', (string) $case['CURRENT_STAGE']);
        Assert::same(1, (int) $case['VERSION_NO']);
        Assert::same('120.00', (string) $case['OUTSTANDING_AMOUNT']);

        $outbox = $db->query(
            'SELECT TEMPLATE_CODE, STATUS, RECIPIENT_TYPE, RECIPIENT_HASH FROM notification_outbox'
        )->fetch(\PDO::FETCH_ASSOC);
        Assert::same('DEBT_CLAIM_FINAL_REMINDER', (string) $outbox['TEMPLATE_CODE']);
        Assert::same('PENDING', (string) $outbox['STATUS']);
        Assert::same('BILLING_PARTY', (string) $outbox['RECIPIENT_TYPE']);
        Assert::same(hash('sha256', 'client@example.test'), (string) $outbox['RECIPIENT_HASH']);
    }

    public function testSameIdempotencyKeyWithDifferentPayloadFailsClosed(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );
        $service = $this->service($db);
        $payload = $this->notice($invoice['uuid_factura'], 'FINAL_REMINDER', 'CLAIM|CONFLICT|1');

        $service->recordNotice($this->manager(), $payload);
        $payload['notes'] = 'different payload';

        Assert::throws(
            SifException::class,
            fn () => $service->recordNotice($this->manager(), $payload),
            409
        );
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM debt_claim_event')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM notification_outbox')->fetchColumn());
    }

    public function testNoticeStagesCannotRepeatOrRegressWithNewKeys(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );
        $service = $this->service($db);
        $actor = $this->manager();

        $service->recordNotice(
            $actor,
            $this->notice($invoice['uuid_factura'], 'FINAL_REMINDER', 'CLAIM|STAGE|1')
        );
        $service->recordNotice(
            $actor,
            $this->notice($invoice['uuid_factura'], 'FIRST_CLAIM', 'CLAIM|STAGE|2')
        );
        $service->recordNotice(
            $actor,
            $this->notice($invoice['uuid_factura'], 'FINAL_CLAIM', 'CLAIM|STAGE|3')
        );

        Assert::throws(
            SifException::class,
            fn () => $service->recordNotice(
                $actor,
                $this->notice($invoice['uuid_factura'], 'FIRST_CLAIM', 'CLAIM|STAGE|4')
            ),
            409
        );

        Assert::same(3, (int) $db->query('SELECT COUNT(*) FROM debt_claim_event')->fetchColumn());
        Assert::same(3, (int) $db->query('SELECT COUNT(*) FROM notification_outbox')->fetchColumn());
        Assert::same('FINAL_CLAIM', (string) $db->query(
            'SELECT CURRENT_STAGE FROM debt_claim_case'
        )->fetchColumn());
    }

    public function testPartialThenFullPaymentRecalculatesAndClosesClaim(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );
        $service = $this->service($db);
        $actor = $this->manager();

        $service->recordNotice(
            $actor,
            $this->notice($invoice['uuid_factura'], 'FIRST_CLAIM', 'CLAIM|PAY|NOTICE')
        );

        $this->pay($db, $invoice['uuid_factura'], '40.00', 'CLAIM|PAYMENT|40');
        $partial = $service->reconcileAfterPayment($actor, [
            'uuid_factura' => $invoice['uuid_factura'],
            'idempotency_key' => 'CLAIM|RECONCILE|40',
            'reason_code' => 'PAYMENT_RECEIVED',
            'request_id' => 'REQ-CLAIM-40',
            'correlation_id' => 'CORR-CLAIM-40',
        ]);

        Assert::same('OPEN', $partial['status']);
        Assert::same('80.00', $partial['outstanding']);
        Assert::same('FIRST_CLAIM', $partial['stage']);
        Assert::same(1, $partial['cancelled_notifications']);
        Assert::same('CANCELLED', (string) $db->query(
            'SELECT STATUS FROM notification_outbox'
        )->fetchColumn());

        $this->pay($db, $invoice['uuid_factura'], '80.00', 'CLAIM|PAYMENT|80');
        $closed = $service->reconcileAfterPayment($actor, [
            'uuid_factura' => $invoice['uuid_factura'],
            'idempotency_key' => 'CLAIM|RECONCILE|120',
            'reason_code' => 'PAYMENT_COMPLETED',
            'request_id' => 'REQ-CLAIM-120',
            'correlation_id' => 'CORR-CLAIM-120',
        ]);

        Assert::same('CLOSED', $closed['status']);
        Assert::same('0.00', $closed['outstanding']);
        Assert::same('RESOLVED', $closed['stage']);
        Assert::same(0, $closed['cancelled_notifications']);
        Assert::same('CANCELLED', (string) $db->query(
            'SELECT STATUS FROM notification_outbox'
        )->fetchColumn());
        Assert::same('PAID', (string) $db->query(
            'SELECT ESTAT_COBRAMENT FROM factura'
        )->fetchColumn());
        Assert::same(3, (int) $db->query('SELECT COUNT(*) FROM debt_claim_event')->fetchColumn());
        Assert::same(3, (int) $db->query(
            "SELECT COUNT(*) FROM operational_event
             WHERE OPERATION_TYPE IN ('DEBT_CLAIM_NOTICE', 'DEBT_CLAIM_RECONCILE')"
        )->fetchColumn());
    }

    public function testSettledInvoiceDoesNotCreateClaimOrNotification(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );
        $this->pay($db, $invoice['uuid_factura'], '120.00', 'CLAIM|PAID|ONE');

        $result = $this->service($db)->recordNotice(
            $this->manager(),
            $this->notice($invoice['uuid_factura'], 'FINAL_REMINDER', 'CLAIM|PAID|NOTICE')
        );

        Assert::same('NO_CHANGE', $result['status']);
        Assert::same('NO_OUTSTANDING_BALANCE', $result['reason']);
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM debt_claim_case')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM notification_outbox')->fetchColumn());
    }

    public function testUnauthorizedActorCannotMutateDebtClaim(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );

        Assert::throws(
            SifException::class,
            fn () => $this->service($db)->recordNotice(
                ['actor_id' => 'viewer', 'roles' => ['LECTURA'], 'request_id' => 'REQ-DENIED'],
                $this->notice($invoice['uuid_factura'], 'FINAL_REMINDER', 'CLAIM|DENIED')
            ),
            403
        );

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM debt_claim_case')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM debt_claim_event')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM notification_outbox')->fetchColumn());
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
            ['GESTIO_COBRAMENTS', 'ADMIN'],
            ['GESTIO_COBRAMENTS', 'ADMIN']
        );
    }

    private function manager(): array
    {
        return [
            'actor_type' => 'USER',
            'actor_id' => 'admin-cobraments',
            'roles' => ['GESTIO_COBRAMENTS'],
            'request_id' => 'REQ-CLAIM-BASE',
        ];
    }

    private function notice(string $uuidFactura, string $action, string $key): array
    {
        return [
            'uuid_factura' => $uuidFactura,
            'action' => $action,
            'idempotency_key' => $key,
            'reason_code' => 'DEBT_DUE',
            'request_id' => 'REQ-' . hash('sha256', $key),
            'correlation_id' => 'CORR-' . hash('sha256', $key),
        ];
    }

    private function pay(\PDO $db, string $uuidFactura, string $amount, string $key): void
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
                'uuid_factura' => $uuidFactura,
                'amount' => $amount,
                'allocation_type' => 'CLAIM_PAYMENT',
            ]],
        ]);
    }
}
