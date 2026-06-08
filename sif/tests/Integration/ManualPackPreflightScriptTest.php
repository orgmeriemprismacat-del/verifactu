<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualPackPreflightScriptTest
{
    public function testScriptChecksManualPackReadinessWithoutIssuingInvoices(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preflight-manual-pack.php');

        if ($source === false) {
            Assert::fail('Could not read manual pack preflight script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('environment_not_production', $source);
        Assert::stringContainsString('legacy_db_configured', $source);
        Assert::stringContainsString('sif_database_connectivity', $source);
        Assert::stringContainsString('legacy_database_connectivity', $source);
        Assert::stringContainsString('payment_transaction', $source);
        Assert::stringContainsString('payment_allocation', $source);
        Assert::stringContainsString('inscripcions', $source);
        Assert::stringContainsString('info_pack', $source);
        Assert::stringContainsString('curs', $source);
        Assert::stringContainsString('fiscal_chain_state', $source);
        Assert::stringContainsString('SHOW TABLES LIKE', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new ManualPackInvoiceService(') || str_contains($source, 'issueInvoice(')) {
            Assert::fail('Manual pack preflight must not construct the invoice orchestrator.');
        }

        if (str_contains($source, 'SIF_REDSYS_MERCHANT_KEY')) {
            Assert::fail('Manual pack preflight must not require Redsys readiness.');
        }
    }
}
