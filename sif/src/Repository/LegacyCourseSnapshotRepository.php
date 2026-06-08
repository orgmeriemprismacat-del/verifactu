<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class LegacyCourseSnapshotRepository
{
    public function loadByIdpag(\PDO $legacyDb, int $idpag, mixed $currentPaymentAmount): array
    {
        if ($idpag <= 0) {
            throw SifException::validation('Invalid legacy IDPAG');
        }

        $inscription = $this->findInscriptionByIdpag($legacyDb, $idpag);
        if ($inscription === null) {
            throw SifException::conflict('Legacy inscription not found for IDPAG');
        }

        $inscription['IDPAG'] = $idpag;

        $course = $this->findCourse($legacyDb, $inscription);
        if ($course === null) {
            throw SifException::conflict('Legacy course not found for inscription');
        }

        return [
            'inscription' => $inscription,
            'course' => $course,
            'payment' => [
                'amount' => $this->money($currentPaymentAmount),
                'idpag' => $idpag,
            ],
        ];
    }

    private function findInscriptionByIdpag(\PDO $legacyDb, int $idpag): ?array
    {
        $stmt = $legacyDb->prepare(
            'SELECT ID, `ANY`, MES, CURS, NOM, COGNOMS, DNI, CORREU, ADRECA, Codi_Postal,
                    Poblacio, FACTURA_RELACIONADA, A_PAGAR, `INSC CURS`, PAGAMENT, FRACCIO
             FROM inscripcions
             WHERE IDPAG = ? AND (`INSC CURS` = \'0\' OR `INSC CURS` = \'1\' OR `INSC CURS` = \'M\')'
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
            'SELECT NOM_CURS, DATAI, DATAF, HORES
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
