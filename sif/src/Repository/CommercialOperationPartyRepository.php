<?php

namespace Prisma\Sif\Repository;

final class CommercialOperationPartyRepository
{
    public function find(
        \PDO $db,
        string $uuidOperation,
        string $partyKey,
        string $partyRole,
        bool $forUpdate = false
    ): ?array {
        $sql = 'SELECT * FROM commercial_operation_party
                WHERE UUID_OPERATION = ? AND PARTY_KEY = ? AND PARTY_ROLE = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$uuidOperation, $partyKey, $partyRole]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function insert(\PDO $db, array $party): void
    {
        $db->prepare(
            'INSERT INTO commercial_operation_party (
                UUID_OPERATION, PARTY_KEY, PARTY_ROLE, LEGACY_PERSON_ID,
                NIF_CIF, NOM_RAO, EMAIL, PRODUCT_CODE, PRODUCT_EDITION,
                LINE_AMOUNT, SNAPSHOT_JSON
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $party['uuid_operation'],
            $party['party_key'],
            $party['party_role'],
            $party['legacy_person_id'] ?? null,
            $party['nif_cif'] ?? null,
            $party['nom_rao'],
            $party['email'] ?? null,
            $party['product_code'] ?? null,
            $party['product_edition'] ?? null,
            $party['line_amount'] ?? null,
            $party['snapshot_json'],
        ]);
    }
}
