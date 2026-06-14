<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class HistoricalInvoiceMigrationPreviewScriptTest
{
    public function testPreviewBuildsHistoricalMigrationPayloadWithoutWriting(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preview-historical-invoice-migration.php');

        if ($source === false) {
            Assert::fail('Could not read historical invoice migration preview script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('new HistoricalInvoicePayloadBuilder()', $source);
        Assert::stringContainsString('readPayloadFile', $source);
        Assert::stringContainsString('--payload-file=', $source);
        Assert::stringContainsString('dry_run', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new InvoiceService(') || str_contains($source, 'issueInvoice(')) {
            Assert::fail('Historical invoice migration preview must not issue invoices.');
        }

        if (str_contains($source, 'factura_registres') || str_contains($source, 'fiscal_queue')) {
            Assert::fail('Historical invoice migration preview must not touch fiscal records or AEAT queue.');
        }
    }
}
