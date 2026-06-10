<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualGiftPreflightScriptTest
{
    public function testPreflightChecksGiftReadinessWithoutRedsysDependency(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preflight-manual-gift.php');

        if ($source === false) {
            Assert::fail('Could not read manual gift preflight script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('environment_not_production', $source);
        Assert::stringContainsString('legacy_db_configured', $source);
        Assert::stringContainsString('sif_database_connectivity', $source);
        Assert::stringContainsString('legacy_database_connectivity', $source);
        Assert::stringContainsString('payment_transaction_table', $source);
        Assert::stringContainsString('payment_allocation_table', $source);
        Assert::stringContainsString('legacy_regal_table', $source);
        Assert::stringContainsString('fiscal_chain_state_seeded', $source);
        Assert::stringContainsString('tableExists($legacyDb, \'regal\')', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'redsys_merchant_key_configured')) {
            Assert::fail('Manual gift preflight must not require Redsys merchant key.');
        }

        if (str_contains($source, 'new InvoiceService(') || str_contains($source, 'issueInvoice(')) {
            Assert::fail('Manual gift preflight must not issue invoices.');
        }
    }
}
