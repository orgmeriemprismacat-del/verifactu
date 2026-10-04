<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\PayloadIdempotencyValidator;

final class CreditBalanceRepository
{
    public function __construct(private UuidGenerator $uuidGenerator)
    {
    }

    public function createCredit(\PDO $db, array $payload): array
    {
        $idempotencyKey = $this->optionalString($payload['idempotency_key'] ?? null);
        $payloadHash = $idempotencyKey === null
            ? null
            : (new PayloadIdempotencyValidator())->calculateHash($payload);

        if ($idempotencyKey !== null) {
            $existing = $this->findByIdempotencyKey($db, $idempotencyKey, true);
            if ($existing !== null) {
                if ((string) ($existing['PAYLOAD_HASH'] ?? '') !== (string) $payloadHash) {
                    throw SifException::conflict(
                        'Credit balance idempotency key already exists with different payload'
                    );
                }

                return [
                    'uuid_credit' => (string) $existing['UUID_CREDIT'],
                    'import_disponible' => (string) $existing['IMPORT_DISPONIBLE'],
                    'estat' => (string) $existing['ESTAT'],
                    'idempotency_reused' => true,
                ];
            }
        }

        $uuid = $this->uuidGenerator->generate();

        try {
            $db->prepare(
                'INSERT INTO credit_balance (
                    UUID_CREDIT, IDEMPOTENCY_KEY, PAYLOAD_HASH,
                    HOLDER_TYPE, HOLDER_ID, HOLDER_NIF_CIF, HOLDER_NOM_RAO,
                    IMPORT_ORIGINAL, IMPORT_DISPONIBLE, SOURCE_TYPE, SOURCE_ID,
                    UUID_FACTURA_ORIGEN, UUID_FACTURA_RECTIFICATIVA, REVIEW_AFTER, ESTAT
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'ACTIVE\')'
            )->execute([
                $uuid,
                $idempotencyKey,
                $payloadHash,
                $payload['holder_type'],
                $payload['holder_id'] ?? null,
                $payload['holder_nif_cif'] ?? null,
                $payload['holder_name'],
                $payload['amount'],
                $payload['amount'],
                $payload['source_type'],
                $payload['source_id'] ?? null,
                $payload['uuid_factura_origen'] ?? null,
                $payload['uuid_factura_rectificativa'] ?? null,
                $payload['review_after'] ?? null,
            ]);
        } catch (\PDOException $exception) {
            if ($idempotencyKey === null || (string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            $existing = $this->findByIdempotencyKey($db, $idempotencyKey, true);
            if ($existing === null) {
                throw $exception;
            }
            if ((string) ($existing['PAYLOAD_HASH'] ?? '') !== (string) $payloadHash) {
                throw SifException::conflict(
                    'Credit balance idempotency key already exists with different payload'
                );
            }

            return [
                'uuid_credit' => (string) $existing['UUID_CREDIT'],
                'import_disponible' => (string) $existing['IMPORT_DISPONIBLE'],
                'estat' => (string) $existing['ESTAT'],
                'idempotency_reused' => true,
            ];
        }

        return [
            'uuid_credit' => $uuid,
            'import_disponible' => $payload['amount'],
            'estat' => 'ACTIVE',
            'idempotency_reused' => false,
        ];
    }

    public function findByIdempotencyKey(
        \PDO $db,
        string $key,
        bool $forUpdate = false
    ): ?array {
        $sql = 'SELECT * FROM credit_balance WHERE IDEMPOTENCY_KEY = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([trim($key)]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByUuid(\PDO $db, string $uuidCredit, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM credit_balance WHERE UUID_CREDIT = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$uuidCredit]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function updateAvailableAmount(\PDO $db, string $uuidCredit, string $available, string $status): void
    {
        $db->prepare('UPDATE credit_balance SET IMPORT_DISPONIBLE = ?, ESTAT = ? WHERE UUID_CREDIT = ?')
            ->execute([$available, $status, $uuidCredit]);
    }

    public function invoiceOutstandingAmount(\PDO $db, string $uuidFactura): string
    {
        $totalStmt = $db->prepare('SELECT TOTAL FROM factura WHERE UUID_FACTURA = ?');
        $totalStmt->execute([$uuidFactura]);
        $total = $totalStmt->fetchColumn();

        if ($total === false) {
            throw new \RuntimeException('Invoice not found for outstanding amount.');
        }

        $charges = $this->sumAllocationsByMovementTypes($db, $uuidFactura, ['CHARGE', 'COMPENSATION']);
        $refunds = $this->sumAllocationsByMovementTypes($db, $uuidFactura, ['REFUND']);
        $outstanding = max(0, $this->cents((string) $total) - $this->cents($charges) + $this->cents($refunds));

        return $this->amount($outstanding);
    }

    private function sumAllocationsByMovementTypes(\PDO $db, string $uuidFactura, array $movementTypes): string
    {
        $marks = implode(', ', array_fill(0, count($movementTypes), '?'));
        $stmt = $db->prepare(
            "SELECT COALESCE(SUM(pa.IMPORT_ASSIGNAT), 0)
             FROM payment_allocation pa
             JOIN payment_transaction pt ON pt.UUID_PAYMENT = pa.UUID_PAYMENT
             WHERE pa.UUID_FACTURA = ? AND pt.TIPUS_MOVIMENT IN ({$marks})"
        );
        $stmt->execute(array_merge([$uuidFactura], $movementTypes));

        return number_format((float) $stmt->fetchColumn(), 2, '.', '');
    }

    private function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function cents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function amount(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
