<?php

namespace Prisma\Sif\Repository;

final class DiscountValidationRepository
{
    public function findByIdempotencyKey(\PDO $db, string $idempotencyKey, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM discount_validation WHERE IDEMPOTENCY_KEY = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$idempotencyKey]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByUuid(\PDO $db, string $uuidValidation, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM discount_validation WHERE UUID_VALIDATION = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$uuidValidation]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function insert(\PDO $db, array $validation): array
    {
        $db->prepare(
            'INSERT INTO discount_validation (
                UUID_VALIDATION, UUID_OPERATION, DISCOUNT_TYPE, SUBJECT_PARTY_KEY,
                STATUS, RULE_VERSION, RULE_SNAPSHOT_JSON, EVIDENCE_STORAGE_REF,
                EVIDENCE_HASH, REQUESTED_AT, VALIDATED_AT, VALIDATED_BY,
                REJECTION_REASON, RESULT_DISCOUNT_AMOUNT, FUTURE_ENTITLEMENT_REF,
                IDEMPOTENCY_KEY
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $validation['uuid_validation'],
            $validation['uuid_operation'],
            $validation['discount_type'],
            $validation['subject_party_key'],
            $validation['status'],
            $validation['rule_version'],
            $validation['rule_snapshot_json'],
            $validation['evidence_storage_ref'],
            $validation['evidence_hash'],
            $validation['requested_at'],
            $validation['validated_at'],
            $validation['validated_by'],
            $validation['rejection_reason'],
            $validation['result_discount_amount'],
            $validation['future_entitlement_ref'],
            $validation['idempotency_key'],
        ]);

        return [
            'uuid_validation' => $validation['uuid_validation'],
            'uuid_operation' => $validation['uuid_operation'],
            'status' => $validation['status'],
            'idempotency_reused' => false,
        ];
    }
}
