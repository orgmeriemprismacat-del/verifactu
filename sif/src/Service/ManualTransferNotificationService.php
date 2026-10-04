<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\NotificationOutboxRepository;

final class ManualTransferNotificationService
{
    public const TEMPLATE_VERSION = '1';
    public const BUNDLE_VERSION = '1';

    private const INTERNAL = 'MANUAL_TRANSFER_CONFIRMED_INTERNAL';
    private const RESPONSIBLE = 'MANUAL_TRANSFER_CONFIRMED_RESPONSIBLE';
    private const INTERNAL_EMAIL = 'resguard.gestio@prisma.cat';

    public function __construct(private NotificationOutboxRepository $outbox)
    {
    }

    public function enqueue(
        \PDO $sifDb,
        \PDO $legacyDb,
        ?\PDO $legacyIntranetDb,
        array $paymentResult,
        array $legacySync,
        array $commandPayload
    ): array {
        $uuidFactura = trim((string) ($paymentResult['uuid_factura'] ?? ''));
        $uuidPayment = trim((string) ($paymentResult['uuid_payment'] ?? ''));
        $numVisible = trim((string) ($paymentResult['num_visible'] ?? ''));
        if ($uuidFactura === '' || $uuidPayment === '' || $numVisible === '') {
            throw SifException::conflict(
                'Manual transfer notification lacks persisted payment identity'
            );
        }

        $projectionStatus = strtoupper(trim((string) ($legacySync['status'] ?? '')));
        if (!in_array($projectionStatus, ['PARTIALLY_PAID', 'PAID'], true)) {
            throw SifException::conflict(
                'Manual transfer notification requires successful legacy projection'
            );
        }

        $invoice = $this->invoiceContext($legacyDb, $numVisible);
        $movementAmount = $this->amount(
            $commandPayload['amount'] ?? null,
            'Invalid manual transfer notification movement amount'
        );
        $confirmedAmount = $this->amount(
            $legacySync['confirmed_amount'] ?? null,
            'Invalid manual transfer notification confirmed amount'
        );
        $projectedAmount = $this->amount(
            $legacySync['projected_amount'] ?? null,
            'Invalid manual transfer notification projected amount'
        );
        $movementDate = trim((string) ($commandPayload['movement_date'] ?? ''));
        if ($movementDate === '') {
            throw SifException::validation(
                'Missing manual transfer notification movement date'
            );
        }

        $basePayload = [
            'source_type' => 'MANUAL_TRANSFER',
            'bundle_version' => self::BUNDLE_VERSION,
            'num_visible' => $numVisible,
            'entity_name' => trim((string) ($invoice['RAO'] ?? '')),
            'invoice_concept' => trim(implode(' · ', array_filter([
                trim((string) ($invoice['CONCEPTE1'] ?? '')),
                trim((string) ($invoice['CONCEPTE2'] ?? '')),
            ]))),
            'movement_amount' => $movementAmount,
            'movement_date' => $movementDate,
            'confirmed_amount' => $confirmedAmount,
            'projected_amount' => $projectedAmount,
            'payment_status' => $projectionStatus,
        ];

        $notifications = [];
        $internal = $this->enqueueOne(
            $sifDb,
            $uuidFactura,
            $uuidPayment,
            self::INTERNAL,
            self::INTERNAL_EMAIL,
            $basePayload + [
                'recipient_resolution' => 'FIXED_RESGUARD_GESTIO',
            ]
        );
        $notifications[] = $internal;

        $responsible = $legacyIntranetDb !== null
            ? $this->activeResponsible(
                $legacyIntranetDb,
                trim((string) ($invoice['CIF'] ?? ''))
            )
            : null;

        if ($responsible !== null) {
            $notifications[] = $this->enqueueOne(
                $sifDb,
                $uuidFactura,
                $uuidPayment,
                self::RESPONSIBLE,
                (string) $responsible['email'],
                $basePayload + [
                    'recipient_resolution' => 'LEGACY_INVOICE_ENTITY_RESPONSIBLE_BY_NUM_VISIBLE_AND_HASH',
                ]
            );
        }

        $allReused = true;
        foreach ($notifications as $notification) {
            $allReused = $allReused && (bool) $notification['idempotency_reused'];
        }

        return [
            'bundle_version' => self::BUNDLE_VERSION,
            'count' => count($notifications),
            'responsible_notification' => $responsible !== null ? 'QUEUED' : 'SKIPPED',
            'responsible_skip_reason' => $responsible !== null
                ? null
                : ($legacyIntranetDb === null
                    ? 'LEGACY_INTRANET_DB_NOT_CONFIGURED'
                    : 'ACTIVE_RESPONSIBLE_EMAIL_NOT_FOUND'),
            'notifications' => $notifications,
            'idempotency_reused' => $allReused,
        ];
    }

    private function enqueueOne(
        \PDO $sifDb,
        string $uuidFactura,
        string $uuidPayment,
        string $messageCode,
        string $recipient,
        array $payload
    ): array {
        $recipient = strtolower(trim($recipient));
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw SifException::validation(
                'Invalid manual transfer notification recipient'
            );
        }

        $shortCode = $messageCode === self::INTERNAL ? 'INTERNAL' : 'RESPONSIBLE';
        $result = $this->outbox->enqueue($sifDb, [
            'idempotency_key' => sprintf(
                'MT_MAIL|PAY:%s|MSG:%s|V1',
                $uuidPayment,
                $shortCode
            ),
            'template_code' => $messageCode,
            'template_version' => self::TEMPLATE_VERSION,
            'recipient_type' => 'EMAIL',
            'recipient_hash' => hash('sha256', $recipient),
            'uuid_factura' => $uuidFactura,
            'uuid_payment' => $uuidPayment,
            'correlation_id' => sprintf(
                'UC022-MAIL|PAY:%s|MSG:%s',
                $uuidPayment,
                $shortCode
            ),
            'payload' => $payload + [
                'message_code' => $messageCode,
            ],
        ]);

        return [
            'message_code' => $messageCode,
            'uuid_notification' => (string) $result['uuid_notification'],
            'status' => (string) $result['status'],
            'idempotency_reused' => (bool) $result['idempotency_reused'],
        ];
    }

    private function invoiceContext(\PDO $legacyDb, string $numVisible): array
    {
        $statement = $legacyDb->prepare(
            'SELECT CIF, RAO, CONCEPTE1, CONCEPTE2
             FROM factures
             WHERE num = ?
             LIMIT 1'
        );
        $statement->execute([$numVisible]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw SifException::conflict(
                'Legacy invoice context not found for manual transfer notification'
            );
        }

        if (trim((string) ($row['CIF'] ?? '')) === '') {
            throw SifException::conflict(
                'Legacy invoice has no entity identity for notification'
            );
        }

        return $row;
    }

    private function activeResponsible(
        \PDO $legacyIntranetDb,
        string $cif
    ): ?array {
        if ($cif === '') {
            return null;
        }

        $statement = $legacyIntranetDb->prepare(
            'SELECT e.RAO, r.NOM, r.COGNOMS, r.CORREU
             FROM entitats AS e
             INNER JOIN entitats_resp AS r ON r.ID_RESP = e.ID_RESP
             WHERE e.CIF = ?
               AND r.DATAI <= CURRENT_TIME
               AND (r.DATAF >= CURRENT_TIME OR r.DATAF IS NULL)
             LIMIT 1'
        );
        $statement->execute([$cif]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        $email = strtolower(trim((string) ($row['CORREU'] ?? '')));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return [
            'entity_name' => trim((string) ($row['RAO'] ?? '')),
            'name' => trim((string) ($row['NOM'] ?? '')),
            'surname' => trim((string) ($row['COGNOMS'] ?? '')),
            'email' => $email,
        ];
    }

    private function amount(mixed $value, string $message): string
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw SifException::validation($message);
        }

        [$euros, $decimals] = array_pad(explode('.', $raw, 2), 2, '');

        return (string) ((int) $euros)
            . '.'
            . str_pad($decimals, 2, '0');
    }
}
