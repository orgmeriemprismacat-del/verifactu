<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Service\ExistingInvoiceLegacyProjectionService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ExistingInvoiceLegacyProjectionServiceTest
{
    public function testProjectsPartialPaymentAcrossInscriptionLinesDeterministically(): void
    {
        $db = TestDatabase::fresh();
        $payload = Fixtures::invoicePayload([
            'idempotency_key' => 'UC002|PROJECTION|INVOICE',
            'totals' => [
                'import_base' => '200.00',
                'taxable_base' => '200.00',
                'total' => '200.00',
            ],
            'lines' => [
                [
                    'concept' => 'Participant 10',
                    'detail' => 'A',
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
                    'source_id' => 10,
                ],
                [
                    'concept' => 'Participant 11',
                    'detail' => 'B',
                    'quantity' => '1.00',
                    'unit_price' => '80.00',
                    'base' => '80.00',
                    'import_base' => '80.00',
                    'discount_amount' => '0.00',
                    'taxable_base' => '80.00',
                    'iva_regim' => 'EXEMPT',
                    'iva_pct' => '0.00',
                    'iva_import' => '0.00',
                    'total' => '80.00',
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 11,
                ],
            ],
            'relations' => [
                [
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 10,
                    'factura_relacionada' => 900,
                    'idpag' => 700,
                    'visible_alumne' => 1,
                ],
                [
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 11,
                    'factura_relacionada' => 900,
                    'idpag' => 700,
                    'visible_alumne' => 1,
                ],
            ],
        ]);

        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice($payload);
        RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'UC002|PROJECTION|PAYMENT',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '150.00',
            'movement_date' => '2026-10-04 03:30:00',
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '150.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        $projection = (new ExistingInvoiceLegacyProjectionService())
            ->build($db, $invoice['uuid_factura']);

        Assert::same('150.00', $projection['net_paid']);
        Assert::same('150.00', $projection['projected_total']);
        Assert::same('0.00', $projection['overpaid_amount']);
        Assert::same('120.00', $projection['items'][0]['projected_payment']);
        Assert::same(true, $projection['items'][0]['fully_paid']);
        Assert::same(10, $projection['items'][0]['id_insc']);
        Assert::same('30.00', $projection['items'][1]['projected_payment']);
        Assert::same(false, $projection['items'][1]['fully_paid']);
        Assert::same(11, $projection['items'][1]['id_insc']);
    }

    public function testCapsLegacyProjectionWhenInvoiceIsOverpaid(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC002|PROJECTION|OVERPAID',
                'emesa_abans_cobrament' => 1,
            ])
        );

        RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'UC002|PROJECTION|OVERPAYMENT',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '150.00',
            'movement_date' => '2026-10-04 03:30:00',
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '150.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        $projection = (new ExistingInvoiceLegacyProjectionService())
            ->build($db, $invoice['uuid_factura']);

        Assert::same('150.00', $projection['net_paid']);
        Assert::same('120.00', $projection['projected_total']);
        Assert::same('30.00', $projection['overpaid_amount']);
        Assert::same('120.00', $projection['items'][0]['projected_payment']);
    }
}
