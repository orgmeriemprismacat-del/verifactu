<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyGiftSnapshotRepository;

final class ManualGiftInvoiceService
{
    public function __construct(
        private LegacyGiftSnapshotRepository $legacySnapshots,
        private ManualGiftInvoicePayloadBuilder $manualPayloads,
        private InvoiceService $invoices
    ) {
    }

    public function issueByGiftIdFromManualPayment(\PDO $legacyDb, int $giftId, array $input): array
    {
        if ($giftId <= 0) {
            throw SifException::validation('Invalid legacy gift ID');
        }

        $snapshot = $this->legacySnapshots->loadById($legacyDb, $giftId);

        return $this->issueSnapshot($snapshot, $input);
    }

    public function issueByGiftCodeFromManualPayment(\PDO $legacyDb, string $giftCode, array $input): array
    {
        $giftCode = trim($giftCode);
        if ($giftCode === '') {
            throw SifException::validation('Missing legacy gift code');
        }

        $snapshot = $this->legacySnapshots->loadByCode($legacyDb, $giftCode);

        return $this->issueSnapshot($snapshot, $input);
    }

    private function issueSnapshot(array $snapshot, array $input): array
    {
        $payload = $this->manualPayloads->buildFromSnapshot($snapshot, $input);
        $result = $this->invoices->issueInvoice($payload);
        $result['legacy_sync'] = [
            'relations' => $payload['relations'] ?? [],
            'estat_cobrament' => isset($payload['payment']) ? 'PAID' : 'PENDING',
        ];

        return $result;
    }
}
