<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\HistoricalInvoiceMigrationRepository;
use Prisma\Sif\Service\HistoricalInvoiceInventoryService;
use Prisma\Sif\Service\HistoricalInvoiceMigrationService;
use Prisma\Sif\Service\HistoricalInvoicePayloadBuilder;
use Prisma\Sif\Service\PrivateDocumentStore;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class HistoricalInvoiceInventoryServiceTest
{
    public function testInventoryMarksMatchingImportedInvoiceAndAggregatesYearSeries(): void
    {
        $db = $this->fixture();
        $this->import($db);

        $result = (new HistoricalInvoiceInventoryService())->inventory($db, $db);

        Assert::same(true, $result['read_only']);
        Assert::same(1, $result['summary']['total']);
        Assert::same(1, $result['summary']['imported_match']);
        Assert::same(0, $result['summary']['blocking']);
        Assert::same('IMPORTED_MATCH', $result['items'][0]['status']);
        Assert::same([], $result['items'][0]['differences']);
        Assert::same('A', $result['groups'][0]['series']);
        Assert::same(2024, $result['groups'][0]['year']);
        Assert::same('A2024/000123', $result['groups'][0]['first_num_visible']);
        Assert::same('A2024/000123', $result['groups'][0]['last_num_visible']);
        Assert::same('100.00', $result['groups'][0]['legacy_total']);
        Assert::same(true, $result['groups'][0]['reconciled']);

        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES);
        Assert::same(false, str_contains((string) $encoded, '12345678Z'));
        Assert::same(hash('sha256', '12345678Z'), $result['items'][0]['billing_nif_fingerprint']);
    }

    public function testInventoryMarksMissingInvoiceWithoutWritingSif(): void
    {
        $db = $this->fixture();

        $result = (new HistoricalInvoiceInventoryService())->inventory($db, $db);

        Assert::same('NOT_IMPORTED', $result['items'][0]['status']);
        Assert::same(1, $result['summary']['not_imported']);
        Assert::same(false, $result['fully_reconciled']);
        Assert::same(false, $result['groups'][0]['reconciled']);
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
    }

    public function testInventoryDetectsMaterialMismatchWithoutLeakingNif(): void
    {
        $db = $this->fixture();
        $this->import($db);
        $db->exec("UPDATE factures SET import = 120.00, cif = '87654321X' WHERE id = 9123");

        $result = (new HistoricalInvoiceInventoryService())->inventory($db, $db);

        Assert::same('IMPORTED_MISMATCH', $result['items'][0]['status']);
        Assert::same(1, $result['summary']['mismatch']);
        Assert::same(1, $result['summary']['blocking']);
        Assert::same('billing_nif', $result['items'][0]['differences'][0]['field']);
        Assert::same(
            hash('sha256', '87654321X'),
            $result['items'][0]['differences'][0]['legacy_fingerprint']
        );
        Assert::same('total', $result['items'][0]['differences'][1]['field']);

        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES);
        Assert::same(false, str_contains((string) $encoded, '87654321X'));
        Assert::same(false, str_contains((string) $encoded, '12345678Z'));
    }

    public function testInventoryVerifiesDocumentBytesAndDetectsTampering(): void
    {
        $db = $this->fixture();
        $root = sys_get_temp_dir() . '/sif-uc011-doc-' . bin2hex(random_bytes(6));
        if (!mkdir($root, 0700, true) && !is_dir($root)) {
            Assert::fail('Could not create temporary private document root');
        }

        try {
            $bytes = '%PDF-1.4 historical original';
            file_put_contents($root . '/historic.pdf', $bytes);
            $this->import($db, [
                'document' => [
                    'type' => 'PDF',
                    'path' => 'historic.pdf',
                    'hash' => hash('sha256', $bytes),
                ],
            ]);

            $service = new HistoricalInvoiceInventoryService();
            $store = new PrivateDocumentStore($root);

            $verified = $service->inventory($db, $db, 9123, $store);
            Assert::same('VERIFIED', $verified['items'][0]['documents'][0]['verification']);
            Assert::same(1, $verified['summary']['documents_verified']);
            Assert::same(true, $verified['fully_reconciled']);

            file_put_contents($root . '/historic.pdf', $bytes . '-tampered');
            $tampered = $service->inventory($db, $db, 9123, $store);
            Assert::same('HASH_MISMATCH', $tampered['items'][0]['documents'][0]['verification']);
            Assert::same(1, $tampered['summary']['documents_unverified']);
            Assert::same(false, $tampered['fully_reconciled']);
        } finally {
            @unlink($root . '/historic.pdf');
            @rmdir($root);
        }
    }

    public function testInventoryScriptIsReadOnlyAndUsesLegacyConnection(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents($root . '/scripts/inventory-historical-invoices.php');
        if (!is_string($source)) {
            Assert::fail('Could not read historical invoice inventory script');
        }

        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('HistoricalInvoiceInventoryService', $source);
        Assert::stringContainsString("'read_only' => true", $source);
        Assert::stringContainsString("'production_authorized' => false", $source);

        foreach ([
            'importHistoricalInvoice(',
            'INSERT INTO factura',
            'UPDATE factures',
            'DELETE FROM factures',
        ] as $forbidden) {
            Assert::same(false, str_contains($source, $forbidden));
        }
    }

    private function fixture(): \PDO
    {
        $db = TestDatabase::fresh();
        $db->exec(
            'CREATE TEMPORARY TABLE factures (
                id INT PRIMARY KEY,
                factura_relacionada INT NULL,
                any INT NOT NULL,
                ordre INT NULL,
                num VARCHAR(30) NOT NULL,
                data DATETIME NOT NULL,
                data_pagament DATETIME NULL,
                generada VARCHAR(30) NULL,
                rao VARCHAR(180) NULL,
                cif VARCHAR(20) NULL,
                adreca VARCHAR(180) NULL,
                cp VARCHAR(10) NULL,
                poblacio VARCHAR(120) NULL,
                concepte1 VARCHAR(255) NULL,
                concepte2 VARCHAR(255) NULL,
                import DECIMAL(12,2) NOT NULL,
                entitat VARCHAR(180) NULL,
                forma_pagament VARCHAR(80) NULL,
                curs VARCHAR(80) NULL,
                hores INT NULL,
                observacions TEXT NULL,
                E_FACT TINYINT NULL
            )'
        );
        $db->prepare(
            'INSERT INTO factures (
                id, factura_relacionada, any, ordre, num, data, data_pagament,
                generada, rao, cif, adreca, cp, poblacio, concepte1, concepte2,
                import, entitat, forma_pagament, curs, hores, observacions, E_FACT
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            9123, 700, 2024, 123, 'A2024/000123', '2024-03-15 10:00:00', null,
            null, 'Client Historic', '12345678Z', 'Carrer Historic 1', '08001',
            'Barcelona', 'Factura historica', null, '100.00', null, 'REDSYS',
            'CURS-TEST', 30, null, 0,
        ]);

        return $db;
    }

    private function import(\PDO $db, array $overrides = []): array
    {
        $input = array_replace_recursive([
            'num_visible' => 'A2024/000123',
            'issue_date' => '2024-03-15 10:00:00',
            'payment_status' => 'UNKNOWN',
            'legacy_id' => 9123,
            'factura_relacionada' => 700,
            'billing' => [
                'name' => 'Client Historic',
                'nif' => '12345678Z',
                'address' => 'Carrer Historic 1',
                'cp' => '08001',
                'city' => 'Barcelona',
                'country' => 'ES',
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
                'quantity' => '1.00',
                'unit_price' => '100.00',
                'base' => '100.00',
                'import_base' => '100.00',
                'taxable_base' => '100.00',
                'iva_regim' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => '100.00',
                'source_type' => 'HISTORIC_WEB_FACTURES',
                'source_id' => 9123,
            ]],
        ], $overrides);

        return (new HistoricalInvoiceMigrationService(
            new TransactionRunner($db),
            new HistoricalInvoicePayloadBuilder(),
            new HistoricalInvoiceMigrationRepository(new UuidGenerator())
        ))->importHistoricalInvoice($input);
    }
}
