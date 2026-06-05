<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class DocumentRepository
{
    public function registerDocument(
        \PDO $db,
        string $uuidFactura,
        string $type,
        string $path,
        string $contents
    ): array {
        $type = strtoupper(trim($type));
        $path = trim($path);

        if (!in_array($type, ['PDF', 'XML', 'QR'], true)) {
            throw SifException::validation('Invalid document type');
        }

        if ($path === '' || strlen($path) > 255) {
            throw SifException::validation('Invalid document path');
        }

        $hash = hash('sha256', $contents);

        $db->prepare(
            'INSERT INTO factura_documents (UUID_FACTURA, TIPUS, PATH_FITXER, HASH_FITXER, ESTAT)
             VALUES (?, ?, ?, ?, \'CREATED\')'
        )->execute([$uuidFactura, $type, $path, $hash]);

        return [
            'ok' => true,
            'hash' => $hash,
        ];
    }
}
