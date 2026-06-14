<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\HistoricalInvoiceMigrationRepository;
use Prisma\Sif\Service\HistoricalInvoiceMigrationService;
use Prisma\Sif\Service\HistoricalInvoicePayloadBuilder;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class HistoricalInvoiceMigrationServiceTest
{
    public function testImportsHistoricalInvoiceWithoutFiscalRecordOrQueue(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->service($db);
        $input = $this->input();

        $first = $service->importHistoricalInvoice($input);
        $second = $service->importHistoricalInvoice($input);

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_factura'], $second['uuid_factura']);
        Assert::same('A2024/000123', $first['num_visible']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_linia')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fact_rels')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_documents')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM fiscal_sequence')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT LAST_FISCAL_ORDER FROM fiscal_chain_state WHERE ID = 1')->fetchColumn());

        $invoice = $db->query(
            'SELECT IDEMPOTENCY_KEY, NUM_VISIBLE, TIPUS_SERIE, ANY_FACT, NUM_SEQ,
                    ESTAT_FACTURA, ESTAT_AEAT, ESTAT_COBRAMENT, SOURCE_CHANNEL
             FROM factura'
        )->fetch(\PDO::FETCH_ASSOC);
        $relation = $db->query(
            'SELECT SOURCE_TYPE, SOURCE_ID, RELATION_TYPE, FACTURA_RELACIONADA, VISIBLE_ALUMNE
             FROM fact_rels'
        )->fetch(\PDO::FETCH_ASSOC);
        $document = $db->query('SELECT TIPUS, PATH_FITXER, HASH_FITXER, ESTAT FROM factura_documents')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same('HISTORIC|FACT:A2024/000123', $invoice['IDEMPOTENCY_KEY']);
        Assert::same('A2024/000123', $invoice['NUM_VISIBLE']);
        Assert::same('A', $invoice['TIPUS_SERIE']);
        Assert::same(2024, (int) $invoice['ANY_FACT']);
        Assert::same(123, (int) $invoice['NUM_SEQ']);
        Assert::same('HISTORICAL', $invoice['ESTAT_FACTURA']);
        Assert::same('NO_VERIFACTU', $invoice['ESTAT_AEAT']);
        Assert::same('PAID', $invoice['ESTAT_COBRAMENT']);
        Assert::same('MIGRACIO', $invoice['SOURCE_CHANNEL']);
        Assert::same('HISTORIC_WEB_FACTURES', $relation['SOURCE_TYPE']);
        Assert::same(9123, (int) $relation['SOURCE_ID']);
        Assert::same('HISTORIC_LINK', $relation['RELATION_TYPE']);
        Assert::same(700, (int) $relation['FACTURA_RELACIONADA']);
        Assert::same(1, (int) $relation['VISIBLE_ALUMNE']);
        Assert::same('PDF', $document['TIPUS']);
        Assert::same('/historic/factures/A2024-000123.pdf', $document['PATH_FITXER']);
        Assert::same(str_repeat('b', 64), $document['HASH_FITXER']);
        Assert::same('ARCHIVED', $document['ESTAT']);
    }

    private function service(\PDO $db): HistoricalInvoiceMigrationService
    {
        return new HistoricalInvoiceMigrationService(
            new TransactionRunner($db),
            new HistoricalInvoicePayloadBuilder(),
            new HistoricalInvoiceMigrationRepository(new UuidGenerator())
        );
    }

    private function input(): array
    {
        return [
            'num_visible' => 'A2024/000123',
            'issue_date' => '2024-03-15 10:00:00',
            'payment_status' => 'PAID',
            'legacy_id' => 9123,
            'factura_relacionada' => 700,
            'visible_alumne' => 1,
            'billing' => [
                'name' => 'Client Historic',
                'nif' => '12345678Z',
                'address' => 'Carrer Historic 1',
                'cp' => '08001',
                'city' => 'Barcelona',
                'province' => 'Barcelona',
                'country' => 'ES',
                'email' => 'historic@example.test',
            ],
            'totals' => [
                'import_base' => '100.00',
                'discount' => '0.00',
                'taxable_base' => '100.00',
                'iva_regim' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => '100.00',
            ],
            'lines' => [[
                'concept' => 'Factura historica',
                'detail' => 'Servei migrat',
                'quantity' => '1.00',
                'unit_price' => '100.00',
                'base' => '100.00',
                'import_base' => '100.00',
                'discount_amount' => '0.00',
                'taxable_base' => '100.00',
                'iva_regim' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => '100.00',
                'source_type' => 'HISTORIC_WEB_FACTURES',
                'source_id' => 9123,
            ]],
            'document' => [
                'type' => 'PDF',
                'path' => '/historic/factures/A2024-000123.pdf',
                'hash' => str_repeat('b', 64),
            ],
        ];
    }
}
