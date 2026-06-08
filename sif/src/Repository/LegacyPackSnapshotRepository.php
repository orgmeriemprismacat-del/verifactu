<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class LegacyPackSnapshotRepository
{
    public function loadByIdpag(\PDO $legacyDb, int $idpag, mixed $currentPaymentAmount): array
    {
        if ($idpag <= 0) {
            throw SifException::validation('Invalid legacy pack IDPAG');
        }

        $inscriptions = $this->findPackInscriptionsByIdpag($legacyDb, $idpag);
        if ($inscriptions === []) {
            throw SifException::conflict('Legacy pack inscriptions not found for IDPAG');
        }

        $packId = $this->packIdFromInscriptions($inscriptions);
        $pack = $this->findPack($legacyDb, $packId);
        if ($pack === null) {
            throw SifException::conflict('Legacy pack not found');
        }

        $items = [];
        foreach ($inscriptions as $inscription) {
            $inscription['IDPAG'] = $idpag;
            $course = $this->findCourse($legacyDb, $inscription);
            if ($course === null) {
                throw SifException::conflict('Legacy course not found for pack inscription');
            }

            $items[] = [
                'inscription' => $inscription,
                'course' => $course,
            ];
        }

        return [
            'pack' => array_replace(['ID_PACK' => $packId], $pack),
            'items' => $items,
            'payment' => [
                'amount' => $this->money($currentPaymentAmount),
                'idpag' => $idpag,
            ],
        ];
    }

    private function findPackInscriptionsByIdpag(\PDO $legacyDb, int $idpag): array
    {
        $stmt = $legacyDb->prepare(
            'SELECT ID, IDPAG, `ANY`, MES, CURS, TIPUS_INSC, NOM, COGNOMS, DNI, CORREU,
                    ADRECA, Codi_Postal, Poblacio, FACTURA_RELACIONADA, A_PAGAR,
                    `INSC CURS`, PAGAMENT, FRACCIO, FRACCIONAT, OBSERVACIONS, pag_observacions
             FROM inscripcions
             WHERE IDPAG = ? AND TIPUS_INSC = \'P\'
               AND (`INSC CURS` = \'0\' OR `INSC CURS` = \'1\' OR `INSC CURS` = \'M\')
             ORDER BY A_PAGAR DESC, ID'
        );
        $stmt->execute([$idpag]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    private function findPack(\PDO $legacyDb, int $packId): ?array
    {
        $stmt = $legacyDb->prepare(
            'SELECT ID_PACK, TITOL, CODI
             FROM info_pack
             WHERE ID_PACK = ?'
        );
        $stmt->execute([$packId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function findCourse(\PDO $legacyDb, array $inscription): ?array
    {
        foreach (['ANY', 'MES', 'CURS'] as $field) {
            if (!array_key_exists($field, $inscription) || $inscription[$field] === '') {
                throw SifException::validation("Missing legacy pack inscription field {$field}");
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

    private function packIdFromInscriptions(array $inscriptions): int
    {
        foreach ($inscriptions as $inscription) {
            $observations = (string) ($inscription['OBSERVACIONS'] ?? '');
            foreach (preg_split('/\s+/', trim($observations)) ?: [] as $token) {
                $parts = explode('|', $token);
                if (count($parts) >= 2 && strtoupper($parts[0]) === 'PACK' && is_numeric($parts[1])) {
                    $packId = (int) $parts[1];
                    if ($packId > 0) {
                        return $packId;
                    }
                }
            }
        }

        throw SifException::validation('Missing PACK marker in legacy observations');
    }

    private function money(mixed $value): string
    {
        if (!is_numeric($value)) {
            throw SifException::validation('Invalid current pack payment amount');
        }

        return number_format((float) $value, 2, '.', '');
    }
}
