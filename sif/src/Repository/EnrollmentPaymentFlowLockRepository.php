<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class EnrollmentPaymentFlowLockRepository
{
    public function lockInscription(\PDO $db, int $sourceId): void
    {
        if (!$db->inTransaction()) {
            throw new \LogicException('Enrollment payment flow lock requires an active transaction.');
        }
        if ($sourceId < 1) {
            throw SifException::validation('Enrollment payment flow lock requires a positive source ID.');
        }

        // INSERT IGNORE is intentional: the first transaction creates the
        // coordination row; concurrent transactions wait on the same PK.
        $insert = $db->prepare(
            'INSERT IGNORE INTO enrollment_payment_flow_lock (SOURCE_TYPE, SOURCE_ID)
             VALUES (?, ?)'
        );
        $insert->execute(['INSCRIPCIO', $sourceId]);

        $stmt = $db->prepare(
            'SELECT SOURCE_ID
             FROM enrollment_payment_flow_lock
             WHERE SOURCE_TYPE = ? AND SOURCE_ID = ?
             FOR UPDATE'
        );
        $stmt->execute(['INSCRIPCIO', $sourceId]);

        if ($stmt->fetchColumn() === false) {
            throw new \RuntimeException('Could not acquire enrollment payment flow lock.');
        }
    }

    public function lockRelations(\PDO $db, array $relations): void
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
            if (!is_int($raw) || $raw < 1) {
                throw SifException::validation(
                    'Enrollment payment flow lock requires positive INSCRIPCIO origin IDs.'
                );
            }
            $ids[$raw] = true;
        }

        if ($ids === []) {
            throw SifException::validation(
                'Enrollment payment flow lock requires at least one INSCRIPCIO origin.'
            );
        }

        $ids = array_keys($ids);
        sort($ids, SORT_NUMERIC);

        // Always lock multi-enrollment operations in numeric order to avoid
        // deadlocks between overlapping UC-021 selections.
        foreach ($ids as $sourceId) {
            $this->lockInscription($db, (int) $sourceId);
        }
    }
}
