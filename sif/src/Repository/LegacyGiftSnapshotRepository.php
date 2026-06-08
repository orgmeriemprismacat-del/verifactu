<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class LegacyGiftSnapshotRepository
{
    public function loadById(\PDO $legacyDb, int $giftId): array
    {
        if ($giftId <= 0) {
            throw SifException::validation('Invalid legacy gift ID');
        }

        return $this->loadOne($legacyDb, 'ID = ?', [$giftId]);
    }

    public function loadByCode(\PDO $legacyDb, string $code): array
    {
        $code = trim($code);
        if ($code === '') {
            throw SifException::validation('Missing legacy gift code');
        }

        return $this->loadOne($legacyDb, 'CODI = ?', [$code]);
    }

    private function loadOne(\PDO $legacyDb, string $where, array $params): array
    {
        $stmt = $legacyDb->prepare(
            'SELECT ID, NOM_CURS, CCURS, NOMC, NIFC, MAILC, ADRECAC,
                    POBLEC, CPC, CODI, IMPORT, FACT_REL, ORIGEN, DESTI, OBSERVACIONS
             FROM regal
             WHERE ' . $where
        );
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw SifException::conflict('Legacy gift not found');
        }

        return [
            'gift' => $this->normalise($row),
        ];
    }

    private function normalise(array $row): array
    {
        if (!isset($row['ID']) || !is_numeric($row['ID']) || (int) $row['ID'] <= 0) {
            throw SifException::validation('Invalid legacy gift ID');
        }

        if (isset($row['IMPORT']) && $row['IMPORT'] !== '') {
            if (!is_numeric($row['IMPORT'])) {
                throw SifException::validation('Invalid legacy gift amount');
            }

            $row['IMPORT'] = number_format((float) $row['IMPORT'], 2, '.', '');
        }

        $row['ID'] = (int) $row['ID'];

        return $row;
    }
}
