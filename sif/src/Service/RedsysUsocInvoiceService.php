<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Domain\DecimalAmount;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\LegacyUsocSnapshotRepository;
use Prisma\Sif\Repository\UsocFinancingCaseRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;

final class RedsysUsocInvoiceService implements RedsysIntentHandler
{
    public function __construct(
        private RedsysNotificationRepository $notifications,
        private LegacyUsocSnapshotRepository $legacySnapshots,
        private LegacyUsocInvoicePayloadBuilder $legacyPayloads,
        private RedsysInvoicePayloadBuilder $redsysPayloads,
        private InvoiceService $invoices,
        ?UsocFinancingCaseRepository $cases = null
    ) {
        $this->cases = $cases ?? new UsocFinancingCaseRepository(new UuidGenerator());
    }

    private UsocFinancingCaseRepository $cases;

    public function sourceType(): string
    {
        return 'USOC_ALUMNE';
    }

    public function issueFromIntentSnapshot(\PDO $sifDb, string $dsOrder, array $snapshot): array
    {
        $entityAmount = $this->positiveMoney(
            $snapshot['usoc']['entity_amount'] ?? null,
            'Invalid Redsys USOC entity amount snapshot'
        );

        $basePayload = $this->legacyPayloads->buildStudentPayload($snapshot);
        $payload = $this->redsysPayloads->buildFromValidatedNotification($sifDb, $dsOrder, $basePayload);
        $result = $this->invoices->issueInvoice($payload);
        $inscriptionId = (int) ($snapshot['inscription']['ID'] ?? 0);
        $studentAmount = $this->positiveMoney(
            $payload['payment']['amount'] ?? null,
            'Invalid Redsys USOC student amount snapshot'
        );
        $case = $this->cases->recordStudentInvoice(
            $sifDb,
            $inscriptionId,
            (int) ($payload['payment']['idpag'] ?? 0),
            (string) $result['uuid_factura'],
            $studentAmount,
            $entityAmount,
            $dsOrder
        );
        $result['usoc_case'] = $case;
        $result['entity_invoice_pending'] = [
            'source_type' => 'USOC_ENTITAT',
            'requires_explicit_billing' => true,
            'entity_amount' => $entityAmount,
            'student_invoice_uuid' => $result['uuid_factura'],
            'idpag' => $payload['payment']['idpag'] ?? null,
            'id_insc' => (int) ($snapshot['inscription']['ID'] ?? 0),
        ];

        return $result;
    }

    public function issueStudentFromValidatedNotification(
        \PDO $sifDb,
        \PDO $legacyDb,
        string $dsOrder,
        mixed $usocAmount,
        int $inscriptionId
    ): array {
        $usocAmount = $this->usocAmount($usocAmount);
        $notification = $this->validatedNotification($sifDb, $dsOrder);
        $idpag = $this->idpag($notification);
        $studentAmount = $this->amount($notification);

        $snapshot = $this->legacySnapshots->loadByIdpag($legacyDb, $idpag, $studentAmount, $usocAmount, $inscriptionId);
        $basePayload = $this->legacyPayloads->buildStudentPayload($snapshot);
        $payload = $this->redsysPayloads->buildFromValidatedNotification($sifDb, $dsOrder, $basePayload);
        $result = $this->invoices->issueInvoice($payload);
        $case = $this->cases->recordStudentInvoice(
            $sifDb,
            (int) $snapshot['inscription']['ID'],
            $idpag,
            (string) $result['uuid_factura'],
            $studentAmount,
            (string) ($snapshot['usoc']['entity_amount'] ?? $usocAmount),
            $dsOrder
        );
        $result['usoc_case'] = $case;
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
            'id_insc' => (int) $snapshot['inscription']['ID'],
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
        if (!array_key_exists('IMPORT', $notification)) {
            throw SifException::validation('Invalid Redsys USOC student amount');
        }

        return $this->positiveMoney(
            $notification['IMPORT'],
            'Invalid Redsys USOC student amount'
        );
    }

    private function usocAmount(mixed $value): string
    {
        if ($value === null || $value === '') {
            throw SifException::validation('Missing USOC entity amount');
        }

        return $this->positiveMoney($value, 'Invalid USOC entity amount');
    }

    private function positiveMoney(mixed $value, string $message): string
    {
        try {
            $cents = DecimalAmount::cents($value);
        } catch (\InvalidArgumentException) {
            throw SifException::validation($message);
        }

        if ($cents <= 0) {
            throw SifException::validation($message);
        }

        return DecimalAmount::format($cents);
    }
}
