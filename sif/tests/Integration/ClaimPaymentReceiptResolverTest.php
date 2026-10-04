<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ClaimPaymentExternalReceiptRepository;
use Prisma\Sif\Service\ClaimPaymentReceiptResolver;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ClaimPaymentReceiptResolverTest
{
    public function testReusesExistingBankReceiptAllocatedToSameInvoice(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC024|RECEIPT|SAME_INVOICE',
                'emesa_abans_cobrament' => 1,
            ])
        );

        $payment = RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'TRANSFERENCIA|REF:BANK-100',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '30.00',
            'movement_date' => '2026-10-04 02:30:00',
            'reference' => 'BANK-100',
            'idpag' => 123,
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '30.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        $resolved = $this->resolver()->resolveExisting(
            $db,
            'BANK_REFERENCE',
            'BANK-100',
            $invoice['uuid_factura'],
            '30.00',
            123
        );

        Assert::same($payment['uuid_payment'], $resolved['uuid_payment']);
        Assert::same(true, $resolved['idempotency_reused']);
        Assert::same(true, $resolved['reconciled_existing']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
    }

    public function testRejectsExistingReceiptAllocatedToDifferentInvoice(): void
    {
        $db = TestDatabase::fresh();
        $invoiceService = IssueInvoiceTest::serviceFor($db);
        $invoiceA = $invoiceService->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC024|RECEIPT|INVOICE_A',
                'emesa_abans_cobrament' => 1,
            ])
        );
        $invoiceB = $invoiceService->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC024|RECEIPT|INVOICE_B',
                'emesa_abans_cobrament' => 1,
            ])
        );

        RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'TRANSFERENCIA|REF:BANK-DIFFERENT',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '20.00',
            'movement_date' => '2026-10-04 02:35:00',
            'reference' => 'BANK-DIFFERENT',
            'idpag' => 123,
            'allocations' => [[
                'uuid_factura' => $invoiceA['uuid_factura'],
                'amount' => '20.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        Assert::throws(SifException::class, function () use ($db, $invoiceB): void {
            $this->resolver()->resolveExisting(
                $db,
                'BANK_REFERENCE',
                'BANK-DIFFERENT',
                $invoiceB['uuid_factura'],
                '20.00',
                123
            );
        }, 409);
    }

    public function testRejectsExistingReceiptWhenAmountDoesNotMatch(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC024|RECEIPT|AMOUNT_MISMATCH',
                'emesa_abans_cobrament' => 1,
            ])
        );

        RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'TRANSFERENCIA|REF:BANK-AMOUNT',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '25.00',
            'movement_date' => '2026-10-04 02:36:00',
            'reference' => 'BANK-AMOUNT',
            'idpag' => 123,
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '25.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        Assert::throws(SifException::class, function () use ($db, $invoice): void {
            $this->resolver()->resolveExisting(
                $db,
                'BANK_REFERENCE',
                'BANK-AMOUNT',
                $invoice['uuid_factura'],
                '30.00',
                123
            );
        }, 409);
    }

    public function testRejectsExistingReceiptWhenIdpagDoesNotMatchClaim(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC024|RECEIPT|IDPAG_MISMATCH',
                'emesa_abans_cobrament' => 1,
            ])
        );

        RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'TRANSFERENCIA|REF:BANK-IDPAG',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '25.00',
            'movement_date' => '2026-10-04 02:37:00',
            'reference' => 'BANK-IDPAG',
            'idpag' => 999,
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '25.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        Assert::throws(SifException::class, function () use ($db, $invoice): void {
            $this->resolver()->resolveExisting(
                $db,
                'BANK_REFERENCE',
                'BANK-IDPAG',
                $invoice['uuid_factura'],
                '25.00',
                123
            );
        }, 409);
    }

    public function testRedsysIdentityMustAlreadyExistBeforeClaimReconciliation(): void
    {
        $resolver = $this->resolver();

        Assert::throws(
            SifException::class,
            fn () => $resolver->assertMayCreateNew('DS_ORDER'),
            409
        );
        Assert::throws(
            SifException::class,
            fn () => $resolver->assertMayCreateNew('PROVIDER_REF'),
            409
        );

        $resolver->assertMayCreateNew('BANK_REFERENCE');
    }

    private function resolver(): ClaimPaymentReceiptResolver
    {
        return new ClaimPaymentReceiptResolver(
            new ClaimPaymentExternalReceiptRepository()
        );
    }
}
