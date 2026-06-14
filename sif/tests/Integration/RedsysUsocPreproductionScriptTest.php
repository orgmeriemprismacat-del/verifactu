<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RedsysUsocPreproductionScriptTest
{
    public function testScriptBuildsManualPreproductionUsocStudentProcessor(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/process-redsys-usoc.php');

        if ($source === false) {
            Assert::fail('Could not read Redsys USOC preproduction script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('new RedsysUsocInvoiceService(', $source);
        Assert::stringContainsString('new LegacyUsocSnapshotRepository()', $source);
        Assert::stringContainsString('new LegacyUsocInvoicePayloadBuilder()', $source);
        Assert::stringContainsString('new RedsysInvoicePayloadBuilder($notifications)', $source);
        Assert::stringContainsString('$service->issueStudentFromValidatedNotification($sifDb, $legacyDb, $dsOrder, $usocAmount)', $source);
        Assert::stringContainsString('--usoc-amount=', $source);
        Assert::stringContainsString('entity_invoice_pending', $source);
        Assert::stringContainsString('legacy_sync_executed', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'LegacySyncService')) {
            Assert::fail('USOC student processor must not sync legacy in this cut.');
        }

        if (str_contains($source, 'RedsysSignatureValidator')) {
            Assert::fail('USOC student processor must consume an already validated notification, not re-parse Redsys POST.');
        }
    }
}
