<?php

namespace Prisma\Sif\Repository;

final class CommercialOperationLineRepository
{
    public function findByOperationAndOrder(
        \PDO $db,
        string $uuidOperation,
        int $order,
        bool $forUpdate = false
    ): ?array {
        $sql = 'SELECT * FROM commercial_operation_line
                WHERE UUID_OPERATION = ? AND ORDRE = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$uuidOperation, $order]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function insert(\PDO $db, array $line): void
    {
        $db->prepare(
            'INSERT INTO commercial_operation_line (
                UUID_LINE, UUID_OPERATION, PARENT_UUID_LINE, LINE_TYPE, ORDRE,
                PRODUCT_TYPE, PRODUCT_CODE, PRODUCT_EDITION, PARTICIPANT_PARTY_KEY,
                DESCRIPTION, QUANTITY, UNIT_PRICE, GROSS_AMOUNT, DISCOUNT_AMOUNT,
                NET_AMOUNT, TAX_REGIME, TAX_RATE, TAX_AMOUNT,
                EXEMPTION_OR_NON_SUBJECT_REASON, PRICE_RULE_VERSION, SNAPSHOT_JSON, STATUS
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $line['uuid_line'],
            $line['uuid_operation'],
            $line['parent_uuid_line'],
            $line['line_type'],
            $line['order'],
            $line['product_type'],
            $line['product_code'],
            $line['product_edition'],
            $line['participant_party_key'],
            $line['description'],
            $line['quantity'],
            $line['unit_price'],
            $line['gross_amount'],
            $line['discount_amount'],
            $line['net_amount'],
            $line['tax_regime'],
            $line['tax_rate'],
            $line['tax_amount'],
            $line['exemption_reason'],
            $line['price_rule_version'],
            $line['snapshot_json'],
            $line['status'],
        ]);
    }
}
