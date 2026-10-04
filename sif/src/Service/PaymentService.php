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

            return $this->reusePaymentAfterDuplicateConstraint($payload, $exception);
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

            $external = $this->payments->findByExternalReceipt($db, $payload, true);
            if ($external !== null) {
                $this->assertSameEconomicReceipt($db, $payload, $external);
                return $this->existingResult($external, true);
            }

            $created = $this->payments->createPayment($db, $payload);

            return [
                'ok' => true,
                'idempotency_reused' => false,
                'uuid_payment' => $created['uuid_payment'],
            ];
        });
    }

    private function reusePaymentAfterDuplicateConstraint(
        array $payload,
        \PDOException $original
    ): array {
        return $this->transactions->run(function (\PDO $db) use ($payload, $original): array {
            $existing = $this->payments->findByIdempotencyKey(
                $db,
                $payload['idempotency_key'],
                true
            );

            if ($existing !== null) {
                $this->assertSamePayload($payload, $existing);
                return $this->existingResult($existing);
            }

            $external = $this->payments->findByExternalReceipt($db, $payload, true);
            if ($external !== null) {
                $this->assertSameEconomicReceipt($db, $payload, $external);
                return $this->existingResult($external, true);
            }

            throw $original;
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

    private function assertSameEconomicReceipt(\PDO $db, array $payload, array $existing): void
    {
        if ((string) ($existing['TIPUS_MOVIMENT'] ?? '') !== (string) ($payload['movement_type'] ?? '')) {
            throw \Prisma\Sif\Exception\SifException::conflict(
                'External receipt already exists with a different movement type'
            );
        }

        if ($this->toCents((string) ($existing['IMPORT'] ?? '0'))
            !== $this->toCents((string) ($payload['amount'] ?? '0'))
        ) {
            throw \Prisma\Sif\Exception\SifException::conflict(
                'External receipt already exists with a different amount'
            );
        }

        $existingAllocations = $this->payments->findAllocations(
            $db,
            (string) ($existing['UUID_PAYMENT'] ?? '')
        );

        $expected = [];
        foreach ((array) ($payload['allocations'] ?? []) as $allocation) {
            if (!is_array($allocation)) {
                continue;
            }
            $expected[] = [
                'uuid_factura' => trim((string) ($allocation['uuid_factura'] ?? '')),
                'amount_cents' => $this->toCents((string) ($allocation['amount'] ?? '0')),
            ];
        }

        $actual = [];
        foreach ($existingAllocations as $allocation) {
            $actual[] = [
                'uuid_factura' => trim((string) ($allocation['UUID_FACTURA'] ?? '')),
                'amount_cents' => $this->toCents((string) ($allocation['IMPORT_ASSIGNAT'] ?? '0')),
            ];
        }

        usort($expected, static fn (array $a, array $b): int => ($a['uuid_factura'] <=> $b['uuid_factura'])
            ?: ($a['amount_cents'] <=> $b['amount_cents']));
        usort($actual, static fn (array $a, array $b): int => ($a['uuid_factura'] <=> $b['uuid_factura'])
            ?: ($a['amount_cents'] <=> $b['amount_cents']));

        if ($expected !== $actual) {
            throw \Prisma\Sif\Exception\SifException::conflict(
                'External receipt already exists with different invoice allocations'
            );
        }
    }

    private function toCents(string $amount): int
    {
        $normalized = str_replace(',', '.', trim($amount));
        $negative = str_starts_with($normalized, '-');
        if ($negative) {
            $normalized = substr($normalized, 1);
        }

        [$whole, $decimal] = array_pad(explode('.', $normalized, 2), 2, '0');
        $decimal = substr(str_pad($decimal, 2, '0'), 0, 2);
        $cents = ((int) $whole * 100) + (int) $decimal;

        return $negative ? -$cents : $cents;
    }

    private function existingResult(array $existing, bool $reconciledExternal = false): array
    {
        return [
            'ok' => true,
            'idempotency_reused' => true,
            'reconciled_existing' => $reconciledExternal,
            'uuid_payment' => $existing['UUID_PAYMENT'],
        ];
    }

    private function isDuplicateKeyException(\PDOException $exception): bool
    {
        return (string) $exception->getCode() === '23000';
    }
}
