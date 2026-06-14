<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualRectificationPreviewScriptTest
{
    public function testPreviewBuildsRectificationPayloadWithoutIssuingInvoice(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preview-manual-rectification.php');

        if ($source === false) {
            Assert::fail('Could not read manual rectification preview script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('new ManualPaymentInvoiceRepository()', $source);
        Assert::stringContainsString('new ManualRectificationPayloadBuilder()', $source);
        Assert::stringContainsString('--uuid-factura=', $source);
        Assert::stringContainsString('--num-visible=', $source);
        Assert::stringContainsString('--reason=', $source);
        Assert::stringContainsString('--mode=', $source);
        Assert::stringContainsString('forOriginalInvoice($invoice, $input)', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new InvoiceService(') || str_contains($source, 'issueInvoice(')) {
            Assert::fail('Manual rectification preview must not issue invoices.');
        }

        if (str_contains($source, 'new PaymentService(')) {
            Assert::fail('Manual rectification preview must not build PaymentService.');
        }
    }
}
