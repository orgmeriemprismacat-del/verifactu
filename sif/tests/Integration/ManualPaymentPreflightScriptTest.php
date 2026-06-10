<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualPaymentPreflightScriptTest
{
    public function testPreflightChecksExistingInvoicePaymentReadinessOnlyInSif(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preflight-manual-payment.php');

        if ($source === false) {
            Assert::fail('Could not read manual payment preflight script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('environment_not_production', $source);
        Assert::stringContainsString('sif_database_connectivity', $source);
        Assert::stringContainsString('factura_table', $source);
        Assert::stringContainsString('payment_transaction_table', $source);
        Assert::stringContainsString('payment_allocation_table', $source);
        Assert::stringContainsString('fiscal_chain_state_seeded', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'makeLegacy') || str_contains($source, 'legacy_')) {
            Assert::fail('Manual payment preflight for existing invoices must not depend on legacy database.');
        }

        if (str_contains($source, 'redsys_merchant_key_configured')) {
            Assert::fail('Manual payment preflight must not require Redsys merchant key.');
        }

        if (str_contains($source, 'new InvoiceService(') || str_contains($source, 'issueInvoice(')) {
            Assert::fail('Manual payment preflight must not issue invoices.');
        }
    }
}
