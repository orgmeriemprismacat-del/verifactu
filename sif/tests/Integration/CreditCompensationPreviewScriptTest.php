<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class CreditCompensationPreviewScriptTest
{
    public function testPreviewBuildsCompensationPayloadWithoutApplyingCredit(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preview-credit-compensation.php');

        if ($source === false) {
            Assert::fail('Could not read credit compensation preview script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('new CreditBalanceRepository(new UuidGenerator())', $source);
        Assert::stringContainsString('new ManualPaymentInvoiceRepository()', $source);
        Assert::stringContainsString('new CreditBalancePayloadBuilder()', $source);
        Assert::stringContainsString('--uuid-credit=', $source);
        Assert::stringContainsString('--uuid-factura=', $source);
        Assert::stringContainsString('--num-visible=', $source);
        Assert::stringContainsString('forCompensation($credit[\'UUID_CREDIT\'], $invoice[\'UUID_FACTURA\'], $input, $invoice)', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new PaymentRepository(') || str_contains($source, 'createPayment(')) {
            Assert::fail('Credit compensation preview must not create payment transactions.');
        }

        if (str_contains($source, 'applyCreditBy')) {
            Assert::fail('Credit compensation preview must not apply credit.');
        }
    }
}
