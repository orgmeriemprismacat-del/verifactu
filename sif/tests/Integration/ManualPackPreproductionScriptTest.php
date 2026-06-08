<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualPackPreproductionScriptTest
{
    public function testScriptBuildsManualPackPreproductionProcessor(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/process-manual-pack.php');

        if ($source === false) {
            Assert::fail('Could not read manual pack preproduction script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('new ManualPackInvoiceService(', $source);
        Assert::stringContainsString('new LegacySyncService(new LegacySyncRepository())', $source);
        Assert::stringContainsString('new LegacyPackSnapshotRepository()', $source);
        Assert::stringContainsString('new ManualPackInvoicePayloadBuilder()', $source);
        Assert::stringContainsString('$service->issueFromLegacyPackPayment($legacyDb, $idpag, $input)', $source);
        Assert::stringContainsString('--sync-legacy', $source);
        Assert::stringContainsString('syncAfterSifSuccess(', $source);
        Assert::stringContainsString('legacy_sync_executed', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'RedsysSignatureValidator') || str_contains($source, 'RedsysInvoicePayloadBuilder')) {
            Assert::fail('Manual pack processor must not depend on Redsys.');
        }
    }
}
