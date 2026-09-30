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
        $marker = "\nSIF {$numVisible} {$estatCobrament} {$uuidFactura}";

        $legacyDb->prepare(
            'UPDATE inscripcions
             SET FACTURA_RELACIONADA = COALESCE(FACTURA_RELACIONADA, ?),
                 OBSERVACIONS = CASE
                     WHEN LOCATE(?, COALESCE(OBSERVACIONS, \'\')) > 0 THEN OBSERVACIONS
                     ELSE CONCAT(COALESCE(OBSERVACIONS, \'\'), ?)
                 END
             WHERE ID = ?'
        )->execute([
            $facturaRelacionada,
            $uuidFactura,
            $marker,
            $idInsc,
        ]);
    }

    public function syncPackFullPayment(
        \PDO $legacyDb,
        int $idInsc,
        string $movementDate
    ): void {
        $legacyDb->prepare(
            "UPDATE inscripcions
             SET PAGAMENT = A_PAGAR,
                 `DATA PAG` = CASE
                     WHEN `DATA PAG` IS NULL OR `DATA PAG` = '' THEN ?
                     ELSE `DATA PAG`
                 END,
                 `INSC CURS` = CASE
                     WHEN `INSC CURS` = 'M' THEN '1'
                     ELSE `INSC CURS`
                 END
             WHERE ID = ? AND TIPUS_INSC = 'P'"
        )->execute([$movementDate, $idInsc]);
    }
}
