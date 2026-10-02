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

    public function testRecoveredJobRejectsCompletionAndFailureFromOlderAttempt(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'TEST|DOCUMENT-WORKER|LEASE',
            ])
        );

        (new InvoiceBeforePaymentDocumentQueueService(
            new TransactionRunner($db),
            new DocumentJobRepository(),
            'uc004-fiscal-pdf-v1'
        ))->ensurePdf(
            $invoice['uuid_factura'],
            'INTRANET|FACTURA_ABANS_COBRAR|REF:WORKER-LEASE'
        );

        $jobs = new DocumentJobRepository();
        $firstNow = new \DateTimeImmutable('2026-10-02 10:00:00', new \DateTimeZone('Europe/Madrid'));
        $first = (new TransactionRunner($db))->run(
            fn (\PDO $connection): ?array => $jobs->claimNext($connection, $firstNow)
        );

        Assert::same(1, (int) $first['ATTEMPTS']);
        Assert::same('PROCESSING', $first['STATUS']);

        $secondNow = $firstNow->modify('+20 minutes');
        $recovered = (new TransactionRunner($db))->run(
            fn (\PDO $connection): int => $jobs->recoverStaleLocks(
                $connection,
                $secondNow,
                60
            )
        );
        Assert::same(1, $recovered);

        $second = (new TransactionRunner($db))->run(
            fn (\PDO $connection): ?array => $jobs->claimNext($connection, $secondNow)
        );

        Assert::same(2, (int) $second['ATTEMPTS']);
        Assert::same('PROCESSING', $second['STATUS']);
        Assert::same($first['UUID_JOB'], $second['UUID_JOB']);

        $document = (new DocumentRepository())->registerDocument(
            $db,
            $invoice['uuid_factura'],
            'PDF',
            'factures/lease-test.pdf',
            '%PDF-1.4 lease-test',
            'READY'
        );

        Assert::throws(\Prisma\Sif\Exception\SifException::class, function () use (
            $db,
            $jobs,
            $first,
            $document
        ): void {
            $jobs->complete(
                $db,
                (int) $first['ID'],
                (int) $first['ATTEMPTS'],
                (int) $document['document_id'],
                'factures/lease-test.pdf',
                (string) $document['hash']
            );
        }, 409);

        Assert::throws(\Prisma\Sif\Exception\SifException::class, function () use (
            $db,
            $jobs,
            $first
        ): void {
            $jobs->fail(
                $db,
                (int) $first['ID'],
                (int) $first['ATTEMPTS'],
                'stale worker must not mutate the current lease',
                60
            );
        }, 409);

        $current = $jobs->findById($db, (int) $second['ID']);
        Assert::same('PROCESSING', $current['STATUS']);
        Assert::same(2, (int) $current['ATTEMPTS']);

        $failed = $jobs->fail(
            $db,
            (int) $second['ID'],
            (int) $second['ATTEMPTS'],
            'current worker retry',
            60,
            $secondNow
        );
        Assert::same('RETRY', $failed['STATUS']);
        Assert::same(2, (int) $failed['ATTEMPTS']);
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
