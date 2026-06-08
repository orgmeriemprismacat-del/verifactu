<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RedsysGiftPreproductionScriptTest
{
    public function testScriptBuildsManualPreproductionGiftProcessor(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/process-redsys-gift.php');

        if ($source === false) {
            Assert::fail('Could not read Redsys gift preproduction script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('new RedsysGiftInvoiceService(', $source);
        Assert::stringContainsString('new LegacyGiftSnapshotRepository()', $source);
        Assert::stringContainsString('new LegacyGiftInvoicePayloadBuilder()', $source);
        Assert::stringContainsString('new RedsysInvoicePayloadBuilder($notifications)', $source);
        Assert::stringContainsString('--gift-id=', $source);
        Assert::stringContainsString('--gift-code=', $source);
        Assert::stringContainsString('issueByGiftIdFromValidatedNotification', $source);
        Assert::stringContainsString('issueByGiftCodeFromValidatedNotification', $source);
        Assert::stringContainsString('legacy_sync_executed', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'LegacySyncService')) {
            Assert::fail('Gift processor must not sync legacy FACT_REL in this cut.');
        }

        if (str_contains($source, 'RedsysSignatureValidator')) {
            Assert::fail('Manual gift processor must consume an already validated notification, not re-parse Redsys POST.');
        }
    }
}
