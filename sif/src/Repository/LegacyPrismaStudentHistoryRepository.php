<?php

namespace Prisma\Sif\Repository;

final class LegacyPrismaStudentHistoryRepository
{
    public function findByDocument(
        \PDO $db,
        string $document,
        ?int $excludeEnrollmentId = null,
        ?string $evaluationAt = null
    ): array {
        $document = trim($document);
        if ($document === '') {
            throw new \InvalidArgumentException('Student document is required');
        }

        $sql = 'SELECT ID, DATA_INSC, A_PAGAR, PAGAMENT, OBSERVACIONS, GENERAT, IDPAG,
                       FACTURA_RELACIONADA, `INSC CURS` AS INSC_CURS
                FROM inscripcions
                WHERE DNI = ?';
        $parameters = [$document];

        if ($excludeEnrollmentId !== null) {
            if ($excludeEnrollmentId < 1) {
                throw new \InvalidArgumentException('Excluded enrollment id must be positive');
            }
            $sql .= ' AND ID <> ?';
            $parameters[] = $excludeEnrollmentId;
        }

        if ($evaluationAt !== null) {
            $evaluationAt = trim($evaluationAt);
            if ($evaluationAt === '') {
                throw new \InvalidArgumentException('Evaluation timestamp cannot be empty');
            }
            $sql .= ' AND DATA_INSC <= ?';
            $parameters[] = $evaluationAt;
        }

        $sql .= ' ORDER BY ID DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($parameters);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
