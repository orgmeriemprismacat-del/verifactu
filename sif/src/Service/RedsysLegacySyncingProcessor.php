<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class RedsysLegacySyncingProcessor implements RedsysJobProcessor
{
    public function __construct(
        private RedsysJobProcessor $inner,
        private \PDO $legacyDb,
        private LegacySyncService $legacySync,
        private ?CourseLegacyPaymentSyncService $coursePaymentSync = null
    ) {
    }

    public function process(\PDO $sifDb, array $job): array
    {
        $result = $this->inner->process($sifDb, $job);
        $sync = $result['legacy_sync'] ?? null;

        if (!is_array($sync)) {
            if (strtoupper(trim((string) ($job['SOURCE_TYPE'] ?? ''))) !== 'CURS'
                || $this->coursePaymentSync === null
            ) {
                return $result;
            }

            $snapshot = json_decode((string) ($job['SNAPSHOT_JSON'] ?? ''), true);
            $inscription = is_array($snapshot) ? ($snapshot['inscription'] ?? null) : null;
            if (!is_array($inscription)) {
                throw SifException::validation('Missing Redsys course inscription snapshot');
            }

            $idpagRaw = $snapshot['payment']['idpag'] ?? $inscription['IDPAG'] ?? null;
            $idInscRaw = $inscription['ID'] ?? null;
            if (!ctype_digit((string) $idpagRaw) || !ctype_digit((string) $idInscRaw)) {
                throw SifException::validation('Invalid Redsys course identity for legacy payment sync');
            }

            $uuidFactura = trim((string) ($result['uuid_factura'] ?? ''));
            $numVisible = trim((string) ($result['num_visible'] ?? ''));
            if ($uuidFactura === '' || $numVisible === '') {
                throw SifException::conflict('SIF course result lacks invoice identity for legacy payment sync');
            }

            $result['legacy_payment_sync'] = $this->coursePaymentSync->sync(
                $sifDb,
                $this->legacyDb,
                (int) $idpagRaw,
                (int) $idInscRaw,
                $uuidFactura,
                $numVisible
            );
            $result['legacy_sync_executed'] = true;

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
