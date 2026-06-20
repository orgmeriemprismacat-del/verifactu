<?php

namespace Prisma\Sif\Repository;

final class RedsysPaymentIntentRepository
{
    public function findByDsOrder(\PDO $db, string $dsOrder, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM redsys_payment_intent WHERE DS_ORDER = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$dsOrder]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function insert(\PDO $db, array $intent): array
    {
        $db->prepare(
            'INSERT INTO redsys_payment_intent (
                UUID_INTENT, DS_ORDER, IDPAG, SOURCE_TYPE, SOURCE_ID,
                EXPECTED_AMOUNT, CURRENCY, TERMINAL, SNAPSHOT_JSON,
                STATUS, CREATED_BY, EXPIRES_AT
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $intent['uuid_intent'],
            $intent['ds_order'],
            $intent['idpag'],
            $intent['source_type'],
            $intent['source_id'],
            $intent['expected_amount'],
            $intent['currency'],
            $intent['terminal'],
            $intent['snapshot_json'],
            $intent['status'],
            $intent['created_by'],
            $intent['expires_at'],
        ]);

        return [
            'uuid_intent' => $intent['uuid_intent'],
            'ds_order' => $intent['ds_order'],
            'status' => $intent['status'],
            'idempotency_reused' => false,
        ];
    }
}
