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
        $linkedInvoices = $this->lockOriginInvoiceRelations($db, $relations);

        foreach ($linkedInvoices as $linkedInvoice) {
            if ((string) $linkedInvoice['UUID_FACTURA'] === $uuidFactura) {
                continue;
            }

            if ((string) $linkedInvoice['SOURCE_CHANNEL'] === 'REDSYS'
                && (string) $linkedInvoice['ESTAT_FACTURA'] === 'ISSUED'
            ) {
                throw SifException::conflict(
                    'Invoice-before-payment origin already has an issued Redsys invoice'
                );
            }
        }

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

    public function findClaims(\PDO $db, array $relations, bool $forUpdate = false): array
    {
        $origins = $this->inscriptionOrigins($relations);
        $placeholders = implode(',', array_fill(0, count($origins), '?'));
        $sql =
            'SELECT SOURCE_ID, UUID_FACTURA, IDEMPOTENCY_KEY
             FROM invoice_before_payment_coverage
             WHERE SOURCE_TYPE = ?
               AND SOURCE_ID IN (' . $placeholders . ')
             ORDER BY SOURCE_ID';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge(['INSCRIPCIO'], $origins));
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function lockOriginInvoiceRelations(\PDO $db, array $relations): array
    {
        $origins = $this->inscriptionOrigins($relations);
        $placeholders = implode(',', array_fill(0, count($origins), '?'));

        $stmt = $db->prepare(
            "SELECT fr.ID, fr.SOURCE_ID, fr.UUID_FACTURA,
                    f.SOURCE_CHANNEL, f.ESTAT_FACTURA, f.EMESA_ABANS_COBRAMENT
             FROM fact_rels fr
             INNER JOIN factura f ON f.UUID_FACTURA = fr.UUID_FACTURA
             WHERE fr.SOURCE_TYPE = 'INSCRIPCIO'
               AND fr.RELATION_TYPE = 'ORIGIN'
               AND fr.SOURCE_ID IN (" . $placeholders . ")
             ORDER BY fr.SOURCE_ID, fr.ID
             FOR UPDATE"
        );
        $stmt->execute($origins);
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
