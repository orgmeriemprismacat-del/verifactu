<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RedsysGiftPreflightScriptTest
{
    public function testPreflightChecksGiftReadinessWithoutIssuingInvoice(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preflight-redsys-gift.php');

        if ($source === false) {
            Assert::fail('Could not read Redsys gift preflight script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('environment_not_production', $source);
        Assert::stringContainsString('redsys_merchant_key_configured', $source);
        Assert::stringContainsString('legacy_db_configured', $source);
        Assert::stringContainsString('sif_database_connectivity', $source);
        Assert::stringContainsString('legacy_database_connectivity', $source);
        Assert::stringContainsString('redsys_notifications_table', $source);
        Assert::stringContainsString('legacy_regal_table', $source);
        Assert::stringContainsString('fiscal_chain_state_seeded', $source);
        Assert::stringContainsString('tableExists($legacyDb, \'regal\')', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new InvoiceService(') || str_contains($source, 'issueInvoice(')) {
            Assert::fail('Gift preflight must not issue invoices.');
        }
    }
}
