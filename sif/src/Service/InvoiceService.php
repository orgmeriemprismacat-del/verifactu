<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Repository\FiscalSequenceRepository;
use Prisma\Sif\Repository\InvoiceRepository;

final class InvoiceService
{
    public function __construct(
        private TransactionRunner $transactions,
        private InvoicePayloadValidator $validator,
        private FiscalSequenceRepository $sequences,
        private InvoiceRepository $invoices
    ) {
    }

    public function issueInvoice(array $payload): array
    {
        $payload = $this->validator->validate($payload);

        try {
            return $this->createOrReuseInvoice($payload);
        } catch (\PDOException $exception) {
            if (!$this->isDuplicateKeyException($exception)) {
                throw $exception;
            }

            return $this->reuseInvoiceAfterDuplicateKey($payload['idempotency_key']);
        }
    }

    private function createOrReuseInvoice(array $payload): array
    {
        return $this->transactions->run(function (\PDO $db) use ($payload): array {
            $existing = $this->invoices->findByIdempotencyKey($db, $payload['idempotency_key'], true);
            if ($existing !== null) {
                return $this->existingResult($existing);
            }

            $year = (int) ($payload['year'] ?? date('Y'));
            $seq = $this->sequences->next($db, $payload['series'], $year);
            $chainState = $this->invoices->lockChainState($db);
            $created = $this->invoices->createInvoiceGraph($db, $payload, $seq, $chainState);

            return [
                'ok' => true,
                'idempotency_reused' => false,
                'uuid_factura' => $created['uuid_factura'],
                'num_visible' => $created['num_visible'],
            ];
        });
    }

    private function reuseInvoiceAfterDuplicateKey(string $idempotencyKey): array
    {
        return $this->transactions->run(function (\PDO $db) use ($idempotencyKey): array {
            $existing = $this->invoices->findByIdempotencyKey($db, $idempotencyKey, true);

            if ($existing === null) {
                throw new \RuntimeException('Duplicate key detected, but existing invoice could not be loaded.');
            }

            return $this->existingResult($existing);
        });
    }

    private function existingResult(array $existing): array
    {
        return [
            'ok' => true,
            'idempotency_reused' => true,
            'uuid_factura' => $existing['UUID_FACTURA'],
            'num_visible' => $existing['NUM_VISIBLE'],
        ];
    }

    private function isDuplicateKeyException(\PDOException $exception): bool
    {
        return (string) $exception->getCode() === '23000';
    }
}
