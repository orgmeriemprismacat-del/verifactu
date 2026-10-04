<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Contract\DocumentAuthorizationPolicyInterface;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\DocumentAccessRepository;
use Prisma\Sif\Repository\FiscalDocumentAccessRepository;
use Prisma\Sif\Repository\HistoricalInvoiceMigrationRepository;
use Prisma\Sif\Repository\InvoiceReadRepository;
use Prisma\Sif\Service\HistoricalInvoiceDocumentCustodyService;
use Prisma\Sif\Service\HistoricalInvoiceMigrationService;
use Prisma\Sif\Service\HistoricalInvoicePayloadBuilder;
use Prisma\Sif\Service\InvoiceDocumentAccessService;
use Prisma\Sif\Service\PrivateDocumentStore;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class HistoricalInvoicePrivateDocumentAccessTest
{
    public function testAccessUsesPrivateStorageReferenceInsteadOfLegacyPath(): void
    {
        $db = TestDatabase::fresh();
        $base = sys_get_temp_dir() . '/sif-uc011-access-' . bin2hex(random_bytes(6));
        $sourceDir = $base . '-source';
        $privateRoot = $base . '-private';
        mkdir($sourceDir, 0700, true);
        mkdir($privateRoot, 0700, true);

        try {
            $source = $sourceDir . '/original.pdf';
            $bytes = '%PDF-1.4 verified historical original';
            file_put_contents($source, $bytes);
            $hash = hash('sha256', $bytes);

            $invoice = (new HistoricalInvoiceMigrationService(
                new TransactionRunner($db),
                new HistoricalInvoicePayloadBuilder(),
                new HistoricalInvoiceMigrationRepository(new UuidGenerator())
            ))->importHistoricalInvoice([
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
                'document' => [
                    'type' => 'PDF',
                    'path' => '/legacy/not-readable-from-private-root/A2024-000123.pdf',
                    'hash' => $hash,
                ],
            ]);

            $custody = (new HistoricalInvoiceDocumentCustodyService(
                new TransactionRunner($db),
                $privateRoot
            ))->custody(
                $invoice['uuid_factura'],
                'PDF',
                $source,
                ['actor_id' => 'test-custody']
            );

            $policy = new class implements DocumentAuthorizationPolicyInterface {
                public function canDownload(
                    array $actor,
                    array $invoice,
                    array $relations,
                    array $document
                ): bool {
                    return true;
                }
            };

            $download = (new InvoiceDocumentAccessService(
                $db,
                new DocumentAccessRepository(),
                new InvoiceReadRepository(),
                $policy,
                new PrivateDocumentStore($privateRoot),
                new FiscalDocumentAccessRepository(new UuidGenerator())
            ))->download([
                'actor_type' => 'HUMAN',
                'actor_id' => 'test-reader',
                'roles' => ['ADMIN'],
                'source_channel' => 'INTERNAL_API',
                'request_id' => 'req-private-doc',
                'correlation_id' => 'corr-private-doc',
            ], (int) $custody['document_id']);

            Assert::same(true, $download['ok']);
            Assert::same(true, $download['document']['private_storage']);
            Assert::same($bytes, $download['bytes']);
            Assert::same(1, (int) $db->query(
                "SELECT COUNT(*) FROM fiscal_document_access
                 WHERE ACTION='DOWNLOAD' AND RESULT='ALLOWED'"
            )->fetchColumn());
        } finally {
            $this->cleanup($sourceDir);
            $this->cleanup($privateRoot);
        }
    }

    private function cleanup(string $root): void
    {
        if (!is_dir($root)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
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
