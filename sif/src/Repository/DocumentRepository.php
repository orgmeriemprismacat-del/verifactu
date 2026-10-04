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
        string $contents,
        string $status = 'CREATED'
    ): array {
        $type = strtoupper(trim($type));
        $path = trim($path);
        $status = strtoupper(trim($status));

        if (!in_array($type, ['PDF', 'XML', 'QR'], true)) {
            throw SifException::validation('Invalid document type');
        }

        if ($path === '' || strlen($path) > 255) {
            throw SifException::validation('Invalid document path');
        }

        if (!in_array($status, ['CREATED', 'READY', 'ARCHIVED'], true)) {
            throw SifException::validation('Invalid document status');
        }

        $hash = hash('sha256', $contents);

        $db->prepare(
            'INSERT INTO factura_documents (UUID_FACTURA, TIPUS, PATH_FITXER, HASH_FITXER, ESTAT)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$uuidFactura, $type, $path, $hash, $status]);

        return [
            'ok' => true,
            'document_id' => (int) $db->lastInsertId(),
            'hash' => $hash,
            'status' => $status,
        ];
    }
}
