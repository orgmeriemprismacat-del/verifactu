<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualCoursePreflightScriptTest
{
    public function testPreflightChecksManualCourseReadinessWithoutIssuingInvoices(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preflight-manual-course.php');

        if ($source === false) {
            Assert::fail('Could not read manual course preflight script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('environment_not_production', $source);
        Assert::stringContainsString('legacy_db_configured', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('factura_table', $source);
        Assert::stringContainsString('factura_linia_table', $source);
        Assert::stringContainsString('fiscal_queue_table', $source);
        Assert::stringContainsString('payment_transaction_table', $source);
        Assert::stringContainsString('payment_allocation_table', $source);
        Assert::stringContainsString('fiscal_chain_state_seeded', $source);
        Assert::stringContainsString('inscripcions', $source);
        Assert::stringContainsString('curs', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);
        Assert::stringContainsString('exit(count($failed) === 0 ? 0 : 1)', $source);

        if (str_contains($source, 'redsys')) {
            Assert::fail('Manual course preflight must not depend on Redsys readiness.');
        }

        if (str_contains($source, 'ManualCourseInvoiceService')) {
            Assert::fail('Preflight must not build the invoice orchestration service.');
        }

        if (str_contains($source, 'issueFromLegacyCoursePayment') || str_contains($source, 'issueInvoice(')) {
            Assert::fail('Preflight must not issue invoices.');
        }
    }
}
