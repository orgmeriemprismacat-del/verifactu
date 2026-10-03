<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class CommercialOperationRepository
{
    public function findByIdempotencyKey(\PDO $db, string $idempotencyKey, bool $forUpdate = false): ?array
    {
        return $this->findOne(
            $db,
            'SELECT * FROM commercial_operation WHERE IDEMPOTENCY_KEY = ?',
            [$idempotencyKey],
            $forUpdate
        );
    }

    public function findByUuid(\PDO $db, string $uuidOperation, bool $forUpdate = false): ?array
    {
        return $this->findOne(
            $db,
            'SELECT * FROM commercial_operation WHERE UUID_OPERATION = ?',
            [$uuidOperation],
            $forUpdate
        );
    }

    public function insert(\PDO $db, array $operation): array
    {
        $db->prepare(
            'INSERT INTO commercial_operation (
                UUID_OPERATION, IDEMPOTENCY_KEY, OPERATION_TYPE, SOURCE_CHANNEL,
                SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE, PRODUCT_EDITION,
                CLASSIFICATION, CLASSIFICATION_REASON, STATUS, CURRENCY,
                GROSS_AMOUNT, DISCOUNT_AMOUNT, NET_AMOUNT,
                PRICE_SNAPSHOT_JSON, CAPACITY_SNAPSHOT_JSON, TAX_SNAPSHOT_JSON,
                EXPIRES_AT, CREATED_BY
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $operation['uuid_operation'],
            $operation['idempotency_key'],
            $operation['operation_type'],
            $operation['source_channel'],
            $operation['source_type'],
            $operation['source_id'],
            $operation['product_type'],
            $operation['product_code'],
            $operation['product_edition'],
            $operation['classification'],
            $operation['classification_reason'],
            $operation['status'],
            $operation['currency'],
            $operation['gross_amount'],
            $operation['discount_amount'],
            $operation['net_amount'],
            $operation['price_snapshot_json'],
            $operation['capacity_snapshot_json'],
            $operation['tax_snapshot_json'],
            $operation['expires_at'],
            $operation['created_by'],
        ]);

        return [
            'uuid_operation' => $operation['uuid_operation'],
            'idempotency_key' => $operation['idempotency_key'],
            'status' => $operation['status'],
            'idempotency_reused' => false,
        ];
    }

    public function linkIntent(
        \PDO $db,
        string $uuidOperation,
        string $uuidIntent,
        ?string $expectedCurrentIntent = null
    ): void {
        $operation = $this->findByUuid($db, $uuidOperation, true);
        if ($operation === null) {
            throw SifException::notFound('Commercial operation not found');
        }

        $current = $this->nullableString($operation['UUID_INTENT'] ?? null);
        if ($current === $uuidIntent) {
            return;
        }
        if ($current !== $expectedCurrentIntent) {
            throw SifException::conflict('Commercial operation intent link changed concurrently');
        }

        $stmt = $db->prepare(
            'UPDATE commercial_operation
             SET UUID_INTENT = ?
             WHERE UUID_OPERATION = ?
               AND ' . ($expectedCurrentIntent === null ? 'UUID_INTENT IS NULL' : 'UUID_INTENT = ?')
        );
        $params = [$uuidIntent, $uuidOperation];
        if ($expectedCurrentIntent !== null) {
            $params[] = $expectedCurrentIntent;
        }
        $stmt->execute($params);

        if ($stmt->rowCount() !== 1) {
            throw SifException::conflict('Commercial operation intent link could not be updated');
        }
    }

    public function updateStatus(\PDO $db, string $uuidOperation, string $status): void
    {
        $stmt = $db->prepare(
            'UPDATE commercial_operation
             SET STATUS = ?, UPDATED_AT = CURRENT_TIMESTAMP
             WHERE UUID_OPERATION = ?'
        );
        $stmt->execute([$status, $uuidOperation]);

        if ($stmt->rowCount() > 1) {
            throw new \RuntimeException('Commercial operation status update affected multiple rows');
        }
    }

    private function findOne(\PDO $db, string $sql, array $params, bool $forUpdate): ?array
    {
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
