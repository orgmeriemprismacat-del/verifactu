<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyPackSnapshotRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;

final class RedsysPackInvoiceService implements RedsysIntentHandler
{
    public function __construct(
        private RedsysNotificationRepository $notifications,
        private LegacyPackSnapshotRepository $legacySnapshots,
        private LegacyPackInvoicePayloadBuilder $legacyPayloads,
        private RedsysInvoicePayloadBuilder $redsysPayloads,
        private InvoiceService $invoices,
        private ?PackPaymentNotificationService $packNotifications = null
    ) {
    }

    public function sourceType(): string
    {
        return 'PACK';
    }

    public function issueFromIntentSnapshot(\PDO $sifDb, string $dsOrder, array $snapshot): array
    {
        $basePayload = $this->legacyPayloads->build($snapshot);
        $payload = $this->redsysPayloads->buildFromValidatedNotification($sifDb, $dsOrder, $basePayload);
        $this->assertPaymentMatchesInvoice($payload);

        $result = $this->invoices->issueInvoice($payload);
        if ($this->packNotifications !== null) {
            $result['notification_outbox'] = $this->packNotifications->enqueue(
                $sifDb,
                $dsOrder,
                $snapshot,
                $result
            );
        }
        $result['legacy_sync'] = [
            'mode' => 'PACK_FULL_PAYMENT',
            'relations' => $payload['relations'] ?? [],
            'estat_cobrament' => isset($payload['payment']) ? 'PAID' : 'PENDING',
            'movement_date' => (string) ($payload['payment']['movement_date'] ?? date('Y-m-d H:i:s')),
        ];

        return $result;
    }

    public function issueFromValidatedNotification(\PDO $sifDb, \PDO $legacyDb, string $dsOrder): array
    {
        $notification = $this->validatedNotification($sifDb, $dsOrder);
        $idpag = $this->idpag($notification);
        $amount = $this->amount($notification);

        $snapshot = $this->legacySnapshots->loadByIdpag($legacyDb, $idpag, $amount);
        $basePayload = $this->legacyPayloads->build($snapshot);
        $payload = $this->redsysPayloads->buildFromValidatedNotification($sifDb, $dsOrder, $basePayload);
        $this->assertPaymentMatchesInvoice($payload);
        $result = $this->invoices->issueInvoice($payload);
        $result['legacy_sync'] = [
            'relations' => $payload['relations'] ?? [],
            'estat_cobrament' => isset($payload['payment']) ? 'PAID' : 'PENDING',
        ];

        return $result;
    }

    private function assertPaymentMatchesInvoice(array $payload): void
    {
        $invoiceTotal = $payload['totals']['total'] ?? null;
        $paymentAmount = $payload['payment']['amount'] ?? null;

        if (!is_numeric($invoiceTotal) || !is_numeric($paymentAmount)) {
            throw SifException::validation('Pack invoice/payment reconciliation data is incomplete');
        }

        $invoiceTotal = number_format((float) $invoiceTotal, 2, '.', '');
        $paymentAmount = number_format((float) $paymentAmount, 2, '.', '');

        if ($invoiceTotal !== $paymentAmount) {
            throw SifException::conflict('Pack invoice total does not match validated Redsys amount');
        }
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
            throw SifException::validation('Validated Redsys pack notification requires IDPAG');
        }

        $idpag = (int) $notification['IDPAG'];
        if ($idpag <= 0) {
            throw SifException::validation('Invalid Redsys pack IDPAG');
        }

        return $idpag;
    }

    private function amount(array $notification): string
    {
        if (!array_key_exists('IMPORT', $notification) || !is_numeric($notification['IMPORT'])) {
            throw SifException::validation('Invalid Redsys pack amount');
        }

        return number_format((float) $notification['IMPORT'], 2, '.', '');
    }
}
