<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RedsysUsocPreflightScriptTest
{
    public function testPreflightChecksUsocStudentRequirementsWithoutIssuingInvoice(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preflight-redsys-usoc.php');

        if ($source === false) {
            Assert::fail('Could not read Redsys USOC preflight script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('environment_not_production', $source);
        Assert::stringContainsString('redsys_merchant_key_configured', $source);
        Assert::stringContainsString('legacy_db_configured', $source);
        Assert::stringContainsString('factura_table', $source);
        Assert::stringContainsString('factura_linia_table', $source);
        Assert::stringContainsString('fact_rels_table', $source);
        Assert::stringContainsString('usoc_financing_case_table', $source);
        Assert::stringContainsString('payment_transaction_table', $source);
        Assert::stringContainsString('payment_allocation_table', $source);
        Assert::stringContainsString('redsys_notifications_table', $source);
        Assert::stringContainsString('redsys_payment_intent_table', $source);
        Assert::stringContainsString('legacy_inscripcions_table', $source);
        Assert::stringContainsString('legacy_curs_table', $source);
        Assert::stringContainsString('legacy_a_pagar_column', $source);
        Assert::stringContainsString('legacy_tipus_desc_column', $source);
        Assert::stringContainsString('legacy_valid_desc_column', $source);
        Assert::stringContainsString('information_schema.columns', $source);
        Assert::stringContainsString('usoc_intent_snapshot_validator', $source);
        Assert::stringContainsString('UsocIntentSnapshotValidator::class', $source);
        Assert::stringContainsString('redsys_usoc_invoice_service', $source);
        Assert::stringContainsString('RedsysUsocInvoiceService::class', $source);
        Assert::stringContainsString('redsys_intent_api_file', $source);
        Assert::stringContainsString('/public/api/redsys/intents/create.php', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new RedsysUsocInvoiceService(') || str_contains($source, 'issueInvoice(')) {
            Assert::fail('USOC preflight must stay read-only.');
        }
    }
}
