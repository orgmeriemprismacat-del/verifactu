<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Repository\FiscalQueueMetricsRepository;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class FiscalQueueMetricsRepositoryTest
{
    public function testReportsStatusesDueEntriesAndStaleLocks(): void
    {
        $db = TestDatabase::fresh();
        IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'METRICS|PENDING',
        ]));
        IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'METRICS|STALE',
        ]));
        $ids = $db->query('SELECT ID FROM fiscal_queue ORDER BY ID')->fetchAll(\PDO::FETCH_COLUMN);
        $db->prepare(
            "UPDATE fiscal_queue SET STATUS = 'PROCESSING', LOCKED_AT = '2000-01-01 00:00:00' WHERE ID = ?"
        )->execute([$ids[1]]);

        $metrics = (new FiscalQueueMetricsRepository())->snapshot($db, 900);

        Assert::same(1, $metrics['counts']['PENDING']);
        Assert::same(1, $metrics['counts']['PROCESSING']);
        Assert::same(1, $metrics['due']);
        Assert::same(1, $metrics['stale_locks']);
        Assert::notSame(null, $metrics['oldest_actionable_at']);
    }
}
