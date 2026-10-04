<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class ManualPaymentInvoiceRepository
{
    public function findByUuid(\PDO $db, string $uuidFactura, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM factura WHERE UUID_FACTURA = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$uuidFactura]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByNumVisible(\PDO $db, string $numVisible, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM factura WHERE NUM_VISIBLE = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$numVisible]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }
    public function findByLegacyFacturaRelacionada(
        \PDO $db,
        int $facturaRelacionada,
        bool $forUpdate = false
    ): ?array {
        if ($facturaRelacionada <= 0) {
            throw SifException::validation('Invalid legacy related invoice id');
        }

        $sql =
            'SELECT DISTINCT f.*
             FROM factura f
             INNER JOIN fact_rels fr ON fr.UUID_FACTURA = f.UUID_FACTURA
             WHERE fr.FACTURA_RELACIONADA = ?
               AND fr.RELATION_TYPE = \'ORIGIN\'
             ORDER BY f.ID
             LIMIT 2';
        if ($forUpdate) {
            // MySQL does not allow FOR UPDATE after LIMIT in every query shape
            // consistently across versions when DISTINCT is involved. Lock the
            // unique invoice row after resolving it below.
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$facturaRelacionada]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if ($rows === []) {
            return null;
        }
        if (count($rows) !== 1) {
            throw SifException::conflict(
                'Legacy related invoice maps to more than one SIF invoice'
            );
        }

        $invoice = $rows[0];
        if ($forUpdate) {
            $locked = $this->findByUuid(
                $db,
                (string) $invoice['UUID_FACTURA'],
                true
            );
            if ($locked === null) {
                throw SifException::conflict(
                    'Resolved SIF invoice disappeared during legacy relation lock'
                );
            }
            return $locked;
        }

        return $invoice;
    }

}
