<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Contract\FiscalDocumentRendererInterface;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Repository\DocumentJobRepository;
use Prisma\Sif\Repository\DocumentRepository;
use Prisma\Sif\Service\FiscalDocumentJobProcessor;
use Prisma\Sif\Service\InvoiceBeforePaymentDocumentQueueService;
use Prisma\Sif\Service\PrivateDocumentWriter;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class FiscalDocumentJobProcessorTest
{
    public function testProcessesPendingPdfIntoReadyDocumentAndCompletesJob(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'TEST|DOCUMENT-WORKER|SUCCESS',
            ])
        );

        (new InvoiceBeforePaymentDocumentQueueService(
            new TransactionRunner($db),
            new DocumentJobRepository(),
            'uc004-fiscal-pdf-v1'
        ))->ensurePdf(
            $invoice['uuid_factura'],
            'INTRANET|FACTURA_ABANS_COBRAR|REF:WORKER-SUCCESS'
        );

        $root = $this->temporaryDirectory();

        try {
            $renderer = new class implements FiscalDocumentRendererInterface {
                public function render(\PDO $db, array $job): array
                {
                    return [
                        'contents' => '%PDF-1.4 rendered-for-' . $job['UUID_FACTURA'],
                        'extension' => 'pdf',
                    ];
                }
            };

            $processor = new FiscalDocumentJobProcessor(
                $db,
                new TransactionRunner($db),
                new DocumentJobRepository(),
                $renderer,
                new PrivateDocumentWriter($root),
                new DocumentRepository()
            );

            $result = $processor->processNext();

            Assert::same(true, $result['ok']);
            Assert::same('COMPLETED', $result['status']);
            Assert::same($invoice['uuid_factura'], $result['uuid_factura']);
            Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM document_job')->fetchColumn());
            Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_documents')->fetchColumn());

            $job = $db->query(
                'SELECT STATUS, ATTEMPTS, FACTURA_DOCUMENT_ID, STORAGE_KEY, OUTPUT_HASH
                 FROM document_job'
            )->fetch(\PDO::FETCH_ASSOC);
            $document = $db->query(
                'SELECT ID, UUID_FACTURA, TIPUS, PATH_FITXER, HASH_FITXER, ESTAT
                 FROM factura_documents'
            )->fetch(\PDO::FETCH_ASSOC);

            Assert::same('COMPLETED', $job['STATUS']);
            Assert::same(1, (int) $job['ATTEMPTS']);
            Assert::same((int) $document['ID'], (int) $job['FACTURA_DOCUMENT_ID']);
            Assert::same('READY', $document['ESTAT']);
            Assert::same('PDF', $document['TIPUS']);
            Assert::same($invoice['uuid_factura'], $document['UUID_FACTURA']);
            Assert::same($document['PATH_FITXER'], $job['STORAGE_KEY']);
            Assert::same($document['HASH_FITXER'], $job['OUTPUT_HASH']);
            Assert::same(true, is_file($root . '/' . $document['PATH_FITXER']));
            Assert::same(null, $processor->processNext());
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testRecoversStaleProcessingLeaseAndCompletesSameJob(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'TEST|DOCUMENT-WORKER|STALE',
            ])
        );

        $queued = (new InvoiceBeforePaymentDocumentQueueService(
            new TransactionRunner($db),
            new DocumentJobRepository(),
            'uc004-fiscal-pdf-v1'
        ))->ensurePdf(
            $invoice['uuid_factura'],
            'INTRANET|FACTURA_ABANS_COBRAR|REF:WORKER-STALE'
        );

        $db->prepare(
            "UPDATE document_job
             SET STATUS = 'PROCESSING', ATTEMPTS = 1, LOCKED_AT = ?
             WHERE ID = ?"
        )->execute(['2026-01-01 00:00:00.000000', $queued['document_job_id']]);

        $root = $this->temporaryDirectory();

        try {
            $renderer = new class implements FiscalDocumentRendererInterface {
                public function render(\PDO $db, array $job): array
                {
                    return [
                        'contents' => '%PDF-1.4 recovered-' . $job['UUID_JOB'],
                        'extension' => 'pdf',
                    ];
                }
            };

            $processor = new FiscalDocumentJobProcessor(
                $db,
                new TransactionRunner($db),
                new DocumentJobRepository(),
                $renderer,
                new PrivateDocumentWriter($root),
                new DocumentRepository(),
                1,
                10,
                60
            );

            $result = $processor->processNext();

            Assert::same(true, $result['ok']);
            Assert::same('COMPLETED', $result['status']);

            $job = $db->query(
                'SELECT STATUS, ATTEMPTS, LAST_ERROR, FACTURA_DOCUMENT_ID
                 FROM document_job'
            )->fetch(\PDO::FETCH_ASSOC);

            Assert::same('COMPLETED', $job['STATUS']);
            Assert::same(2, (int) $job['ATTEMPTS']);
            Assert::same(null, $job['LAST_ERROR']);
            Assert::same(true, (int) $job['FACTURA_DOCUMENT_ID'] > 0);
            Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_documents')->fetchColumn());
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testRendererFailureSchedulesRetryWithoutTouchingInvoice(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'TEST|DOCUMENT-WORKER|FAIL',
            ])
        );

        (new InvoiceBeforePaymentDocumentQueueService(
            new TransactionRunner($db),
            new DocumentJobRepository(),
            'uc004-fiscal-pdf-v1'
        ))->ensurePdf(
            $invoice['uuid_factura'],
            'INTRANET|FACTURA_ABANS_COBRAR|REF:WORKER-FAIL'
        );

        $root = $this->temporaryDirectory();

        try {
            $renderer = new class implements FiscalDocumentRendererInterface {
                public function render(\PDO $db, array $job): array
                {
                    throw new \RuntimeException('synthetic renderer failure');
                }
            };

            $processor = new FiscalDocumentJobProcessor(
                $db,
                new TransactionRunner($db),
                new DocumentJobRepository(),
                $renderer,
                new PrivateDocumentWriter($root),
                new DocumentRepository(),
                1,
                10
            );

            $result = $processor->processNext();

            Assert::same(false, $result['ok']);
            Assert::same('RETRY', $result['status']);
            Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
            Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura_documents')->fetchColumn());

            $job = $db->query(
                'SELECT STATUS, ATTEMPTS, NEXT_ATTEMPT_AT, LAST_ERROR
                 FROM document_job'
            )->fetch(\PDO::FETCH_ASSOC);

            Assert::same('RETRY', $job['STATUS']);
            Assert::same(1, (int) $job['ATTEMPTS']);
            Assert::same(true, $job['NEXT_ATTEMPT_AT'] !== null);
            Assert::stringContainsString('synthetic renderer failure', (string) $job['LAST_ERROR']);
        } finally {
            $this->removeDirectory($root);
        }
    }

    private function temporaryDirectory(): string
    {
        $root = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'sif-document-worker-'
            . bin2hex(random_bytes(8));

        if (!mkdir($root, 0700, true) && !is_dir($root)) {
            throw new \RuntimeException('Could not create test document directory');
        }

        return $root;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $candidate = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($candidate) && !is_link($candidate)) {
                $this->removeDirectory($candidate);
            } else {
                @unlink($candidate);
            }
        }

        @rmdir($path);
    }
}
