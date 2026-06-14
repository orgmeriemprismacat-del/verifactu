<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ClaimPaymentPreviewScriptTest
{
    public function testPreviewBuildsClaimPaymentPayloadWithoutRegisteringPayment(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preview-claim-payment.php');

        if ($source === false) {
            Assert::fail('Could not read claim payment preview script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('new ManualPaymentInvoiceRepository()', $source);
        Assert::stringContainsString('new ClaimPaymentPayloadBuilder()', $source);
        Assert::stringContainsString('--uuid-factura=', $source);
        Assert::stringContainsString('--num-visible=', $source);
        Assert::stringContainsString('--claim-reference=', $source);
        Assert::stringContainsString('loadClaimPaymentInvoice($sifDb, $selector)', $source);
        Assert::stringContainsString('forExistingInvoice($invoice[\'UUID_FACTURA\'], $input)', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new PaymentService(')) {
            Assert::fail('Claim payment preview must not build PaymentService.');
        }

        if (str_contains($source, 'registerPayment(')) {
            Assert::fail('Claim payment preview must not register payments.');
        }
    }
}
