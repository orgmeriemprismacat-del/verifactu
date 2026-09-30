<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class InvoiceBeforePaymentSelectionRepository
{
    public function loadByIds(\PDO $legacyDb, array $ids): array
    {
        $ids = $this->normaliseIds($ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $legacyDb->prepare(
            'SELECT
                i.ID,
                i.IDPAG,
                i.`ANY`,
                i.MES,
                i.CURS,
                i.NOM,
                i.COGNOMS,
                i.DNI,
                i.CORREU,
                i.A_PAGAR,
                i.PAGAMENT,
                i.FACTURA_RELACIONADA,
                i.`INSC CURS` AS INSC_CURS,
                c.NOM_CURS,
                c.HORES,
                c.DATAI,
                c.DATAF
             FROM inscripcions AS i
             INNER JOIN curs AS c
               ON i.`ANY` = c.`ANY`
              AND i.MES = c.MES
              AND i.CURS = c.CURS
             WHERE i.ID IN (' . $placeholders . ')
             ORDER BY i.ID'
        );
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!is_array($rows) || count($rows) !== count($ids)) {
            throw SifException::conflict(
                'One or more selected legacy inscriptions no longer exist or have no matching course'
            );
        }

        $byId = [];
        foreach ($rows as $row) {
            $id = (int) ($row['ID'] ?? 0);
            if ($id <= 0 || isset($byId[$id])) {
                throw SifException::conflict('Invalid or duplicated legacy inscription row');
            }
            $byId[$id] = $row;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (!isset($byId[$id])) {
                throw SifException::conflict("Legacy inscription {$id} was not returned");
            }
            $ordered[] = $byId[$id];
        }

        return $ordered;
    }

    private function normaliseIds(array $ids): array
    {
        if ($ids === []) {
            throw SifException::validation('Invoice before payment requires at least one inscription ID');
        }

        $normalised = [];
        $seen = [];

        foreach ($ids as $value) {
            if (is_string($value) && ctype_digit(trim($value))) {
                $value = (int) trim($value);
            }

            if (!is_int($value) || $value <= 0) {
                throw SifException::validation('Invalid invoice before payment inscription ID');
            }

            if (isset($seen[$value])) {
                throw SifException::validation(
                    "Duplicate invoice before payment inscription ID {$value}"
                );
            }

            $seen[$value] = true;
            $normalised[] = $value;
        }

        sort($normalised, SORT_NUMERIC);

        return $normalised;
    }
}
