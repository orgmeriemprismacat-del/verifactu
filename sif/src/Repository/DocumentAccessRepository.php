<?php

namespace Prisma\Sif\Repository;

final class DocumentAccessRepository
{
    public function findById(\PDO $db, int $documentId): ?array
    {
        $stmt = $db->prepare(
            'SELECT ID, UUID_FACTURA, TIPUS, PATH_FITXER, HASH_FITXER, ESTAT, CREATED_AT
             FROM factura_documents
             WHERE ID = ?'
        );
        $stmt->execute([$documentId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
