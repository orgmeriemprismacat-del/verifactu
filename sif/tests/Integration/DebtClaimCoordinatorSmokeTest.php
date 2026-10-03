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

final class DebtClaimCoordinatorSmokeTest
{
    public function testCreatesAndReusesDebtClaimNotice(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );
        $uuids = new UuidGenerator();
        $service = new DebtClaimCoordinator(
            $db,
            new TransactionRunner($db),
            new DebtSnapshotRepository(),
            new DebtClaimCaseRepository($uuids),
            new NotificationOutboxRepository($uuids),
            new OperationalEventRepository($uuids),
            ['GESTIO_COBRAMENTS'],
            ['GESTIO_COBRAMENTS']
        );
        $actor = [
            'actor_type' => 'USER',
            'actor_id' => 'admin-cobraments',
            'roles' => ['GESTIO_COBRAMENTS'],
            'request_id' => 'REQ-UC012',
        ];
        $payload = [
            'uuid_factura' => $invoice['uuid_factura'],
            'action' => 'FINAL_REMINDER',
            'idempotency_key' => 'CLAIM|REMINDER|SMOKE',
            'reason_code' => 'DEBT_DUE',
            'request_id' => 'REQ-UC012-NOTICE',
            'correlation_id' => 'CORR-UC012-NOTICE',
        ];

        $preview = $service->preview($actor, ['uuid_factura' => $invoice['uuid_factura']]);
        $first = $service->recordNotice($actor, $payload);
        $retry = $service->recordNotice($actor, $payload);

        Assert::same('120.00', $preview['snapshot']['outstanding']);
        Assert::same(true, $preview['can_notify']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $retry['idempotency_reused']);
        Assert::same($first['uuid_claim_event'], $retry['uuid_claim_event']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM debt_claim_case')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM debt_claim_event')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM notification_outbox')->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM operational_event WHERE OPERATION_TYPE = 'DEBT_CLAIM_NOTICE'"
        )->fetchColumn());
    }
}
