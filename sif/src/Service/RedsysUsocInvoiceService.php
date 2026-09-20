<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyUsocSnapshotRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;

final class RedsysUsocInvoiceService implements RedsysIntentHandler
{
    public function __construct(
        private RedsysNotificationRepository $notifications,
        private LegacyUsocSnapshotRepository $legacySnapshots,
        private LegacyUsocInvoicePayloadBuilder $legacyPayloads,
        private RedsysInvoicePayloadBuilder $redsysPayloads,
        private InvoiceService $invoices
    ) {
    }

    public function sourceType(): string
    {
        return 'USOC_ALUMNE';
    }

    public function issueFromIntentSnapshot(\PDO $sifDb, string $dsOrder, array $snapshot): array
    {
        $entityAmount = $snapshot['usoc']['entity_amount'] ?? null;
        if ($entityAmount === null || !is_numeric($entityAmount) || (float) $entityAmount <= 0) {
            throw SifException::validation('Invalid Redsys USOC entity amount snapshot');
        }

        $basePayload = $this->legacyPayloads->buildStudentPayload($snapshot);
        $payload = $this->redsysPayloads->buildFromValidatedNotification($sifDb, $dsOrder, $basePayload);
        $result = $this->invoices->issueInvoice($payload);
        $result['entity_invoice_pending'] = [
            'source_type' => 'USOC_ENTITAT',
            'requires_explicit_billing' => true,
            'entity_amount' => number_format((float) $entityAmount, 2, '.', ''),
            'student_invoice_uuid' => $result['uuid_factura'],
            'idpag' => $payload['payment']['idpag'] ?? null,
        ];

        return $result;
    }

    public function issueStudentFromValidatedNotification(
        \PDO $sifDb,
        \PDO $legacyDb,
        string $dsOrder,
        mixed $usocAmount
    ): array {
        $usocAmount = $this->usocAmount($usocAmount);
        $notification = $this->validatedNotification($sifDb, $dsOrder);
        $idpag = $this->idpag($notification);
        $studentAmount = $this->amount($notification);

        $snapshot = $this->legacySnapshots->loadByIdpag($legacyDb, $idpag, $studentAmount, $usocAmount);
        $basePayload = $this->legacyPayloads->buildStudentPayload($snapshot);
        $payload = $this->redsysPayloads->buildFromValidatedNotification($sifDb, $dsOrder, $basePayload);
        $result = $this->invoices->issueInvoice($payload);
        $result['legacy_sync'] = [
            'relations' => $payload['relations'] ?? [],
            'estat_cobrament' => isset($payload['payment']) ? 'PAID' : 'PENDING',
        ];
        $result['entity_invoice_pending'] = [
            'source_type' => 'USOC_ENTITAT',
            'requires_explicit_billing' => true,
            'entity_amount' => $snapshot['usoc']['entity_amount'] ?? $usocAmount,
            'student_invoice_uuid' => $result['uuid_factura'],
            'idpag' => $idpag,
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
            throw SifException::validation('Validated Redsys USOC notification requires IDPAG');
        }

        $idpag = (int) $notification['IDPAG'];
        if ($idpag <= 0) {
            throw SifException::validation('Invalid Redsys USOC IDPAG');
        }

        return $idpag;
    }

    private function amount(array $notification): string
    {
        if (!array_key_exists('IMPORT', $notification) || !is_numeric($notification['IMPORT'])) {
            throw SifException::validation('Invalid Redsys USOC student amount');
        }

        return number_format((float) $notification['IMPORT'], 2, '.', '');
    }

    private function usocAmount(mixed $value): string
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            throw SifException::validation('Missing USOC entity amount');
        }

        $amount = (float) $value;
        if ($amount <= 0.0) {
            throw SifException::validation('Invalid USOC entity amount');
        }

        return number_format($amount, 2, '.', '');
    }
}
