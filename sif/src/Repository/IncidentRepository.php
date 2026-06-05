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
}
