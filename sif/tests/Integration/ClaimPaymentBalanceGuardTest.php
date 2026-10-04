<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\ClaimPaymentBalanceGuard;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ClaimPaymentBalanceGuardTest
{
    public function testAllowsChargeUpToOutstandingBalance(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC024|BALANCE|ALLOW',
                'emesa_abans_cobrament' => 1,
            ])
        );

        RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'TRANSFERENCIA|REF:UC024_BALANCE_PARTIAL',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '40.00',
            'movement_date' => '2026-10-04 02:40:00',
            'reference' => 'UC024_BALANCE_PARTIAL',
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '40.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        $outstanding = (new ClaimPaymentBalanceGuard())->assertMayCharge(
            $db,
            $invoice['uuid_factura'],
            '80.00'
        );

        Assert::same('80.00', $outstanding);
    }

    public function testRejectsChargeAboveOutstandingBalance(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC024|BALANCE|OVERPAY',
                'emesa_abans_cobrament' => 1,
            ])
        );

        RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'TRANSFERENCIA|REF:UC024_BALANCE_100',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '100.00',
            'movement_date' => '2026-10-04 02:45:00',
            'reference' => 'UC024_BALANCE_100',
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '100.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        Assert::throws(SifException::class, function () use ($db, $invoice): void {
            (new ClaimPaymentBalanceGuard())->assertMayCharge(
                $db,
                $invoice['uuid_factura'],
                '21.00'
            );
        }, 409);
    }

    public function testRejectsNewChargeWhenInvoiceIsAlreadyPaid(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC024|BALANCE|PAID',
                'emesa_abans_cobrament' => 1,
            ])
        );

        RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'TRANSFERENCIA|REF:UC024_BALANCE_FULL',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '120.00',
            'movement_date' => '2026-10-04 02:50:00',
            'reference' => 'UC024_BALANCE_FULL',
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '120.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        Assert::throws(SifException::class, function () use ($db, $invoice): void {
            (new ClaimPaymentBalanceGuard())->assertMayCharge(
                $db,
                $invoice['uuid_factura'],
                '1.00'
            );
        }, 409);
    }
}
