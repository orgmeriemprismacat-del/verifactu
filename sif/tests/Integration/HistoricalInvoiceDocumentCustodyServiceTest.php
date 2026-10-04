<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\HistoricalInvoiceMigrationRepository;
use Prisma\Sif\Service\HistoricalInvoiceDocumentCustodyService;
use Prisma\Sif\Service\HistoricalInvoiceMigrationService;
use Prisma\Sif\Service\HistoricalInvoicePayloadBuilder;
use Prisma\Sif\Service\PrivateDocumentStore;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class HistoricalInvoiceDocumentCustodyServiceTest
{
    public function testCustodyPreservesLegacyPathAndAddsVerifiedStorageReference(): void
    {
        $db = TestDatabase::fresh();
        [$sourceDir, $root, $source, $bytes] = $this->files();
        try {
            $invoice = $this->historicalInvoice($db, [
                'document' => [
                    'type' => 'PDF',
                    'path' => '/legacy/archive/A2024-000123.pdf',
                    'hash' => hash('sha256', $bytes),
                ],
            ]);

            $service = new HistoricalInvoiceDocumentCustodyService(
                new TransactionRunner($db),
                $root
            );
            $result = $service->custody(
                $invoice['uuid_factura'],
                'PDF',
                $source,
                [
                    'actor_id' => 'migration-test',
                    'correlation_id' => 'custody-test-1',
                ]
            );

            Assert::same(true, $result['metadata_reused']);
            Assert::same(false, $result['storage_reused']);
            Assert::same('HISTORICAL', $result['invoice_status']);
            Assert::same('NO_VERIFACTU', $result['aeat_status']);

            $row = $db->query(
                'SELECT PATH_FITXER, STORAGE_REF, HASH_FITXER, ESTAT
                 FROM factura_documents'
            )->fetch(\PDO::FETCH_ASSOC);
            Assert::same('/legacy/archive/A2024-000123.pdf', $row['PATH_FITXER']);
            Assert::same($result['storage_ref'], $row['STORAGE_REF']);
            Assert::same(hash('sha256', $bytes), $row['HASH_FITXER']);
            Assert::same('ARCHIVED', $row['ESTAT']);

            $stored = (new PrivateDocumentStore($root))->readVerified(
                $result['storage_ref'],
                hash('sha256', $bytes)
            );
            Assert::same($bytes, $stored);
            Assert::same(1, (int) $db->query(
                "SELECT COUNT(*) FROM operational_event
                 WHERE OPERATION_TYPE='HISTORICAL_DOCUMENT_CUSTODY'"
            )->fetchColumn());
            Assert::same(1, (int) $db->query(
                "SELECT COUNT(*) FROM sif_audit_event
                 WHERE ACTION='CUSTODY_HISTORICAL_DOCUMENT'"
            )->fetchColumn());
        } finally {
            $this->cleanup($sourceDir, $root);
        }
    }

    public function testCustodyReplayReusesOneMetadataRowAndOneObject(): void
    {
        $db = TestDatabase::fresh();
        [$sourceDir, $root, $source] = $this->files();
        try {
            $invoice = $this->historicalInvoice($db);
            $service = new HistoricalInvoiceDocumentCustodyService(
                new TransactionRunner($db),
                $root
            );

            $first = $service->custody($invoice['uuid_factura'], 'PDF', $source);
            $second = $service->custody($invoice['uuid_factura'], 'PDF', $source);

            Assert::same(false, $first['metadata_reused']);
            Assert::same(true, $second['metadata_reused']);
            Assert::same(true, $second['storage_reused']);
            Assert::same($first['document_id'], $second['document_id']);
            Assert::same($first['storage_ref'], $second['storage_ref']);
            Assert::same(1, (int) $db->query(
                "SELECT COUNT(*) FROM factura_documents WHERE TIPUS='PDF'"
            )->fetchColumn());
        } finally {
            $this->cleanup($sourceDir, $root);
        }
    }

    public function testCustodyRejectsDifferentOriginalForSameType(): void
    {
        $db = TestDatabase::fresh();
        [$sourceDir, $root, $source] = $this->files();
        try {
            $invoice = $this->historicalInvoice($db);
            $service = new HistoricalInvoiceDocumentCustodyService(
                new TransactionRunner($db),
                $root
            );
            $service->custody($invoice['uuid_factura'], 'PDF', $source);

            $other = $sourceDir . '/other.pdf';
            file_put_contents($other, '%PDF-1.4 different historical bytes');

            Assert::throws(
                SifException::class,
                fn (): array => $service->custody(
                    $invoice['uuid_factura'],
                    'PDF',
                    $other
                ),
                409
            );
            Assert::same(1, (int) $db->query(
                "SELECT COUNT(*) FROM factura_documents WHERE TIPUS='PDF'"
            )->fetchColumn());
        } finally {
            $this->cleanup($sourceDir, $root);
        }
    }

    public function testCustodyRejectsNonHistoricalInvoice(): void
    {
        $db = TestDatabase::fresh();
        [$sourceDir, $root, $source] = $this->files();
        try {
            $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
                \Prisma\Sif\Tests\Support\Fixtures::invoicePayload()
            );

            Assert::throws(
                SifException::class,
                fn (): array => (new HistoricalInvoiceDocumentCustodyService(
                    new TransactionRunner($db),
                    $root
                ))->custody($invoice['uuid_factura'], 'PDF', $source),
                409
            );
        } finally {
            $this->cleanup($sourceDir, $root);
        }
    }

    public function testCustodyScriptRequiresActorAndRefusesProduction(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents(
            $root . '/scripts/custody-historical-invoice-document.php'
        );
        if (!is_string($source)) {
            Assert::fail('Could not read historical custody script');
        }

        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('--actor-id=', $source);
        Assert::stringContainsString('SIF_DOCUMENT_ROOT', $source);
        Assert::stringContainsString('HistoricalInvoiceDocumentCustodyService', $source);
        Assert::same(false, str_contains($source, 'InvoiceService'));
        Assert::same(false, str_contains($source, 'fiscal_queue'));
    }

    private function historicalInvoice(\PDO $db, array $overrides = []): array
    {
        $input = array_replace_recursive([
            'num_visible' => 'A2024/000123',
            'issue_date' => '2024-03-15 10:00:00',
            'legacy_id' => 9123,
            'factura_relacionada' => 700,
            'billing' => [
                'name' => 'Client Historic',
                'nif' => '12345678Z',
            ],
            'totals' => [
                'import_base' => '100.00',
                'taxable_base' => '100.00',
                'total' => '100.00',
            ],
            'lines' => [[
                'concept' => 'Factura historica',
                'quantity' => '1.00',
                'unit_price' => '100.00',
                'base' => '100.00',
                'import_base' => '100.00',
                'taxable_base' => '100.00',
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

    private function files(): array
    {
        $base = sys_get_temp_dir() . '/sif-uc011-custody-' . bin2hex(random_bytes(6));
        $sourceDir = $base . '-src';
        $root = $base . '-private';
        mkdir($sourceDir, 0700, true);
        mkdir($root, 0700, true);
        $source = $sourceDir . '/original.pdf';
        $bytes = '%PDF-1.4 original historical invoice';
        file_put_contents($source, $bytes);

        return [$sourceDir, $root, $source, $bytes];
    }

    private function cleanup(string $sourceDir, string $root): void
    {
        if (is_dir($sourceDir)) {
            foreach (glob($sourceDir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($sourceDir);
        }
        if (is_dir($root)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $root,
                    \FilesystemIterator::SKIP_DOTS
                ),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    @rmdir($item->getPathname());
                } else {
                    @unlink($item->getPathname());
                }
            }
            @rmdir($root);
        }
    }
}
