<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RedsysCallbackQueueScriptTest
{
    public function testWorkerScriptRefusesProductionAndUsesAsyncWorker(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/scripts/process-redsys-callback-queue.php'
        );

        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('RedsysCallbackWorker', $source);
        Assert::stringContainsString('--limit=', $source);
        Assert::stringContainsString('--worker-id=', $source);
    }

    public function testPreflightIsReadOnlyAndChecksAsyncSchema(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/scripts/preflight-redsys-callback-queue.php'
        );

        Assert::stringContainsString('redsys_payment_intent', $source);
        Assert::stringContainsString('redsys_notifications', $source);
        Assert::stringContainsString('redsys_callback_queue', $source);
        Assert::stringContainsString('PAYLOAD_HASH', $source);

        foreach (['INSERT ', 'UPDATE ', 'DELETE '] as $writeSql) {
            if (stripos($source, $writeSql) !== false) {
                Assert::fail('Redsys callback queue preflight must be read-only');
            }
        }
    }
}
