<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class InvoiceBeforePaymentCoverageRepository
{
    public function claim(
        \PDO $db,
        array $relations,
        string $uuidFactura,
        string $idempotencyKey
    ): void {
        $origins = $this->inscriptionOrigins($relations);

        $stmt = $db->prepare(
            'INSERT INTO invoice_before_payment_coverage (
                SOURCE_TYPE, SOURCE_ID, UUID_FACTURA, IDEMPOTENCY_KEY
            ) VALUES (?, ?, ?, ?)'
        );

        foreach ($origins as $sourceId) {
            $stmt->execute([
                'INSCRIPCIO',
                $sourceId,
                $uuidFactura,
                $idempotencyKey,
            ]);
        }
    }

    public function findClaims(\PDO $db, array $relations): array
    {
        $origins = $this->inscriptionOrigins($relations);
        $placeholders = implode(',', array_fill(0, count($origins), '?'));

        $stmt = $db->prepare(
            'SELECT SOURCE_ID, UUID_FACTURA, IDEMPOTENCY_KEY
             FROM invoice_before_payment_coverage
             WHERE SOURCE_TYPE = ?
               AND SOURCE_ID IN (' . $placeholders . ')
             ORDER BY SOURCE_ID'
        );
        $stmt->execute(array_merge(['INSCRIPCIO'], $origins));
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    private function inscriptionOrigins(array $relations): array
    {
        $origins = [];

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

            $sourceId = $relation['source_id'] ?? null;
            if (is_string($sourceId) && ctype_digit($sourceId)) {
                $sourceId = (int) $sourceId;
            }

            if (!is_int($sourceId) || $sourceId <= 0) {
                throw SifException::validation(
                    'Invoice before payment coverage requires a positive INSCRIPCIO source_id'
                );
            }

            $origins[] = $sourceId;
        }

        if ($origins === []) {
            throw SifException::validation(
                'Invoice before payment coverage requires at least one INSCRIPCIO/ORIGIN relation'
            );
        }

        return $origins;
    }
}
