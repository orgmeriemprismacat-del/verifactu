<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualGroupPreproductionScriptTest
{
    public function testScriptBuildsPreproductionManualGroupProcessor(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/process-manual-group.php');

        if ($source === false) {
            Assert::fail('Could not read manual group preproduction script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('new ManualGroupInvoiceService(', $source);
        Assert::stringContainsString('new LegacyGroupSnapshotRepository()', $source);
        Assert::stringContainsString('new ManualGroupInvoicePayloadBuilder()', $source);
        Assert::stringContainsString('$service->issueFromLegacyGroupPayment($legacyDb, $idpag, $input)', $source);
        Assert::stringContainsString('--sync-legacy', $source);
        Assert::stringContainsString('syncAfterSifSuccess(', $source);
        Assert::stringContainsString('legacy_sync_executed', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);
    }
}
