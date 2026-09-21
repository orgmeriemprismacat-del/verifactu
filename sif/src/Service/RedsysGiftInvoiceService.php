<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyGiftSnapshotRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;

final class RedsysGiftInvoiceService implements RedsysIntentHandler
{
    public function __construct(
        private RedsysNotificationRepository $notifications,
        private LegacyGiftSnapshotRepository $legacySnapshots,
        private LegacyGiftInvoicePayloadBuilder $legacyPayloads,
        private RedsysInvoicePayloadBuilder $redsysPayloads,
        private InvoiceService $invoices
    ) {
    }

    public function sourceType(): string
    {
        return 'REGAL';
    }

    public function issueFromIntentSnapshot(\PDO $sifDb, string $dsOrder, array $snapshot): array
    {
        $giftId = (int) ($snapshot['gift']['ID'] ?? $snapshot['gift']['id'] ?? 0);
        if ($giftId <= 0) {
            throw SifException::validation('Invalid Redsys gift snapshot ID');
        }

        $notification = $this->validatedNotification($sifDb, $dsOrder);

        return $this->issueSnapshot($sifDb, $dsOrder, $snapshot, $this->amount($notification));
    }

    public function issueByGiftIdFromValidatedNotification(
        \PDO $sifDb,
        \PDO $legacyDb,
        string $dsOrder,
        int $giftId
    ): array {
        if ($giftId <= 0) {
            throw SifException::validation('Invalid legacy gift ID');
        }

        $notification = $this->validatedNotification($sifDb, $dsOrder);
        $amount = $this->amount($notification);
        $snapshot = $this->legacySnapshots->loadById($legacyDb, $giftId);

        return $this->issueSnapshot($sifDb, $dsOrder, $snapshot, $amount);
    }

    public function issueByGiftCodeFromValidatedNotification(
        \PDO $sifDb,
        \PDO $legacyDb,
        string $dsOrder,
        string $giftCode
    ): array {
        $giftCode = trim($giftCode);
        if ($giftCode === '') {
            throw SifException::validation('Missing legacy gift code');
        }

        $notification = $this->validatedNotification($sifDb, $dsOrder);
        $amount = $this->amount($notification);
        $snapshot = $this->legacySnapshots->loadByCode($legacyDb, $giftCode);

        return $this->issueSnapshot($sifDb, $dsOrder, $snapshot, $amount);
    }

    private function issueSnapshot(\PDO $sifDb, string $dsOrder, array $snapshot, string $amount): array
    {
        $basePayload = $this->legacyPayloads->build($snapshot);
        $this->assertMatchingAmount($basePayload, $amount);

        $payload = $this->redsysPayloads->buildFromValidatedNotification($sifDb, $dsOrder, $basePayload);
        $result = $this->invoices->issueInvoice($payload);
        $result['legacy_sync'] = [
            'relations' => $payload['relations'] ?? [],
            'estat_cobrament' => isset($payload['payment']) ? 'PAID' : 'PENDING',
        ];

        return $result;
    }

    private function validatedNotification(\PDO $sifDb, string $dsOrder): array
    {
        $notification = $this->notifications->findByDsOrder($sifDb, $dsOrder);
        if ($notification === null) {
            throw SifException::validation('Redsys notification not found');
        }

        if ((string) $notification['STATUS'] !== 'VALIDATED') {
            throw SifException::conflict('Redsys notification is not validated');
        }

        return $notification;
    }

    private function amount(array $notification): string
    {
        if (!array_key_exists('IMPORT', $notification) || !is_numeric($notification['IMPORT'])) {
            throw SifException::validation('Invalid Redsys gift amount');
        }

        return number_format((float) $notification['IMPORT'], 2, '.', '');
    }

    private function assertMatchingAmount(array $basePayload, string $notificationAmount): void
    {
        $invoiceAmount = number_format((float) ($basePayload['totals']['total'] ?? 0), 2, '.', '');
        if ($invoiceAmount !== $notificationAmount) {
            throw SifException::conflict('Redsys gift amount does not match gift amount');
        }
    }
}
