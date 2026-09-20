<?php

namespace Prisma\Sif\Tests\Database;

use Prisma\Sif\Tests\Support\Assert;

final class RedsysCallbackQueueSchemaTest
{
    public function testQueueMigrationDefinesDurableJobContract(): void
    {
        $sql = file_get_contents(
            dirname(__DIR__, 2) . '/database/migrations/2026_06_19_000003_create_redsys_callback_queue.sql'
        );

        Assert::stringContainsString('CREATE TABLE IF NOT EXISTS redsys_callback_queue', $sql);
        Assert::stringContainsString('UNIQUE KEY uq_redsys_callback_notification (NOTIFICATION_ID)', $sql);
        Assert::stringContainsString('KEY idx_redsys_callback_available (STATUS, AVAILABLE_AT)', $sql);
        Assert::stringContainsString(
            'FOREIGN KEY (UUID_INTENT) REFERENCES redsys_payment_intent(UUID_INTENT)',
            $sql
        );
    }
}
