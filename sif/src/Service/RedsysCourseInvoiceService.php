<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyCourseSnapshotRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;

final class RedsysCourseInvoiceService implements RedsysIntentHandler
{
    public function __construct(
        private RedsysNotificationRepository $notifications,
        private LegacyCourseSnapshotRepository $legacySnapshots,
        private LegacyCourseInvoicePayloadBuilder $legacyPayloads,
        private RedsysInvoicePayloadBuilder $redsysPayloads,
        private InvoiceService $invoices,
        private ?NovicePromotionInvoiceLinkService $noviceLinks = null,
        private ?NovicePromotionGrantService $noviceGrants = null
    ) {
    }

    public function sourceType(): string
    {
        return 'CURS';
    }

    public function issueFromIntentSnapshot(\PDO $sifDb, string $dsOrder, array $snapshot): array
    {
        $basePayload = $this->legacyPayloads->build($snapshot);
        $payload = $this->redsysPayloads->buildFromValidatedNotification($sifDb, $dsOrder, $basePayload);

        $invoice = $this->invoices->issueInvoice($payload);

        return $this->afterCommittedCourseInvoice($sifDb, $snapshot, $invoice);
    }

    public function issueFromValidatedNotification(
        \PDO $sifDb,
        \PDO $legacyDb,
        string $dsOrder,
        ?array $discountSnapshot = null
    ): array
    {
        $notification = $this->validatedNotification($sifDb, $dsOrder);
        $idpag = $this->idpag($notification);
        $amount = $this->amount($notification);

        $snapshot = $this->legacySnapshots->loadByIdpag($legacyDb, $idpag, $amount);
        if ($discountSnapshot !== null) {
            $snapshot['discount'] = $discountSnapshot;
        }

        $basePayload = $this->legacyPayloads->build($snapshot);
        $payload = $this->redsysPayloads->buildFromValidatedNotification($sifDb, $dsOrder, $basePayload);
        $result = $this->invoices->issueInvoice($payload);
        $result = $this->afterCommittedCourseInvoice($sifDb, $snapshot, $result);
        $result['legacy_sync'] = [
            'relations' => $payload['relations'] ?? [],
            'estat_cobrament' => isset($payload['payment']) ? 'PAID' : 'PENDING',
        ];

        return $result;
    }

    /**
     * Independent post-commit promotion step. InvoiceService has already
     * committed the real invoice/payment; any failure below is retried by the
     * Redsys job with the existing invoice's idempotency key.
     *
     * NOT_STAGED must be monitored: we refuse to infer secretary approval or
     * invent a canonical identity from a callback snapshot.
     */
    private function afterCommittedCourseInvoice(\PDO $sifDb, array $snapshot, array $invoiceResult): array
    {
        if ($this->noviceLinks === null || $this->noviceGrants === null) {
            return $invoiceResult;
        }

        $inscription = $snapshot['inscription'] ?? [];
        if (!is_array($inscription) || strtoupper(trim((string) ($inscription['CURS'] ?? $inscription['curs'] ?? ''))) !== 'JASOM') {
            return $invoiceResult;
        }

        $rawId = (string) ($inscription['ID'] ?? $inscription['id'] ?? '');
        if ($rawId === '' || !ctype_digit($rawId) || (int) $rawId < 1) {
            throw SifException::validation('JASOM callback lacks a verified enrollment reference.');
        }

        $uuidInvoice = trim((string) ($invoiceResult['uuid_factura'] ?? ''));
        if ($uuidInvoice === '') {
            throw SifException::conflict('JASOM invoice response has no persisted invoice reference.');
        }

        $link = $this->noviceLinks->attach($sifDb, (int) $rawId, $uuidInvoice);
        $invoiceResult['novice_promotion_sync'] = $link['status'];

        if ($link['grant_eligible']) {
            $invoiceResult['novice_promotion'] = $this->noviceGrants->issueForOperation(
                $sifDb,
                (string) $link['uuid_operation']
            );
        }

        return $invoiceResult;
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
