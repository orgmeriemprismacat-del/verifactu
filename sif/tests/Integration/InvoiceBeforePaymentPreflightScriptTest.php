<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class InvoiceBeforePaymentPreflightScriptTest
{
    public function testPreflightChecksInvoiceBeforePaymentReadinessOnlyInSif(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preflight-invoice-before-payment.php');

        if ($source === false) {
            Assert::fail('Could not read invoice before payment preflight script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('environment_not_production', $source);
        Assert::stringContainsString('sif_database_connectivity', $source);
        Assert::stringContainsString('factura_table', $source);
        Assert::stringContainsString('factura_linia_table', $source);
        Assert::stringContainsString('factura_registres_table', $source);
        Assert::stringContainsString('fiscal_queue_table', $source);
        Assert::stringContainsString('payment_transaction_table', $source);
        Assert::stringContainsString('payment_allocation_table', $source);
        Assert::stringContainsString('fiscal_chain_state_seeded', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'makeLegacy') || str_contains($source, 'redsys_merchant_key_configured')) {
            Assert::fail('Invoice before payment preflight must not require legacy or Redsys.');
        }

        if (str_contains($source, 'issueInvoice(') || str_contains($source, 'registerPayment(')) {
            Assert::fail('Invoice before payment preflight must not mutate fiscal or payment data.');
        }
    }
}
