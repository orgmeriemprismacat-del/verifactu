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

    /**
     * Returns every still-unnotified intent for one commercial source.
     *
     * The caller must serialize creation when this is used as an idempotency
     * guard; otherwise two concurrent requests could both observe zero rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findUnnotifiedBySource(
        \PDO $db,
        string $sourceType,
        string $sourceId
    ): array {
        $stmt = $db->prepare(
            "SELECT i.*
             FROM redsys_payment_intent i
             LEFT JOIN redsys_notifications n ON n.DS_ORDER = i.DS_ORDER
             WHERE i.SOURCE_TYPE = ?
               AND i.SOURCE_ID = ?
               AND n.ID IS NULL
             LIMIT 2"
        );
        $stmt->execute([$sourceType, $sourceId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function hasValidatedNotificationForSource(
        \PDO $db,
        string $sourceType,
        string $sourceId
    ): bool {
        $stmt = $db->prepare(
            "SELECT 1
             FROM redsys_payment_intent i
             INNER JOIN redsys_notifications n ON n.DS_ORDER = i.DS_ORDER
             WHERE i.SOURCE_TYPE = ?
               AND i.SOURCE_ID = ?
               AND n.STATUS = 'VALIDATED'
             LIMIT 1"
        );
        $stmt->execute([$sourceType, $sourceId]);

        return $stmt->fetchColumn() !== false;
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
