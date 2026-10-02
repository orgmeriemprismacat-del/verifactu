<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Contract\DocumentStorageWriterInterface;
use Prisma\Sif\Contract\FiscalDocumentRendererInterface;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\DocumentJobRepository;
use Prisma\Sif\Repository\DocumentRepository;

final class FiscalDocumentJobProcessor
{
    public function __construct(
        private \PDO $db,
        private TransactionRunner $transactions,
        private DocumentJobRepository $jobs,
        private FiscalDocumentRendererInterface $renderer,
        private DocumentStorageWriterInterface $storage,
        private DocumentRepository $documents,
        private int $baseRetrySeconds = 60,
        private int $maxRetrySeconds = 3600
    ) {
        $this->baseRetrySeconds = max(1, $this->baseRetrySeconds);
        $this->maxRetrySeconds = max($this->baseRetrySeconds, $this->maxRetrySeconds);
    }

    public function processNext(): ?array
    {
        $job = $this->transactions->run(
            fn (\PDO $db): ?array => $this->jobs->claimNext($db)
        );

        if ($job === null) {
            return null;
        }

        $jobId = (int) $job['ID'];

        try {
            $rendered = $this->renderer->render($this->db, $job);
            $contents = $rendered['contents'] ?? null;
            $extension = strtolower(trim((string) ($rendered['extension'] ?? '')));

            if (!is_string($contents) || $contents === '') {
                throw SifException::validation('Document renderer returned empty contents');
            }

            $expectedExtension = $this->extensionForType((string) $job['DOCUMENT_TYPE']);
            if ($extension !== $expectedExtension) {
                throw SifException::validation('Document renderer returned an unexpected extension');
            }

            $storageKey = $this->storageKey($job, $extension);
            $stored = $this->storage->writeVerified($storageKey, $contents);

            $completed = $this->transactions->run(function (\PDO $db) use (
                $job,
                $jobId,
                $contents,
                $stored
            ): array {
                $current = $this->jobs->findById($db, $jobId, true);
                if ($current === null
                    || strtoupper((string) $current['STATUS']) !== 'PROCESSING'
                    || (string) $current['UUID_JOB'] !== (string) $job['UUID_JOB']
                ) {
                    throw SifException::conflict('Document job ownership changed before completion');
                }

                $registered = $this->documents->registerDocument(
                    $db,
                    (string) $job['UUID_FACTURA'],
                    (string) $job['DOCUMENT_TYPE'],
                    (string) $stored['storage_key'],
                    $contents,
                    'READY'
                );

                if (!hash_equals((string) $stored['hash'], (string) $registered['hash'])) {
                    throw SifException::conflict(
                        'Stored document hash differs from registered document hash'
                    );
                }

                $row = $this->jobs->complete(
                    $db,
                    $jobId,
                    (int) $registered['document_id'],
                    (string) $stored['storage_key'],
                    (string) $stored['hash']
                );

                return [
                    'job' => $row,
                    'document' => $registered,
                ];
            });

            return [
                'ok' => true,
                'status' => 'COMPLETED',
                'job_id' => $jobId,
                'uuid_job' => (string) $job['UUID_JOB'],
                'uuid_factura' => (string) $job['UUID_FACTURA'],
                'document_id' => (int) $completed['document']['document_id'],
                'document_type' => (string) $job['DOCUMENT_TYPE'],
                'storage_key' => (string) $stored['storage_key'],
                'output_hash' => (string) $stored['hash'],
                'storage_reused' => (bool) $stored['reused'],
            ];
        } catch (\Throwable $exception) {
            $retrySeconds = $this->retrySeconds((int) ($job['ATTEMPTS'] ?? 1));

            try {
                $failed = $this->transactions->run(
                    fn (\PDO $db): array => $this->jobs->fail(
                        $db,
                        $jobId,
                        $this->safeError($exception),
                        $retrySeconds
                    )
                );
            } catch (\Throwable $failureRecordingException) {
                throw new \RuntimeException(
                    'Document processing failed and the job failure could not be recorded',
                    0,
                    $failureRecordingException
                );
            }

            return [
                'ok' => false,
                'status' => (string) $failed['STATUS'],
                'job_id' => $jobId,
                'uuid_job' => (string) $job['UUID_JOB'],
                'uuid_factura' => (string) $job['UUID_FACTURA'],
                'error_code' => 'DOCUMENT_PROCESSING_FAILED',
            ];
        }
    }

    private function storageKey(array $job, string $extension): string
    {
        $versionHash = substr(
            hash('sha256', (string) $job['GENERATOR_VERSION']),
            0,
            16
        );

        return 'factures/'
            . strtolower((string) $job['UUID_FACTURA'])
            . '/'
            . strtolower((string) $job['DOCUMENT_TYPE'])
            . '/'
            . $versionHash
            . '-'
            . strtolower((string) $job['UUID_JOB'])
            . '.'
            . $extension;
    }

    private function extensionForType(string $type): string
    {
        return match (strtoupper(trim($type))) {
            'PDF' => 'pdf',
            'XML' => 'xml',
            'QR' => 'png',
            default => throw SifException::validation('Unsupported document job type'),
        };
    }

    private function retrySeconds(int $attempt): int
    {
        $exponent = max(0, min(10, $attempt - 1));
        $seconds = $this->baseRetrySeconds * (2 ** $exponent);

        return min($this->maxRetrySeconds, $seconds);
    }

    private function safeError(\Throwable $exception): string
    {
        $class = get_class($exception);
        $message = preg_replace('/\s+/', ' ', trim($exception->getMessage()));
        if (!is_string($message) || $message === '') {
            $message = 'Document processing failed';
        }

        return mb_substr($class . ': ' . $message, 0, 4000, 'UTF-8');
    }
}
