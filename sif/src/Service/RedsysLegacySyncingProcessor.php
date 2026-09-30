<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class RedsysLegacySyncingProcessor implements RedsysJobProcessor
{
    public function __construct(
        private RedsysJobProcessor $inner,
        private \PDO $legacyDb,
        private LegacySyncService $legacySync
    ) {
    }

    public function process(\PDO $sifDb, array $job): array
    {
        $result = $this->inner->process($sifDb, $job);
        $sync = $result['legacy_sync'] ?? null;

        if (!is_array($sync)) {
            return $result;
        }

        $uuidFactura = trim((string) ($result['uuid_factura'] ?? ''));
        $numVisible = trim((string) ($result['num_visible'] ?? ''));
        $relations = $sync['relations'] ?? null;
        $estatCobrament = trim((string) ($sync['estat_cobrament'] ?? ''));

        if ($uuidFactura === '' || $numVisible === '' || !is_array($relations)
            || $estatCobrament === ''
        ) {
            throw SifException::conflict('Incomplete Redsys legacy sync payload');
        }

        $this->legacySync->syncAfterSifSuccess(
            $this->legacyDb,
            $relations,
            $uuidFactura,
            $numVisible,
            $estatCobrament
        );

        if (($sync['mode'] ?? '') === 'PACK_FULL_PAYMENT') {
            $movementDate = trim((string) ($sync['movement_date'] ?? ''));
            if ($movementDate === '') {
                throw SifException::conflict('Missing pack payment movement date for legacy sync');
            }

            $this->legacySync->syncPackFullPayment(
                $this->legacyDb,
                $relations,
                $movementDate
            );
        }

        $result['legacy_sync_executed'] = true;

        return $result;
    }
}
