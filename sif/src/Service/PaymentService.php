<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Repository\PaymentRepository;

final class PaymentService
{
    public function __construct(
        private TransactionRunner $transactions,
        private PaymentPayloadValidator $validator,
        private PaymentRepository $payments
    ) {
    }

    public function registerPayment(array $payload): array
    {
        $payload = $this->validator->validate($payload);

        try {
            return $this->createOrReusePayment($payload);
        } catch (\PDOException $exception) {
            if (!$this->isDuplicateKeyException($exception)) {
                throw $exception;
            }

            return $this->reusePaymentAfterDuplicateKey($payload['idempotency_key']);
        }
    }

    private function createOrReusePayment(array $payload): array
    {
        return $this->transactions->run(function (\PDO $db) use ($payload): array {
            $existing = $this->payments->findByIdempotencyKey($db, $payload['idempotency_key'], true);
            if ($existing !== null) {
                return $this->existingResult($existing);
            }

            $created = $this->payments->createPayment($db, $payload);

            return [
                'ok' => true,
                'idempotency_reused' => false,
                'uuid_payment' => $created['uuid_payment'],
            ];
        });
    }

    private function reusePaymentAfterDuplicateKey(string $idempotencyKey): array
    {
        return $this->transactions->run(function (\PDO $db) use ($idempotencyKey): array {
            $existing = $this->payments->findByIdempotencyKey($db, $idempotencyKey, true);

            if ($existing === null) {
                throw new \RuntimeException('Duplicate key detected, but existing payment could not be loaded.');
            }

            return $this->existingResult($existing);
        });
    }

    private function existingResult(array $existing): array
    {
        return [
            'ok' => true,
            'idempotency_reused' => true,
            'uuid_payment' => $existing['UUID_PAYMENT'],
        ];
    }

    private function isDuplicateKeyException(\PDOException $exception): bool
    {
        return (string) $exception->getCode() === '23000';
    }
}
