<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualRefundPreviewScriptTest
{
    public function testPreviewBuildsRefundPayloadWithoutRegisteringRefund(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preview-manual-refund.php');

        if ($source === false) {
            Assert::fail('Could not read manual refund preview script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('new ManualPaymentInvoiceRepository()', $source);
        Assert::stringContainsString('new ManualRefundPayloadBuilder()', $source);
        Assert::stringContainsString('--uuid-factura=', $source);
        Assert::stringContainsString('--num-visible=', $source);
        Assert::stringContainsString('loadManualRefundInvoice($sifDb, $selector)', $source);
        Assert::stringContainsString('forExistingInvoice($invoice[\'UUID_FACTURA\'], $input)', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new PaymentService(')) {
            Assert::fail('Manual refund preview must not build PaymentService.');
        }

        if (str_contains($source, 'registerPayment(') || str_contains($source, 'registerByUuid(')) {
            Assert::fail('Manual refund preview must not register refunds.');
        }
    }
}
