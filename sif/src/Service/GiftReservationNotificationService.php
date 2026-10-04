<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\NotificationOutboxRepository;

final class GiftReservationNotificationService
{
    public function __construct(private NotificationOutboxRepository $outbox)
    {
    }

    public function enqueueBundle(\PDO $db, array $snapshot): array
    {
        $gift = $snapshot['gift'] ?? null;
        if (!is_array($gift)) {
            throw SifException::validation('Invalid gift reservation notification snapshot');
        }

        $giftId = $this->positiveInt($gift['ID'] ?? null, 'gift.ID');
        $buyerEmail = strtolower(trim((string) ($gift['MAILC'] ?? '')));
        if ($buyerEmail === '' || !filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
            throw SifException::validation('Invalid gift reservation buyer email');
        }

        $courseCode = trim((string) ($gift['CCURS'] ?? ''));
        $courseTitle = trim((string) ($gift['NOM_CURS'] ?? ''));
        $amount = $this->money($gift['IMPORT'] ?? null);
        if ($courseCode === '' || $courseTitle === '') {
            throw SifException::validation('Missing gift reservation course identity');
        }

        $basePayload = [
            'source_type' => 'REGAL',
            'gift_id' => $giftId,
            'course_code' => $courseCode,
            'course_title' => $courseTitle,
            'amount' => $amount,
            'currency' => 'EUR',
            'gift_code_in_payload' => false,
        ];

        $definitions = [
            [
                'message_code' => 'GIFT_RESERVATION_BUYER_CONFIRMATION',
                'recipient_type' => 'COMPRADOR',
                'recipient_hash' => hash('sha256', $buyerEmail),
                'recipient_resolution' => 'LEGACY_GIFT_BUYER_EMAIL_HASH',
            ],
            [
                'message_code' => 'GIFT_RESERVATION_INTERNAL_CONFIRMATION',
                'recipient_type' => 'GESTIO',
                'recipient_hash' => hash('sha256', 'gestio@prisma.cat'),
                'recipient_resolution' => 'STATIC_GESTIO_EMAIL_HASH',
            ],
        ];

        $notifications = [];
        foreach ($definitions as $definition) {
            $messageCode = $definition['message_code'];
            $queued = $this->outbox->enqueue($db, [
                'idempotency_key' => 'NOTIFY|' . $messageCode . '|GIFT:' . $giftId,
                'template_code' => $messageCode,
                'template_version' => 'v1',
                'recipient_type' => $definition['recipient_type'],
                'recipient_hash' => $definition['recipient_hash'],
                'correlation_id' => 'GIFT_RESERVATION|' . $giftId,
                'payload' => $basePayload + [
                    'message_code' => $messageCode,
                    'recipient_resolution' => $definition['recipient_resolution'],
                ],
            ]);

            $notifications[] = [
                'message_code' => $messageCode,
                'uuid_notification' => $queued['uuid_notification'],
                'status' => $queued['status'],
                'idempotency_reused' => $queued['idempotency_reused'],
            ];
        }

        return [
            'gift_id' => $giftId,
            'notifications' => $notifications,
        ];
    }

    private function positiveInt(mixed $value, string $label): int
    {
        if (!is_numeric($value) || (int) $value <= 0) {
            throw SifException::validation('Invalid ' . $label);
        }

        return (int) $value;
    }

    private function money(mixed $value): string
    {
        if (!is_numeric($value) || (float) $value <= 0) {
            throw SifException::validation('Invalid gift reservation amount');
        }

        return number_format((float) $value, 2, '.', '');
    }
}
