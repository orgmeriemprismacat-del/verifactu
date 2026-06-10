<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class InvoiceBeforePaymentPreviewScriptTest
{
    public function testPreviewBuildsPayloadWithoutIssuingInvoice(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preview-invoice-before-payment.php');

        if ($source === false) {
            Assert::fail('Could not read invoice before payment preview script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('new InvoiceBeforePaymentPayloadBuilder()', $source);
        Assert::stringContainsString('readPayloadFile', $source);
        Assert::stringContainsString('--payload-file=', $source);
        Assert::stringContainsString('dry_run', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new InvoiceService(') || str_contains($source, 'issueInvoice(')) {
            Assert::fail('Invoice before payment preview must not issue invoices.');
        }
    }
}
