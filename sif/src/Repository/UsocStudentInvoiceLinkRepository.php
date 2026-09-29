<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class UsocStudentInvoiceLinkRepository
{
    public function assertMatches(
        \PDO $sifDb,
        string $studentInvoiceUuid,
        int $inscriptionId,
        int $idpag
    ): void {
        if (trim($studentInvoiceUuid) === '') {
            throw SifException::validation('Missing USOC student invoice UUID');
        }
        if ($inscriptionId <= 0) {
            throw SifException::validation('Invalid USOC inscription ID');
        }
        if ($idpag <= 0) {
            throw SifException::validation('Invalid USOC IDPAG');
        }

        $stmt = $sifDb->prepare(
            'SELECT f.UUID_FACTURA, f.IDEMPOTENCY_KEY, f.SOURCE_CHANNEL,
                    r.SOURCE_TYPE, r.SOURCE_ID, r.IDPAG, r.VISIBLE_ALUMNE
             FROM factura AS f
             INNER JOIN fact_rels AS r ON r.UUID_FACTURA = f.UUID_FACTURA
             WHERE f.UUID_FACTURA = ?
               AND r.SOURCE_TYPE = \'INSCRIPCIO\'
               AND r.SOURCE_ID = ?
               AND r.IDPAG = ?
               AND r.VISIBLE_ALUMNE = 1
             LIMIT 1'
        );
        $stmt->execute([$studentInvoiceUuid, $inscriptionId, $idpag]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw SifException::conflict('USOC student invoice does not match inscription and IDPAG');
        }

        $expectedPrefix = 'REDSYS|USOC_ALUMNE|IDPAG:' . $idpag . '|ORDER:';
        if (
            (string) ($row['SOURCE_CHANNEL'] ?? '') !== 'REDSYS'
            || !str_starts_with((string) ($row['IDEMPOTENCY_KEY'] ?? ''), $expectedPrefix)
        ) {
            throw SifException::conflict('USOC student invoice is not a Redsys USOC student invoice');
        }
    }
}
