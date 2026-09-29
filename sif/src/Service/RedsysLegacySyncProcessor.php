<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class RedsysLegacySyncProcessor implements RedsysJobProcessor
{
    public function __construct(
        private RedsysJobProcessor $inner,
        private \PDO $legacyDb,
        private CourseLegacyPaymentSyncService $courseSync
    ) {
    }

    public function process(\PDO $sifDb, array $job): array
    {
        $result = $this->inner->process($sifDb, $job);

        if (strtoupper(trim((string) ($job['SOURCE_TYPE'] ?? ''))) !== 'CURS') {
            return $result;
        }

        $snapshot = json_decode((string) ($job['SNAPSHOT_JSON'] ?? ''), true);
        if (!is_array($snapshot)) {
            throw SifException::validation('Invalid Redsys course snapshot for legacy sync');
        }

        $inscription = $snapshot['inscription'] ?? null;
        if (!is_array($inscription)) {
            throw SifException::validation('Missing Redsys course inscription snapshot');
        }

        $idpag = $this->positiveInt($snapshot['payment']['idpag'] ?? $inscription['IDPAG'] ?? null, 'IDPAG');
        $idInsc = $this->positiveInt($inscription['ID'] ?? null, 'inscription ID');
        $uuidFactura = trim((string) ($result['uuid_factura'] ?? ''));
        $numVisible = trim((string) ($result['num_visible'] ?? ''));
        if ($uuidFactura === '' || $numVisible === '') {
            throw SifException::conflict('SIF course result lacks invoice identity for legacy sync');
        }

        $result['legacy_payment_sync'] = $this->courseSync->sync(
            $sifDb,
            $this->legacyDb,
            $idpag,
            $idInsc,
            $uuidFactura,
            $numVisible
        );

        return $result;
    }

    private function positiveInt(mixed $value, string $label): int
    {
        $raw = trim((string) $value);
        if ($raw === '' || !ctype_digit($raw) || (int) $raw < 1) {
            throw SifException::validation("Invalid {$label}");
        }

        return (int) $raw;
    }
}
