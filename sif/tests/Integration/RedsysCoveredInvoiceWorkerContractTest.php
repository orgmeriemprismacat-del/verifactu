<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RedsysCoveredInvoiceWorkerContractTest
{
    public function testWorkerWiresCoveredInvoiceResolverIntoCourseHandler(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/scripts/process-redsys-callback-queue.php'
        );
        if ($source === false) {
            Assert::fail('Could not read Redsys callback worker script');
        }

        Assert::stringContainsString('InvoiceBeforePaymentCoverageRepository', $source);
        Assert::stringContainsString('new PaymentService(', $source);
        Assert::stringContainsString('new RedsysCoveredInvoicePaymentService(', $source);
        Assert::stringContainsString('$coveredInvoicePayments', $source);
        Assert::stringContainsString('new RedsysCourseInvoiceService(', $source);
    }

    public function testWorkerPreflightRequiresCoveredInvoicePersistenceTables(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/scripts/preflight-redsys-callback-queue.php'
        );
        if ($source === false) {
            Assert::fail('Could not read Redsys callback preflight script');
        }

        foreach ([
            'invoice_before_payment_coverage',
            'factura',
            'payment_transaction',
            'payment_allocation',
            'enrollment_fund_movement',
        ] as $table) {
            Assert::stringContainsString($table, $source);
        }
    }
}
