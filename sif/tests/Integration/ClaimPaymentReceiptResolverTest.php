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

    public function testRejectsUnconfirmedOrNonPositiveExistingReceipt(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC024|RECEIPT|STATE',
                'emesa_abans_cobrament' => 1,
            ])
        );

        $payment = RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'TRANSFERENCIA|REF:BANK-STATE',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '25.00',
            'movement_date' => '2026-10-04 03:15:00',
            'reference' => 'BANK-STATE',
            'idpag' => 123,
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '25.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        $db->prepare('UPDATE payment_transaction SET ESTAT = ? WHERE UUID_PAYMENT = ?')
            ->execute(['FAILED', $payment['uuid_payment']]);

        Assert::throws(SifException::class, function () use ($db, $invoice): void {
            $this->resolver()->resolveExisting(
                $db,
                'BANK_REFERENCE',
                'BANK-STATE',
                $invoice['uuid_factura'],
                '25.00',
                123
            );
        }, 409);

        $db->prepare(
            'UPDATE payment_transaction SET ESTAT = ?, TIPUS_MOVIMENT = ? WHERE UUID_PAYMENT = ?'
        )->execute(['CONFIRMED', 'REFUND', $payment['uuid_payment']]);

        Assert::throws(SifException::class, function () use ($db, $invoice): void {
            $this->resolver()->resolveExisting(
                $db,
                'BANK_REFERENCE',
                'BANK-STATE',
                $invoice['uuid_factura'],
                '25.00',
                123
            );
        }, 409);
    }

    public function testSplitReceiptUsesAllocationAmountForTargetInvoice(): void
    {
        $db = TestDatabase::fresh();
        $invoiceService = IssueInvoiceTest::serviceFor($db);
        $invoiceA = $invoiceService->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC024|RECEIPT|SPLIT_A',
                'emesa_abans_cobrament' => 1,
            ])
        );
        $invoiceB = $invoiceService->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC024|RECEIPT|SPLIT_B',
                'emesa_abans_cobrament' => 1,
                'relations' => [[
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 11,
                    'factura_relacionada' => 501,
                    'idpag' => 123,
                    'ds_order' => '999998',
                    'visible_alumne' => 1,
                ]],
                'lines' => [[
                    'concept' => 'Curs B',
                    'detail' => 'Curs de prova B',
                    'quantity' => '1.00',
                    'unit_price' => '120.00',
                    'base' => '120.00',
                    'import_base' => '120.00',
                    'discount_amount' => '0.00',
                    'taxable_base' => '120.00',
                    'iva_regim' => 'EXEMPT',
                    'iva_pct' => '0.00',
                    'iva_import' => '0.00',
                    'total' => '120.00',
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 11,
                ]],
            ])
        );

        $payment = RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'TRANSFERENCIA|REF:BANK-SPLIT',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '100.00',
            'movement_date' => '2026-10-04 03:20:00',
            'reference' => 'BANK-SPLIT',
            'idpag' => 123,
            'allocations' => [
                [
                    'uuid_factura' => $invoiceA['uuid_factura'],
                    'amount' => '40.00',
                    'allocation_type' => 'INVOICE_PAYMENT',
                ],
                [
                    'uuid_factura' => $invoiceB['uuid_factura'],
                    'amount' => '60.00',
                    'allocation_type' => 'INVOICE_PAYMENT',
                ],
            ],
        ]);

        $resolved = $this->resolver()->resolveExisting(
            $db,
            'BANK_REFERENCE',
            'BANK-SPLIT',
            $invoiceA['uuid_factura'],
            '40.00',
            123
        );

        Assert::same($payment['uuid_payment'], $resolved['uuid_payment']);

        Assert::throws(SifException::class, function () use ($db, $invoiceA): void {
            $this->resolver()->resolveExisting(
                $db,
                'BANK_REFERENCE',
                'BANK-SPLIT',
                $invoiceA['uuid_factura'],
                '100.00',
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
