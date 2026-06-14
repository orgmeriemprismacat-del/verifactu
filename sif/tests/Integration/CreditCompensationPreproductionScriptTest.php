<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class CreditCompensationPreproductionScriptTest
{
    public function testScriptBuildsCreditCompensationProcessorWithoutIssuingInvoices(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/process-credit-compensation.php');

        if ($source === false) {
            Assert::fail('Could not read credit compensation processor script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('new CreditBalanceService(', $source);
        Assert::stringContainsString('new CreditBalanceRepository(new UuidGenerator())', $source);
        Assert::stringContainsString('new ManualPaymentInvoiceRepository()', $source);
        Assert::stringContainsString('new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())', $source);
        Assert::stringContainsString('--uuid-credit=', $source);
        Assert::stringContainsString('--uuid-factura=', $source);
        Assert::stringContainsString('--num-visible=', $source);
        Assert::stringContainsString('applyCreditByUuid', $source);
        Assert::stringContainsString('applyCreditByNumVisible', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new InvoiceService(') || str_contains($source, 'issueInvoice(')) {
            Assert::fail('Credit compensation processor must not issue invoices.');
        }

        if (str_contains($source, 'LegacySyncService')) {
            Assert::fail('Credit compensation processor must not sync legacy in this cut.');
        }
    }
}
