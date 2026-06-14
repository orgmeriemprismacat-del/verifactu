<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualInvoicePreproductionScriptTest
{
    public function testScriptBuildsPreproductionManualInvoiceProcessor(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/process-manual-invoice.php');

        if ($source === false) {
            Assert::fail('Could not read manual invoice processor script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('new InvoiceService(', $source);
        Assert::stringContainsString('new PaymentPayloadValidator()', $source);
        Assert::stringContainsString('new PaymentRepository(', $source);
        Assert::stringContainsString('new ManualInvoiceService(', $source);
        Assert::stringContainsString('new ManualInvoicePayloadBuilder()', $source);
        Assert::stringContainsString('issueManualInvoice($payload)', $source);
        Assert::stringContainsString('--payload-file=', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new PaymentService(') || str_contains($source, 'registerPayment(')) {
            Assert::fail('Manual invoice processor must not call registerPayment directly.');
        }

        if (str_contains($source, 'LegacySyncService') || str_contains($source, 'makeLegacy')) {
            Assert::fail('Manual invoice processor must not sync legacy in this cut.');
        }
    }
}
