<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RedsysCoursePreproductionScriptTest
{
    public function testScriptBuildsManualPreproductionCourseProcessor(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/process-redsys-course.php');

        if ($source === false) {
            Assert::fail('Could not read Redsys course preproduction script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('new RedsysCourseInvoiceService(', $source);
        Assert::stringContainsString('new LegacySyncService(new LegacySyncRepository())', $source);
        Assert::stringContainsString('new LegacyCourseSnapshotRepository()', $source);
        Assert::stringContainsString('new LegacyCourseInvoicePayloadBuilder()', $source);
        Assert::stringContainsString('new RedsysInvoicePayloadBuilder($notifications)', $source);
        Assert::stringContainsString('$service->issueFromValidatedNotification($sifDb, $legacyDb, $dsOrder)', $source);
        Assert::stringContainsString('--sync-legacy', $source);
        Assert::stringContainsString('syncAfterSifSuccess(', $source);
        Assert::stringContainsString('legacy_sync_executed', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'RedsysSignatureValidator')) {
            Assert::fail('Manual processor must consume an already validated notification, not re-parse Redsys POST.');
        }
    }
}
