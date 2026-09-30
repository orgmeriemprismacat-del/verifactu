<?php

namespace Prisma\Sif\Repository;

final class StudentProfileReadRepository
{
    public function findByEnrollmentId(\PDO $legacyDb, int $idInsc): ?array
    {
        $stmt = $legacyDb->prepare(
            'SELECT ID, NOM, COGNOMS, CORREU, DNI, TELEFON, ADRECA,
                    Codi_Postal AS CODI_POSTAL, Poblacio AS POBLACIO,
                    PERFIL, Titulacio AS TITULACIO
             FROM inscripcions
             WHERE ID = ?
             LIMIT 1'
        );
        $stmt->execute([$idInsc]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $row['ID'] = (int) $row['ID'];
        return $row;
    }
}
