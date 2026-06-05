<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Repository\LegacySyncRepository;

final class LegacySyncService
{
    public function __construct(private LegacySyncRepository $repository)
    {
    }

    public function syncAfterSifSuccess(
        \PDO $legacyDb,
        array $relations,
        string $uuidFactura,
        string $numVisible,
        string $estatCobrament
    ): void {
        foreach ($relations as $relation) {
            if (($relation['source_type'] ?? '') !== 'INSCRIPCIO' || !isset($relation['source_id'])) {
                continue;
            }

            $facturaRelacionada = isset($relation['factura_relacionada'])
                ? (int) $relation['factura_relacionada']
                : null;

            $this->repository->syncInscripcioSummary(
                $legacyDb,
                (int) $relation['source_id'],
                $facturaRelacionada,
                $uuidFactura,
                $numVisible,
                $estatCobrament
            );
        }
    }
}
