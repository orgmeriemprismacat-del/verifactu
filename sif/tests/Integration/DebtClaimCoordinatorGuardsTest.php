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

final class DebtClaimCoordinatorGuardsTest
{
    public function testConflictingRetryAndStageRegressionAreRejected(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload());
        $service = $this->service($db);
        $actor = $this->actor();

        $first = $this->notice($invoice['uuid_factura'], 'FINAL_REMINDER', 'CLAIM|GUARD|1');
        $service->recordNotice($actor, $first);

        $changed = $first;
        $changed['notes'] = 'payload changed';
        Assert::throws(SifException::class, fn () => $service->recordNotice($actor, $changed), 409);

        $service->recordNotice(
            $actor,
            $this->notice($invoice['uuid_factura'], 'FIRST_CLAIM', 'CLAIM|GUARD|2')
        );
        Assert::throws(
            SifException::class,
            fn () => $service->recordNotice(
                $actor,
                $this->notice($invoice['uuid_factura'], 'FINAL_REMINDER', 'CLAIM|GUARD|3')
            ),
            409
        );

        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM debt_claim_event')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM notification_outbox')->fetchColumn());
    }

    public function testSettledInvoiceAndUnauthorizedActorCannotCreateClaim(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload());

        RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'CLAIM|GUARD|PAID',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '120.00',
            'movement_date' => '2026-10-03 12:00:00',
            'reference' => 'CLAIM-GUARD-PAID',
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '120.00',
                'allocation_type' => 'CLAIM_PAYMENT',
            ]],
        ]);

        $service = $this->service($db);
        $result = $service->recordNotice(
            $this->actor(),
            $this->notice($invoice['uuid_factura'], 'FINAL_REMINDER', 'CLAIM|GUARD|NOCHANGE')
        );
        Assert::same('NO_CHANGE', $result['status']);
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM debt_claim_case')->fetchColumn());

        Assert::throws(
            SifException::class,
            fn () => $service->recordNotice(
                ['actor_id' => 'viewer', 'roles' => ['LECTURA'], 'request_id' => 'REQ-VIEW'],
                $this->notice($invoice['uuid_factura'], 'FINAL_REMINDER', 'CLAIM|GUARD|DENIED')
            ),
            403
        );
    }

    private function service(\PDO $db): DebtClaimCoordinator
    {
        $u = new UuidGenerator();
        return new DebtClaimCoordinator(
            $db,
            new TransactionRunner($db),
            new DebtSnapshotRepository(),
            new DebtClaimCaseRepository($u),
            new NotificationOutboxRepository($u),
            new OperationalEventRepository($u),
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
            'request_id' => 'REQ-GUARD',
        ];
    }

    private function notice(string $uuid, string $action, string $key): array
    {
        return [
            'uuid_factura' => $uuid,
            'action' => $action,
            'idempotency_key' => $key,
            'reason_code' => 'DEBT_DUE',
            'request_id' => 'REQ-' . substr(hash('sha256', $key), 0, 20),
            'correlation_id' => 'CORR-' . substr(hash('sha256', $key), 0, 20),
        ];
    }
}
