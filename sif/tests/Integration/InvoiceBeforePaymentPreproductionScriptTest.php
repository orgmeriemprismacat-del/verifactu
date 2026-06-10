<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class InvoiceBeforePaymentPreproductionScriptTest
{
    public function testScriptBuildsPreproductionInvoiceBeforePaymentProcessor(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/process-invoice-before-payment.php');

        if ($source === false) {
            Assert::fail('Could not read invoice before payment processor script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('new InvoiceService(', $source);
        Assert::stringContainsString('new InvoiceBeforePaymentService(', $source);
        Assert::stringContainsString('new InvoiceBeforePaymentPayloadBuilder()', $source);
        Assert::stringContainsString('issueBeforePayment($payload)', $source);
        Assert::stringContainsString('--payload-file=', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new PaymentService(') || str_contains($source, 'registerPayment(')) {
            Assert::fail('Invoice before payment processor must not register payments.');
        }

        if (str_contains($source, 'LegacySyncService') || str_contains($source, 'makeLegacy')) {
            Assert::fail('Invoice before payment processor must not sync legacy in this cut.');
        }
    }
}
