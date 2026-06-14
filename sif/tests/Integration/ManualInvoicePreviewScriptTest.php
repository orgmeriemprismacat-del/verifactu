<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualInvoicePreviewScriptTest
{
    public function testPreviewBuildsManualInvoicePayloadWithoutIssuingInvoice(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preview-manual-invoice.php');

        if ($source === false) {
            Assert::fail('Could not read manual invoice preview script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('new ManualInvoicePayloadBuilder()', $source);
        Assert::stringContainsString('readPayloadFile', $source);
        Assert::stringContainsString('--payload-file=', $source);
        Assert::stringContainsString('dry_run', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new InvoiceService(') || str_contains($source, 'issueInvoice(')) {
            Assert::fail('Manual invoice preview must not issue invoices.');
        }

        if (str_contains($source, 'new PaymentService(') || str_contains($source, 'registerPayment(')) {
            Assert::fail('Manual invoice preview must not register payments.');
        }
    }
}
