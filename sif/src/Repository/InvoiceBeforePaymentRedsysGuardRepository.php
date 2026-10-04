<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class InvoiceBeforePaymentRedsysGuardRepository
{
    public function findBlockingCourseIntents(\PDO $db, array $relations, bool $forUpdate = false): array
    {
        $sourceIds = $this->inscriptionOrigins($relations);
        $placeholders = implode(',', array_fill(0, count($sourceIds), '?'));

        $sql =
            "SELECT
                i.UUID_INTENT,
                i.DS_ORDER,
                i.SOURCE_ID,
                i.EXPIRES_AT,
                n.STATUS AS NOTIFICATION_STATUS
             FROM redsys_payment_intent i
             LEFT JOIN redsys_notifications n
               ON n.DS_ORDER = i.DS_ORDER
             WHERE i.SOURCE_TYPE = 'CURS'
               AND i.SOURCE_ID IN ({$placeholders})
               AND (
                    n.ID IS NOT NULL
                    OR i.EXPIRES_AT IS NULL
                    OR i.EXPIRES_AT >= CURRENT_TIMESTAMP
               )
             ORDER BY i.SOURCE_ID, i.CREATED_AT";

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute(array_map('strval', $sourceIds));
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, static function (array $row): bool {
            $status = strtoupper(trim((string) ($row['NOTIFICATION_STATUS'] ?? '')));

            // A rejected Redsys notification is terminal and carries no money
            // that still needs to be reconciled. All other existing intents
            // remain blocking for invoice-before-payment.
            return $status !== 'ERROR';
        }));
    }

    public function assertNoExistingSifInvoices(\PDO $db, array $relations, bool $forUpdate = false): void
    {
        $sourceIds = $this->inscriptionOrigins($relations);
        $placeholders = implode(',', array_fill(0, count($sourceIds), '?'));

        $sql =
            "SELECT fr.SOURCE_ID, fr.UUID_FACTURA, f.NUM_VISIBLE, f.ESTAT_FACTURA
             FROM fact_rels fr
             INNER JOIN factura f ON f.UUID_FACTURA = fr.UUID_FACTURA
             WHERE fr.SOURCE_TYPE = 'INSCRIPCIO'
               AND fr.SOURCE_ID IN ({$placeholders})
             ORDER BY fr.SOURCE_ID, fr.UUID_FACTURA";

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute(array_map('strval', $sourceIds));
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (!is_array($rows) || $rows === []) {
            return;
        }

        $refs = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['NUM_VISIBLE'] ?? $row['UUID_FACTURA'] ?? '')),
            $rows
        ))));

        throw SifException::conflict(
            'Invoice-before-payment conflicts with an existing SIF invoice for one or more inscriptions'
            . ($refs === [] ? '' : ': ' . implode(',', $refs))
        );
    }

    public function assertNoBlockingCourseIntents(\PDO $db, array $relations, bool $forUpdate = false): void
    {
        $conflicts = $this->findBlockingCourseIntents($db, $relations, $forUpdate);
        if ($conflicts === []) {
            return;
        }

        $orders = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['DS_ORDER'] ?? '')),
            $conflicts
        ))));

        throw SifException::conflict(
            'Invoice-before-payment conflicts with an existing Redsys course intent'
            . ($orders === [] ? '' : ': ' . implode(',', $orders))
        );
    }

    private function inscriptionOrigins(array $relations): array
    {
        $ids = [];

        foreach ($relations as $relation) {
            if (!is_array($relation)) {
                continue;
            }

            if (strtoupper(trim((string) ($relation['source_type'] ?? ''))) !== 'INSCRIPCIO') {
                continue;
            }
            if (strtoupper(trim((string) ($relation['relation_type'] ?? 'ORIGIN'))) !== 'ORIGIN') {
                continue;
            }

            $raw = $relation['source_id'] ?? null;
            if (is_string($raw) && ctype_digit($raw)) {
                $raw = (int) $raw;
            }
            if (!is_int($raw) || $raw <= 0) {
                throw SifException::validation(
                    'Invoice-before-payment Redsys guard requires positive INSCRIPCIO source IDs'
                );
            }
            $ids[$raw] = true;
        }

        if ($ids === []) {
            throw SifException::validation(
                'Invoice-before-payment Redsys guard requires at least one INSCRIPCIO origin'
            );
        }

        $ids = array_keys($ids);
        sort($ids, SORT_NUMERIC);

        return $ids;
    }
}
