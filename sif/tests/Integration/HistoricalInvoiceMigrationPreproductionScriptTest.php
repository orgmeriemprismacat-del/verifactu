<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class HistoricalInvoiceMigrationPreproductionScriptTest
{
    public function testScriptBuildsPreproductionHistoricalMigrationProcessor(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/process-historical-invoice-migration.php');

        if ($source === false) {
            Assert::fail('Could not read historical invoice migration processor script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('new HistoricalInvoiceMigrationService(', $source);
        Assert::stringContainsString('new HistoricalInvoicePayloadBuilder()', $source);
        Assert::stringContainsString('new HistoricalInvoiceMigrationRepository(', $source);
        Assert::stringContainsString('importHistoricalInvoice($payload)', $source);
        Assert::stringContainsString('--payload-file=', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new InvoiceService(') || str_contains($source, 'issueInvoice(')) {
            Assert::fail('Historical invoice migration processor must not issue invoices.');
        }

        if (str_contains($source, 'new PaymentService(') || str_contains($source, 'registerPayment(')) {
            Assert::fail('Historical invoice migration processor must not register payments.');
        }

        if (str_contains($source, 'LegacySyncService') || str_contains($source, 'makeLegacy')) {
            Assert::fail('Historical invoice migration processor must not sync legacy.');
        }
    }
}
