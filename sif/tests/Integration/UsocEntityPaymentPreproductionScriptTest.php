<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class UsocEntityPaymentPreproductionScriptTest
{
    public function testScriptRegistersEntityPaymentAndReconcilesUsocCase(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/process-usoc-entity-payment.php');

        if ($source === false) {
            Assert::fail('Could not read USOC entity payment processor script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('new UsocEntityPaymentService(', $source);
        Assert::stringContainsString('new UsocFinancingCaseRepository(', $source);
        Assert::stringContainsString('new ManualPaymentService(', $source);
        Assert::stringContainsString('new UsocCaseReconciler(', $source);
        Assert::stringContainsString('registerByEntityInvoiceUuid($db, $uuidFactura, $input)', $source);
        Assert::stringContainsString('--uuid-factura=', $source);
        Assert::stringContainsString('--amount=', $source);
        Assert::stringContainsString('--movement-date=', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);
    }
}
