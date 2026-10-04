<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class ClaimPaymentInvoiceLinkRepository
{
    public function resolveUniqueOriginForInscription(
        \PDO $db,
        int $inscriptionId
    ): array {
        return $this->assertMatches(
            $db,
            '1 = 1',
            [],
            $inscriptionId
        );
    }

    public function assertUuidMatches(
        \PDO $db,
        string $uuidFactura,
        int $inscriptionId
    ): array {
        $uuidFactura = trim($uuidFactura);
        if ($uuidFactura === '') {
            throw SifException::validation('Missing claim payment invoice UUID');
        }

        return $this->assertMatches(
            $db,
            'f.UUID_FACTURA = ?',
            [$uuidFactura],
            $inscriptionId
        );
    }

    public function assertNumVisibleMatches(
        \PDO $db,
        string $numVisible,
        int $inscriptionId
    ): array {
        $numVisible = trim($numVisible);
        if ($numVisible === '') {
            throw SifException::validation('Missing claim payment invoice number');
        }

        return $this->assertMatches(
            $db,
            'f.NUM_VISIBLE = ?',
            [$numVisible],
            $inscriptionId
        );
    }

    private function assertMatches(
        \PDO $db,
        string $selectorSql,
        array $selectorParams,
        int $inscriptionId
    ): array {
        if ($inscriptionId <= 0) {
            throw SifException::validation('Invalid claim payment inscription ID');
        }

        $stmt = $db->prepare(
            'SELECT DISTINCT f.UUID_FACTURA, f.NUM_VISIBLE, f.ESTAT_FACTURA, f.ESTAT_COBRAMENT,
                    r.IDPAG
             FROM factura AS f
             INNER JOIN fact_rels AS r ON r.UUID_FACTURA = f.UUID_FACTURA
             WHERE ' . $selectorSql . '
               AND r.SOURCE_TYPE = \'INSCRIPCIO\'
               AND r.SOURCE_ID = ?
               AND r.RELATION_TYPE = \'ORIGIN\'
             LIMIT 2'
        );
        $stmt->execute(array_merge($selectorParams, [$inscriptionId]));
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) !== 1) {
            throw SifException::conflict(
                'Claim payment invoice does not match exactly one inscription origin'
            );
        }

        return $rows[0];
    }
}
