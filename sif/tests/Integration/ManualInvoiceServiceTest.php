<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\ManualInvoicePayloadBuilder;
use Prisma\Sif\Service\ManualInvoiceService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ManualInvoiceServiceTest
{
    public function testIssuesPendingManualInvoiceWithoutPaymentOrLegacySync(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->service($db);
        $input = Fixtures::invoicePayload([
            'idempotency_key' => '',
            'reference' => 'FM 2026/101',
            'source_channel' => 'REDSYS',
            'created_by' => 'adam',
        ]);
        $input['relations'] = [];

        $first = $service->issueManualInvoice($input);
        $second = $service->issueManualInvoice($input);

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_factura'], $second['uuid_factura']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM fact_rels')->fetchColumn());

        $invoice = $db->query(
            'SELECT IDEMPOTENCY_KEY, TIPUS_SERIE, TIPUS_FACTURA, SOURCE_CHANNEL,
                    CREATED_BY, ESTAT_COBRAMENT
             FROM factura'
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('INTRANET|MANUAL|REF:FM_2026/101', $invoice['IDEMPOTENCY_KEY']);
        Assert::same('A', $invoice['TIPUS_SERIE']);
        Assert::same('F1', $invoice['TIPUS_FACTURA']);
        Assert::same('INTRANET', $invoice['SOURCE_CHANNEL']);
        Assert::same('adam', $invoice['CREATED_BY']);
        Assert::same('PENDING', $invoice['ESTAT_COBRAMENT']);
    }

    public function testIssuesManualInvoiceWithInitialPaymentInsideIssueInvoice(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->service($db);
        $input = Fixtures::invoicePayload([
            'idempotency_key' => '',
            'reference' => 'FM PAID 2026/102',
            'created_by' => 'pablo',
            'payment' => [
                'method' => 'MANUAL',
                'movement_date' => '2026-06-12 13:00:00',
                'reference' => 'CAIXA-INGRES-102',
                'notes' => 'Factura manual cobrada en el moment de l emissio',
            ],
        ]);

        $first = $service->issueManualInvoice($input);
        $second = $service->issueManualInvoice($input);

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::matchesRegularExpression('/^[0-9a-f-]{36}$/', $first['uuid_payment']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_factura'], $second['uuid_factura']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());

        $invoice = $db->query('SELECT ESTAT_COBRAMENT, SOURCE_CHANNEL FROM factura')->fetch(\PDO::FETCH_ASSOC);
        $payment = $db->query(
            'SELECT IDEMPOTENCY_KEY, TIPUS_MOVIMENT, METODE, SOURCE_CHANNEL,
                    IMPORT, PROVIDER_REF, REFERENCIA_BANCARIA
             FROM payment_transaction'
        )->fetch(\PDO::FETCH_ASSOC);
        $allocation = $db->query('SELECT IMPORT_ASSIGNAT, TIPUS_ASSIGNACIO FROM payment_allocation')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same('PAID', $invoice['ESTAT_COBRAMENT']);
        Assert::same('INTRANET', $invoice['SOURCE_CHANNEL']);
        Assert::same('PAYMENT|INTRANET|MANUAL|REF:FM_PAID_2026/102', $payment['IDEMPOTENCY_KEY']);
        Assert::same('CHARGE', $payment['TIPUS_MOVIMENT']);
        Assert::same('MANUAL', $payment['METODE']);
        Assert::same('INTRANET', $payment['SOURCE_CHANNEL']);
        Assert::same('120.00', $payment['IMPORT']);
        Assert::same('CAIXA-INGRES-102', $payment['PROVIDER_REF']);
        Assert::same('CAIXA-INGRES-102', $payment['REFERENCIA_BANCARIA']);
        Assert::same('120.00', $allocation['IMPORT_ASSIGNAT']);
        Assert::same('INVOICE_PAYMENT', $allocation['TIPUS_ASSIGNACIO']);
    }

    public function testRejectsManualInvoiceWithoutInternalUserBeforeIssuing(): void
    {
        $db = TestDatabase::fresh();

        Assert::throws(SifException::class, function () use ($db): void {
            $this->service($db)->issueManualInvoice(Fixtures::invoicePayload([
                'created_by' => '',
                'user' => '',
                'usuari' => '',
            ]));
        }, 422);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
    }

    private function service(\PDO $db): ManualInvoiceService
    {
        return new ManualInvoiceService(
            new ManualInvoicePayloadBuilder(),
            IssueInvoiceTest::serviceFor($db)
        );
    }
}
