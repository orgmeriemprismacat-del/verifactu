<?php

namespace Prisma\Sif\Repository;

final class ManualPaymentInvoiceRepository
{
    public function findByUuid(\PDO $db, string $uuidFactura): ?array
    {
        $stmt = $db->prepare('SELECT * FROM factura WHERE UUID_FACTURA = ?');
        $stmt->execute([$uuidFactura]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByNumVisible(\PDO $db, string $numVisible): ?array
    {
        $stmt = $db->prepare('SELECT * FROM factura WHERE NUM_VISIBLE = ?');
        $stmt->execute([$numVisible]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
