<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CommercialEntitlementRepository;
use Prisma\Sif\Repository\LegacyGiftSnapshotRepository;

final class ManualGiftInvoiceService
{
    public function __construct(
        private LegacyGiftSnapshotRepository $legacySnapshots,
        private ManualGiftInvoicePayloadBuilder $manualPayloads,
        private InvoiceService $invoices,
        private ?\PDO $sifDb = null,
        private ?GiftEntitlementIssuerService $giftEntitlements = null
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
        $gift = $snapshot['gift'] ?? null;
        if (!is_array($gift)) {
            throw SifException::validation('Missing gift snapshot');
        }

        $payload = $this->manualPayloads->buildFromSnapshot($snapshot, $input);
        $result = $this->invoices->issueInvoice($payload);

        if ($this->sifDb !== null) {
            $issuer = $this->giftEntitlements
                ?? new GiftEntitlementIssuerService(
                    new UuidGenerator(),
                    new CommercialEntitlementRepository(new UuidGenerator())
                );
            $result['gift_entitlement'] = $issuer->issue(
                $this->sifDb,
                $gift,
                $result,
                'MANUAL-' . hash('sha256', (string) ($payload['idempotency_key'] ?? '')),
                'INTRANET',
                (string) ($input['created_by'] ?? 'passar-pagaments-regal')
            );
        }

        $result['legacy_sync'] = [
            'relations' => $payload['relations'] ?? [],
            'estat_cobrament' => isset($payload['payment']) ? 'PAID' : 'PENDING',
        ];

        return $result;
    }
}
