<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyCourseSnapshotRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;

final class RedsysCourseInvoiceService
{
    public function __construct(
        private RedsysNotificationRepository $notifications,
        private LegacyCourseSnapshotRepository $legacySnapshots,
        private LegacyCourseInvoicePayloadBuilder $legacyPayloads,
        private RedsysInvoicePayloadBuilder $redsysPayloads,
        private InvoiceService $invoices
    ) {
    }

    public function issueFromValidatedNotification(\PDO $sifDb, \PDO $legacyDb, string $dsOrder): array
    {
        $notification = $this->validatedNotification($sifDb, $dsOrder);
        $idpag = $this->idpag($notification);
        $amount = $this->amount($notification);

        $snapshot = $this->legacySnapshots->loadByIdpag($legacyDb, $idpag, $amount);
        $basePayload = $this->legacyPayloads->build($snapshot);
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

    private function idpag(array $notification): int
    {
        if (!array_key_exists('IDPAG', $notification) || $notification['IDPAG'] === null || $notification['IDPAG'] === '') {
            throw SifException::validation('Validated Redsys course notification requires IDPAG');
        }

        $idpag = (int) $notification['IDPAG'];
        if ($idpag <= 0) {
            throw SifException::validation('Invalid Redsys course IDPAG');
        }

        return $idpag;
    }

    private function amount(array $notification): string
    {
        if (!array_key_exists('IMPORT', $notification) || !is_numeric($notification['IMPORT'])) {
            throw SifException::validation('Invalid Redsys course amount');
        }

        return number_format((float) $notification['IMPORT'], 2, '.', '');
    }
}
