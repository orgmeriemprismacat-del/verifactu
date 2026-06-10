<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualGiftPreproductionScriptTest
{
    public function testScriptBuildsManualPreproductionGiftProcessor(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/process-manual-gift.php');

        if ($source === false) {
            Assert::fail('Could not read manual gift preproduction script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('new ManualGiftInvoiceService(', $source);
        Assert::stringContainsString('new LegacyGiftSnapshotRepository()', $source);
        Assert::stringContainsString('new ManualGiftInvoicePayloadBuilder()', $source);
        Assert::stringContainsString('--gift-id=', $source);
        Assert::stringContainsString('--gift-code=', $source);
        Assert::stringContainsString('issueByGiftIdFromManualPayment', $source);
        Assert::stringContainsString('issueByGiftCodeFromManualPayment', $source);
        Assert::stringContainsString('legacy_sync_executed', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'LegacySyncService')) {
            Assert::fail('Manual gift processor must not sync legacy FACT_REL in this cut.');
        }
    }
}
