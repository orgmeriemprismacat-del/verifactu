<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualInstallmentPaymentPreviewScriptTest
{
    public function testPreviewBuildsInstallmentPayloadWithoutRegisteringPayment(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preview-manual-installment.php');

        if ($source === false) {
            Assert::fail('Could not read manual installment preview script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('new ManualPaymentInvoiceRepository()', $source);
        Assert::stringContainsString('new ManualInstallmentPaymentPayloadBuilder()', $source);
        Assert::stringContainsString('--uuid-factura=', $source);
        Assert::stringContainsString('--num-visible=', $source);
        Assert::stringContainsString('--id-insc=', $source);
        Assert::stringContainsString('--user=', $source);
        Assert::stringContainsString('forExistingInvoice($invoice[\'UUID_FACTURA\'], $input)', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new PaymentService(')) {
            Assert::fail('Manual installment preview must not build PaymentService.');
        }

        if (str_contains($source, 'registerPayment(') || str_contains($source, 'registerByUuid(')) {
            Assert::fail('Manual installment preview must not register payments.');
        }
    }
}
