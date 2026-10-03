<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\InvoiceBeforePaymentPayloadBuilder;
use Prisma\Sif\Service\InvoiceBeforePaymentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class Uc021CompanyResponsiblePaymentFlowTest
{
    public function testJointInvoiceCanMoveFromPendingToPartialToPaidWithoutSecondFiscalRecord(): void
    {
        $db = TestDatabase::fresh();

        $invoiceService = new InvoiceBeforePaymentService(
            new InvoiceBeforePaymentPayloadBuilder(),
            IssueInvoiceTest::serviceFor($db)
        );

        $invoice = $invoiceService->issueBeforePayment($this->jointInvoicePayload());

        Assert::same(true, $invoice['ok']);
        Assert::same(false, $invoice['idempotency_reused']);
        Assert::same('PENDING', (string) $db->query(
            'SELECT ESTAT_COBRAMENT FROM factura WHERE UUID_FACTURA = "' . $invoice['uuid_factura'] . '"'
        )->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura_linia')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM fact_rels')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM invoice_before_payment_coverage')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());

        $payments = RegisterPaymentTest::paymentServiceFor($db);

        $firstPayment = $payments->registerPayment([
            'idempotency_key' => 'TRANSFERENCIA|UC021|REF:PARTIAL-80',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '80.00',
            'movement_date' => '2026-10-03 10:00:00',
            'reference' => 'UC021-PARTIAL-80',
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '80.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        Assert::same(false, $firstPayment['idempotency_reused']);
        Assert::same('PARTIAL', (string) $db->query(
            'SELECT ESTAT_COBRAMENT FROM factura WHERE UUID_FACTURA = "' . $invoice['uuid_factura'] . '"'
        )->fetchColumn());

        $secondPayment = $payments->registerPayment([
            'idempotency_key' => 'TRANSFERENCIA|UC021|REF:FINAL-120',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '120.00',
            'movement_date' => '2026-10-03 11:00:00',
            'reference' => 'UC021-FINAL-120',
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '120.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        Assert::same(false, $secondPayment['idempotency_reused']);
        Assert::same('PAID', (string) $db->query(
            'SELECT ESTAT_COBRAMENT FROM factura WHERE UUID_FACTURA = "' . $invoice['uuid_factura'] . '"'
        )->fetchColumn());

        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same('200.00', number_format((float) $db->query(
            'SELECT SUM(IMPORT_ASSIGNAT) FROM payment_allocation WHERE UUID_FACTURA = "' . $invoice['uuid_factura'] . '"'
        )->fetchColumn(), 2, '.', ''));
    }

    public function testSameIdempotencyKeyWithChangedBillingPartyIsRejected(): void
    {
        $db = TestDatabase::fresh();

        $invoiceService = new InvoiceBeforePaymentService(
            new InvoiceBeforePaymentPayloadBuilder(),
            IssueInvoiceTest::serviceFor($db)
        );

        $payload = $this->jointInvoicePayload();
        $invoiceService->issueBeforePayment($payload);

        $changed = $payload;
        $changed['billing']['name'] = 'Una altra empresa';
        $changed['billing']['nif'] = 'B99999999';

        Assert::throws(SifException::class, function () use ($invoiceService, $changed): void {
            $invoiceService->issueBeforePayment($changed);
        }, 409);

        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM invoice_before_payment_coverage')->fetchColumn());
    }

    private function jointInvoicePayload(): array
    {
        return Fixtures::invoicePayload([
            'idempotency_key' => 'INTRANET|FACTURA_ABANS_COBRAR|UC021:ENTITY7:I11-I12',
            'source_channel' => 'INTRANET',
            'created_by' => 'uc021-test',
            'billing' => [
                'name' => 'Escola Exemple SL',
                'nif' => 'B12345678',
                'email' => 'responsable@example.test',
            ],
            'totals' => [
                'import_base' => '200.00',
                'taxable_base' => '200.00',
                'total' => '200.00',
            ],
            'lines' => [
                [
                    'concept' => 'Curs compartit',
                    'detail' => 'Participant 11',
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
                [
                    'concept' => 'Curs compartit',
                    'detail' => 'Participant 12',
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
                    'source_id' => 12,
                ],
            ],
            'relations' => [
                [
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 11,
                    'relation_type' => 'ORIGIN',
                    'visible_alumne' => 0,
                ],
                [
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 12,
                    'relation_type' => 'ORIGIN',
                    'visible_alumne' => 0,
                ],
            ],
        ]);
    }
}
