<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class CreditBalancePreproductionScriptTest
{
    public function testScriptBuildsCreditBalanceCreatorWithoutIssuingInvoices(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/process-credit-balance.php');

        if ($source === false) {
            Assert::fail('Could not read credit balance processor script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('new CreditBalanceService(', $source);
        Assert::stringContainsString('new CreditBalanceRepository(new UuidGenerator())', $source);
        Assert::stringContainsString('new CreditBalancePayloadBuilder()', $source);
        Assert::stringContainsString('--holder-type=', $source);
        Assert::stringContainsString('--holder-name=', $source);
        Assert::stringContainsString('--source-type=', $source);
        Assert::stringContainsString('createCredit($input)', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new InvoiceService(') || str_contains($source, 'issueInvoice(')) {
            Assert::fail('Credit balance creator must not issue invoices.');
        }

        if (str_contains($source, 'registerPayment(')) {
            Assert::fail('Credit balance creator must not register compensation payments.');
        }
    }
}
