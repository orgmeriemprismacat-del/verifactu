<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ClaimPaymentPreproductionScriptTest
{
    public function testScriptBuildsPreproductionClaimPaymentProcessor(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/process-claim-payment.php');

        if ($source === false) {
            Assert::fail('Could not read claim payment preproduction script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('new ClaimPaymentService(', $source);
        Assert::stringContainsString('new ManualPaymentInvoiceRepository()', $source);
        Assert::stringContainsString('new ClaimPaymentPayloadBuilder()', $source);
        Assert::stringContainsString('new PaymentService(', $source);
        Assert::stringContainsString('--uuid-factura=', $source);
        Assert::stringContainsString('--num-visible=', $source);
        Assert::stringContainsString('--claim-reference=', $source);
        Assert::stringContainsString('registerByUuid(', $source);
        Assert::stringContainsString('registerByNumVisible(', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new InvoiceService(') || str_contains($source, 'issueInvoice(')) {
            Assert::fail('Claim payment processor must not issue invoices.');
        }
    }
}
