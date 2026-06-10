<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualPaymentPreviewScriptTest
{
    public function testPreviewBuildsRegisterPaymentPayloadWithoutRegisteringPayment(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preview-manual-payment.php');

        if ($source === false) {
            Assert::fail('Could not read manual payment preview script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('new ManualPaymentInvoiceRepository()', $source);
        Assert::stringContainsString('new ManualPaymentPayloadBuilder()', $source);
        Assert::stringContainsString('--uuid-factura=', $source);
        Assert::stringContainsString('--num-visible=', $source);
        Assert::stringContainsString('loadManualPaymentInvoice($sifDb, $selector)', $source);
        Assert::stringContainsString('forExistingInvoice($invoice[\'UUID_FACTURA\'], $input)', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new PaymentService(')) {
            Assert::fail('Manual payment preview must not build PaymentService.');
        }

        if (str_contains($source, 'registerPayment(')) {
            Assert::fail('Manual payment preview must not register payments.');
        }
    }
}
