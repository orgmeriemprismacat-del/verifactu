<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class LegacyGroupSnapshotRepository
{
    public function loadByIdpag(\PDO $legacyDb, int $idpag, mixed $currentPaymentAmount): array
    {
        if ($idpag <= 0) {
            throw SifException::validation('Invalid legacy group IDPAG');
        }

        $inscriptions = $this->findGroupInscriptionsByIdpag($legacyDb, $idpag);
        if ($inscriptions === []) {
            throw SifException::conflict('Legacy group inscriptions not found for IDPAG');
        }

        $responsible = $this->findResponsible($legacyDb, $idpag);
        if ($responsible === null) {
            throw SifException::conflict('Legacy group responsible not found');
        }

        $items = [];
        foreach ($inscriptions as $inscription) {
            $inscription['IDPAG'] = $idpag;
            $course = $this->findCourse($legacyDb, $inscription);
            if ($course === null) {
                throw SifException::conflict('Legacy course not found for group inscription');
            }

            $items[] = [
                'inscription' => $inscription,
                'course' => $course,
            ];
        }

        return [
            'responsible' => $responsible,
            'items' => $items,
            'payment' => [
                'amount' => $this->money($currentPaymentAmount),
                'idpag' => $idpag,
            ],
        ];
    }

    private function findGroupInscriptionsByIdpag(\PDO $legacyDb, int $idpag): array
    {
        $stmt = $legacyDb->prepare(
            'SELECT ID, IDPAG, `ANY`, MES, CURS, `Grup`, TIPUS_INSC, NOM, COGNOMS, DNI, CORREU,
                    FACTURA_RELACIONADA, A_PAGAR, `INSC CURS`, PAGAMENT, FRACCIO,
                    FRACCIONAT, pag_observacions
             FROM inscripcions
             WHERE IDPAG = ? AND TIPUS_INSC = \'G\'
               AND (`INSC CURS` = \'0\' OR `INSC CURS` = \'1\' OR `INSC CURS` = \'M\')
             ORDER BY ID'
        );
        $stmt->execute([$idpag]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    private function findResponsible(\PDO $legacyDb, int $idpag): ?array
    {
        $stmt = $legacyDb->prepare(
            'SELECT NOM, COGNOMS, DNI, CORREU, ADRECA, Codi_Postal, Poblacio
             FROM respGrups
             WHERE IDPAG = ?'
        );
        $stmt->execute([$idpag]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function findCourse(\PDO $legacyDb, array $inscription): ?array
    {
        foreach (['ANY', 'MES', 'CURS'] as $field) {
            if (!array_key_exists($field, $inscription) || $inscription[$field] === '') {
                throw SifException::validation("Missing legacy group inscription field {$field}");
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
            throw SifException::validation('Invalid current group payment amount');
        }

        return number_format((float) $value, 2, '.', '');
    }
}
