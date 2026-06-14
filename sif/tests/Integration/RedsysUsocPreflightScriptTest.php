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
        Assert::stringContainsString('payment_transaction_table', $source);
        Assert::stringContainsString('payment_allocation_table', $source);
        Assert::stringContainsString('redsys_notifications_table', $source);
        Assert::stringContainsString('legacy_inscripcions_table', $source);
        Assert::stringContainsString('legacy_curs_table', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new RedsysUsocInvoiceService(') || str_contains($source, 'issueInvoice(')) {
            Assert::fail('USOC preflight must stay read-only.');
        }
    }
}
