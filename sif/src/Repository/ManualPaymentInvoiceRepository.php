<?php

namespace Prisma\Sif\Repository;

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
}
