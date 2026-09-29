<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class IncidentRepository
{
    public function open(\PDO $db, ?string $uuidFactura, string $type, string $message): array
    {
        $type = strtoupper(trim($type));
        $message = trim($message);

        if ($type === '' || strlen($type) > 50) {
            throw SifException::validation('Invalid incident type');
        }

        if ($message === '') {
            throw SifException::validation('Invalid incident message');
        }

        $db->prepare(
            'INSERT INTO errors_verifactu (UUID_FACTURA, TIPUS_INCIDENCIA, ESTAT, DETAILS)
             VALUES (?, ?, \'OPEN\', ?)'
        )->execute([$uuidFactura, $type, $message]);

        return [
            'ok' => true,
        ];
    }

    public function resolveAeatQueueReview(\PDO $db, string $uuidFactura, int $queueId): int
    {
        $prefix = 'Queue ID ' . $queueId . ':%';
        $stmt = $db->prepare(
            "UPDATE errors_verifactu
             SET ESTAT = 'RESOLVED'
             WHERE UUID_FACTURA = ?
               AND ESTAT = 'OPEN'
               AND TIPUS_INCIDENCIA LIKE 'AEAT_%'
               AND DETAILS LIKE ?"
        );
        $stmt->execute([$uuidFactura, $prefix]);

        return $stmt->rowCount();
    }

}
