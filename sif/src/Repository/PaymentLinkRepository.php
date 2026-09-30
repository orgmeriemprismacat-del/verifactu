<?php

namespace Prisma\Sif\Repository;

final class PaymentLinkRepository
{
    public function findByUuid(\PDO $db, string $uuidPaymentLink, bool $forUpdate = false): ?array
    {
        return $this->findOne(
            $db,
            'SELECT * FROM payment_link WHERE UUID_PAYMENT_LINK = ?',
            [$uuidPaymentLink],
            $forUpdate
        );
    }

    public function findByTokenHash(\PDO $db, string $tokenHash, bool $forUpdate = false): ?array
    {
        return $this->findOne(
            $db,
            'SELECT * FROM payment_link WHERE TOKEN_HASH = ?',
            [$tokenHash],
            $forUpdate
        );
    }

    public function insert(\PDO $db, array $link): array
    {
        $db->prepare(
            'INSERT INTO payment_link (
                UUID_PAYMENT_LINK, UUID_OPERATION, TOKEN_HASH, STATUS,
                PAYER_PARTY_KEY, EXPECTED_AMOUNT, CURRENCY, EXPIRES_AT,
                REVOKED_AT, REVOKED_BY, REVOKE_REASON, REPLACED_BY_UUID,
                LAST_ACCESSED_AT, CREATED_BY
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $link['uuid_payment_link'],
            $link['uuid_operation'],
            $link['token_hash'],
            $link['status'],
            $link['payer_party_key'],
            $link['expected_amount'],
            $link['currency'],
            $link['expires_at'],
            $link['revoked_at'],
            $link['revoked_by'],
            $link['revoke_reason'],
            $link['replaced_by_uuid'],
            $link['last_accessed_at'],
            $link['created_by'],
        ]);

        return [
            'uuid_payment_link' => $link['uuid_payment_link'],
            'uuid_operation' => $link['uuid_operation'],
            'status' => $link['status'],
        ];
    }

    public function markAccessed(\PDO $db, string $uuidPaymentLink, string $accessedAt): void
    {
        $db->prepare(
            'UPDATE payment_link SET LAST_ACCESSED_AT = ? WHERE UUID_PAYMENT_LINK = ?'
        )->execute([$accessedAt, $uuidPaymentLink]);
    }

    public function revoke(
        \PDO $db,
        string $uuidPaymentLink,
        string $revokedAt,
        ?string $revokedBy,
        string $reason,
        ?string $replacedByUuid = null
    ): bool {
        $stmt = $db->prepare(
            'UPDATE payment_link
             SET STATUS = ?, REVOKED_AT = ?, REVOKED_BY = ?, REVOKE_REASON = ?, REPLACED_BY_UUID = ?
             WHERE UUID_PAYMENT_LINK = ? AND STATUS = ?'
        );
        $stmt->execute([
            'REVOKED',
            $revokedAt,
            $revokedBy,
            $reason,
            $replacedByUuid,
            $uuidPaymentLink,
            'ACTIVE',
        ]);

        return $stmt->rowCount() === 1;
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
}
