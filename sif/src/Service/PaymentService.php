<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Contract\PayloadIdempotencyValidatorInterface;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Repository\PaymentRepository;

final class PaymentService
{
    public function __construct(
        private TransactionRunner $transactions,
        private PaymentPayloadValidator $validator,
        private PaymentRepository $payments,
        private ?PayloadIdempotencyValidatorInterface $idempotency = null
    ) {
        $this->idempotency ??= new PayloadIdempotencyValidator();
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

            return $this->reusePaymentAfterDuplicateKey($payload);
        }
    }

    private function createOrReusePayment(array $payload): array
    {
        return $this->transactions->run(function (\PDO $db) use ($payload): array {
            $existing = $this->payments->findByIdempotencyKey($db, $payload['idempotency_key'], true);
            if ($existing !== null) {
                $this->assertSamePayload($payload, $existing);
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

    private function reusePaymentAfterDuplicateKey(array $payload): array
    {
        return $this->transactions->run(function (\PDO $db) use ($payload): array {
            $existing = $this->payments->findByIdempotencyKey($db, $payload['idempotency_key'], true);

            if ($existing === null) {
                throw new \RuntimeException('Duplicate key detected, but existing payment could not be loaded.');
            }

            $this->assertSamePayload($payload, $existing);
            return $this->existingResult($existing);
        });
    }

    private function assertSamePayload(array $payload, array $existing): void
    {
        $hash = (string) ($existing['PAYLOAD_HASH'] ?? '');
        if ((int) ($existing['PAYLOAD_HASH_VERSION'] ?? 1) === 1) {
            // Legacy rows were hashed with json_encode in incoming key order.
            // Never compare a legacy fingerprint as if it were canonical v2.
            try {
                $legacy = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw \Prisma\Sif\Exception\SifException::validation('Invalid payment payload encoding');
            }
            $this->idempotency->assertMatches($legacy, $hash);
            return;
        }
        if ((int) $existing['PAYLOAD_HASH_VERSION'] !== 2) {
            throw \Prisma\Sif\Exception\SifException::conflict('Unknown payment idempotency hash version');
        }
        $this->idempotency->assertMatches($payload, $hash);
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
