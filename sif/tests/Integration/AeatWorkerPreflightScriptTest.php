<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class AeatWorkerPreflightScriptTest
{
    public function testPreflightReportsReadinessMetricsAndAlertsWithoutSending(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preflight-aeat-worker.php');
        if ($source === false) {
            Assert::fail('Could not read AEAT worker preflight script');
        }

        Assert::stringContainsString('new AeatPreflight()', $source);
        Assert::stringContainsString('new FiscalQueueMetricsRepository()', $source);
        Assert::stringContainsString('DEAD_LETTER_THRESHOLD', $source);
        Assert::stringContainsString('DUE_QUEUE_THRESHOLD', $source);
        Assert::stringContainsString('STALE_WORKER_LOCK', $source);

        foreach (['new FiscalQueueProcessor(', 'processNext(', 'processBatch(', '->send('] as $forbidden) {
            if (str_contains($source, $forbidden)) {
                Assert::fail('AEAT preflight must not send or process fiscal records: ' . $forbidden);
            }
        }
    }
}
