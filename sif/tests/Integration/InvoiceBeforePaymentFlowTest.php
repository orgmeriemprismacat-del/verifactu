<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class InvoiceBeforePaymentFlowTest
{
    public function testInvoiceBeforePaymentKeepsFiscalRecordStableAndPaymentLaterMarksPaid(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'INTRANET|FACTURA_ABANS_COBRAR|FACT_REL:900',
            'source_channel' => 'INTRANET',
            'created_by' => 'intranet-factura-abans-cobrar',
            'emesa_abans_cobrament' => 1,
            'relations' => [[
                'source_type' => 'INSCRIPCIO',
                'source_id' => 900,
                'factura_relacionada' => 900,
                'visible_alumne' => 1,
            ]],
        ]));

        Assert::same(true, $invoice['ok']);
        Assert::same(false, $invoice['idempotency_reused']);
        Assert::same(1, (int) $db->query('SELECT EMESA_ABANS_COBRAMENT FROM factura')->fetchColumn());
        Assert::same('PENDING', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT LAST_FISCAL_ORDER FROM fiscal_chain_state WHERE ID = 1')->fetchColumn());

        $payment = RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'TRANSFERENCIA|FACT_REL:900|REF:TRF900',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '120.00',
            'movement_date' => '2026-06-06 10:30:00',
            'reference' => 'TRF900',
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '120.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        Assert::same(true, $payment['ok']);
        Assert::matchesRegularExpression('/^[0-9a-f-]{36}$/', $payment['uuid_payment']);
        Assert::same('PAID', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT LAST_FISCAL_ORDER FROM fiscal_chain_state WHERE ID = 1')->fetchColumn());

        $transaction = $db->query('SELECT METODE, SOURCE_CHANNEL, REFERENCIA_BANCARIA FROM payment_transaction')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same('TRANSFERENCIA', $transaction['METODE']);
        Assert::same('INTRANET', $transaction['SOURCE_CHANNEL']);
        Assert::same('TRF900', $transaction['REFERENCIA_BANCARIA']);
    }
}
