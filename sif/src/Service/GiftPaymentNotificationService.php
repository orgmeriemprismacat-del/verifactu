<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\NotificationOutboxRepository;

final class GiftPaymentNotificationService
{
    public function __construct(private NotificationOutboxRepository $outbox)
    {
    }

    public function enqueue(
        \PDO $db,
        string $dsOrder,
        array $snapshot,
        array $invoiceResult,
        array $entitlementResult
    ): array {
        $gift = $snapshot['gift'] ?? null;
        if (!is_array($gift)) {
            throw SifException::validation('Invalid gift notification snapshot');
        }

        $giftId = $this->positiveInt($gift['ID'] ?? $gift['id'] ?? null, 'gift notification ID');
        $email = strtolower(trim((string) ($gift['MAILC'] ?? $gift['email'] ?? $gift['mailc'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw SifException::validation('Invalid gift notification buyer email');
        }

        $uuidFactura = trim((string) ($invoiceResult['uuid_factura'] ?? ''));
        $uuidPayment = trim((string) ($invoiceResult['uuid_payment'] ?? ''));
        $uuidEntitlement = trim((string) ($entitlementResult['uuid_entitlement'] ?? ''));
        if ($uuidFactura === '' || $uuidPayment === '' || $uuidEntitlement === '') {
            throw SifException::conflict('Gift notification lacks persisted invoice/payment/entitlement identity');
        }

        $dsOrder = trim($dsOrder);
        if ($dsOrder === '') {
            throw SifException::validation('Missing gift notification DS_ORDER');
        }

        return $this->outbox->enqueue($db, [
            'idempotency_key' => 'NOTIFY|GIFT_PAYMENT_CONFIRMED|ORDER:' . $dsOrder,
            'template_code' => 'GIFT_PAYMENT_CONFIRMED',
            'template_version' => 'v1',
            'recipient_type' => 'COMPRADOR',
            'recipient_hash' => hash('sha256', $email),
            'uuid_factura' => $uuidFactura,
            'uuid_payment' => $uuidPayment,
            'correlation_id' => 'REDSYS|' . $dsOrder,
            'payload' => [
                'source_type' => 'REGAL',
                'gift_id' => $giftId,
                'ds_order' => $dsOrder,
                'uuid_entitlement' => $uuidEntitlement,
                'holder_state' => (string) ($entitlementResult['holder_state'] ?? ''),
                'entitlement_status' => (string) ($entitlementResult['status'] ?? ''),
                'num_visible' => (string) ($invoiceResult['num_visible'] ?? ''),
                'recipient_resolution' => 'LEGACY_GIFT_BUYER_EMAIL_HASH',
                'gift_code_in_payload' => false,
            ],
        ]);
    }

    private function positiveInt(mixed $value, string $label): int
    {
        if (!is_numeric($value) || (int) $value <= 0) {
            throw SifException::validation('Invalid ' . $label);
        }

        return (int) $value;
    }
}
