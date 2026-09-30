<?php

namespace Prisma\Sif\Repository;

final class LegacyPrismaStudentHistoryRepository
{
    public function findByDocument(\PDO $db, string $document): array
    {
        $document = trim($document);
        if ($document === '') {
            throw new \InvalidArgumentException('Student document is required');
        }

        $stmt = $db->prepare(
            'SELECT ID, A_PAGAR, PAGAMENT, OBSERVACIONS, GENERAT, IDPAG, FACTURA_RELACIONADA, `INSC CURS` AS INSC_CURS
             FROM inscripcions
             WHERE DNI = ?
             ORDER BY ID DESC'
        );
        $stmt->execute([$document]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
