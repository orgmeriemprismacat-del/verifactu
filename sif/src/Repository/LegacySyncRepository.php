<?php

namespace Prisma\Sif\Repository;

final class LegacySyncRepository
{
    public function syncInscripcioSummary(
        \PDO $legacyDb,
        int $idInsc,
        ?int $facturaRelacionada,
        string $uuidFactura,
        string $numVisible,
        string $estatCobrament
    ): void {
        $legacyDb->prepare(
            'UPDATE inscripcions
             SET FACTURA_RELACIONADA = COALESCE(FACTURA_RELACIONADA, ?),
                 OBSERVACIONS = CONCAT(COALESCE(OBSERVACIONS, \'\'), ?, ?, \' \', ?, \' \', ?)
             WHERE ID = ?'
        )->execute([
            $facturaRelacionada,
            "\nSIF ",
            $numVisible,
            $estatCobrament,
            $uuidFactura,
            $idInsc,
        ]);
    }
}
