<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class LegacyCourseSnapshotRepository
{
    public function loadByIdpag(\PDO $legacyDb, int $idpag, mixed $currentPaymentAmount): array
    {
        $context = $this->loadCourseContextByIdpag($legacyDb, $idpag);

        return [
            'inscription' => $context['inscription'],
            'course' => $context['course'],
            'payment' => [
                'amount' => $this->money($currentPaymentAmount),
                'idpag' => $idpag,
            ],
        ];
    }

    public function loadCourseContextByIdpag(\PDO $legacyDb, int $idpag): array
    {
        if ($idpag <= 0) {
            throw SifException::validation('Invalid legacy IDPAG');
        }

        $inscription = $this->findInscriptionByIdpag($legacyDb, $idpag);
        if ($inscription === null) {
            throw SifException::conflict('Legacy course inscription not found or ambiguous for IDPAG');
        }

        $inscription['IDPAG'] = $idpag;
        $course = $this->findCourse($legacyDb, $inscription);
        if ($course === null) {
            throw SifException::conflict('Legacy course not found for inscription');
        }

        return ['inscription' => $inscription, 'course' => $course];
    }

    private function findInscriptionByIdpag(\PDO $legacyDb, int $idpag): ?array
    {
        $stmt = $legacyDb->prepare(
            'SELECT i.ID, i.`ANY`, i.MES, i.CURS, i.DATA_INSC, i.NOM, i.COGNOMS,
                    i.DNI, i.CORREU, i.ADRECA, i.Codi_Postal, i.Poblacio,
                    i.FACTURA_RELACIONADA, i.A_PAGAR, i.`INSC CURS`, i.PAGAMENT,
                    i.FRACCIONAT, i.FRACCIO, i.TIPUS_DESC, i.VALID_DESC
             FROM inscripcions AS i
             WHERE i.IDPAG = ?
               AND (i.`INSC CURS` = \'0\' OR i.`INSC CURS` = \'1\' OR i.`INSC CURS` = \'M\')
               AND (
                   SELECT COUNT(*)
                   FROM inscripcions AS duplicate_guard
                   WHERE duplicate_guard.IDPAG = i.IDPAG
                     AND (
                         duplicate_guard.`INSC CURS` = \'0\'
                         OR duplicate_guard.`INSC CURS` = \'1\'
                         OR duplicate_guard.`INSC CURS` = \'M\'
                     )
               ) = 1
             LIMIT 1'
        );
        $stmt->execute([$idpag]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function findCourse(\PDO $legacyDb, array $inscription): ?array
    {
        foreach (['ANY', 'MES', 'CURS'] as $field) {
            if (!array_key_exists($field, $inscription) || $inscription[$field] === '') {
                throw SifException::validation("Missing legacy inscription field {$field}");
            }
        }

        $stmt = $legacyDb->prepare(
            'SELECT NOM_CURS, DATAI, DATAF, HORES, ID_PREU
             FROM curs
             WHERE `ANY` = ? AND MES = ? AND CURS = ?'
        );
        $stmt->execute([
            (int) $inscription['ANY'],
            (string) $inscription['MES'],
            (string) $inscription['CURS'],
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function money(mixed $value): string
    {
        if (!is_numeric($value)) {
            throw SifException::validation('Invalid current payment amount');
        }

        return number_format((float) $value, 2, '.', '');
    }
}
