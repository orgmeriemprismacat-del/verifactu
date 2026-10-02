<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\NotificationOutboxRepository;

/**
 * Materializes the six legacy UC-018 SMTP side effects as six independent
 * durable outbox rows. Each message can therefore be claimed/completed
 * separately: a partial SMTP failure never forces the already-sent messages to
 * be sent again and never blocks unrelated pending messages in the same bundle.
 */
final class GiftRedemptionNotificationBundleService
{
    public const TEMPLATE_VERSION = '1';
    public const BUNDLE_VERSION = '2';

    private const INTERNAL_DETAIL_PRIMARY = 'GIFT_REDEEM_INTERNAL_DETAIL_PRIMARY';
    private const RESGUARD_PRIMARY = 'GIFT_REDEEM_RESGUARD_PRIMARY';
    private const SECRETARY_CONFIRMATION = 'GIFT_REDEEM_SECRETARY_CONFIRMATION';
    private const INTERNAL_DETAIL_GMAIL = 'GIFT_REDEEM_INTERNAL_DETAIL_GMAIL';
    private const RESGUARD_SECONDARY = 'GIFT_REDEEM_RESGUARD_SECONDARY';
    private const STUDENT_CONFIRMATION = 'GIFT_REDEEM_STUDENT_CONFIRMATION';

    public function __construct(private NotificationOutboxRepository $outbox)
    {
    }

    public function enqueue(
        \PDO $sifDb,
        \PDO $legacyDb,
        int $enrollmentId,
        array $execution
    ): array {
        if ($enrollmentId < 1) {
            throw SifException::validation('Invalid gift enrollment notification ID');
        }

        $stage = $execution['stage'] ?? null;
        $redemption = $execution['redemption'] ?? null;
        $legacyReconciliation = $execution['legacy_reconciliation'] ?? null;

        if (!is_array($stage)
            || !is_array($redemption)
            || !is_array($legacyReconciliation)
            || strtoupper((string) ($redemption['status'] ?? '')) !== 'CONSUMED'
            || strtoupper((string) ($legacyReconciliation['status'] ?? '')) !== 'RECONCILED'
        ) {
            throw SifException::conflict(
                'Gift notification cannot be enqueued before successful reconciliation'
            );
        }

        $uuidOperation = trim((string) ($stage['uuid_operation'] ?? ''));
        $uuidEntitlement = trim((string) ($stage['uuid_entitlement'] ?? ''));
        if ($uuidOperation === '' || $uuidEntitlement === '') {
            throw SifException::validation('Gift notification references are incomplete');
        }

        $statement = $legacyDb->prepare(
            'SELECT CORREU FROM inscripcions WHERE ID = ?'
        );
        $statement->execute([$enrollmentId]);
        $studentEmail = trim(strtolower((string) $statement->fetchColumn()));
        if ($studentEmail === ''
            || filter_var($studentEmail, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw SifException::conflict(
                'Gift enrollment has no valid notification email'
            );
        }

        $messages = [
            [
                'message_code' => self::INTERNAL_DETAIL_PRIMARY,
                'recipient' => 'inscripcions@prisma.cat',
            ],
            [
                'message_code' => self::RESGUARD_PRIMARY,
                'recipient' => 'resguard.secretaria@prisma.cat',
            ],
            [
                'message_code' => self::SECRETARY_CONFIRMATION,
                'recipient' => 'inscripcions@prisma.cat',
            ],
            [
                'message_code' => self::INTERNAL_DETAIL_GMAIL,
                'recipient' => 'inscripcions.prisma@gmail.com',
            ],
            [
                'message_code' => self::RESGUARD_SECONDARY,
                'recipient' => 'resguard.secretaria@prisma.cat',
            ],
            [
                'message_code' => self::STUDENT_CONFIRMATION,
                'recipient' => $studentEmail,
            ],
        ];

        $notifications = [];
        $allReused = true;

        foreach ($messages as $message) {
            $messageCode = (string) $message['message_code'];
            $recipient = strtolower(trim((string) $message['recipient']));

            $result = $this->outbox->enqueue(
                $sifDb,
                [
                    'idempotency_key' => sprintf(
                        'GIFT_MAIL|ENT:%s|INSC:%d|MSG:%s|V1',
                        $uuidEntitlement,
                        $enrollmentId,
                        $messageCode
                    ),
                    'template_code' => $messageCode,
                    'template_version' => self::TEMPLATE_VERSION,
                    'recipient_type' => 'EMAIL',
                    'recipient_hash' => hash('sha256', $recipient),
                    'payload' => [
                        'enrollment_id' => $enrollmentId,
                        'uuid_operation' => $uuidOperation,
                        'uuid_entitlement' => $uuidEntitlement,
                        'bundle_version' => self::BUNDLE_VERSION,
                        'message_code' => $messageCode,
                    ],
                    'correlation_id' => sprintf(
                        'UC018-MAIL|ENT:%s|INSC:%d|MSG:%s',
                        $uuidEntitlement,
                        $enrollmentId,
                        $messageCode
                    ),
                ]
            );

            $notifications[] = [
                'message_code' => $messageCode,
                'uuid_notification' => (string) $result['uuid_notification'],
                'status' => (string) $result['status'],
                'idempotency_reused' => (bool) $result['idempotency_reused'],
            ];
            $allReused = $allReused && (bool) $result['idempotency_reused'];
        }

        return [
            'bundle_version' => self::BUNDLE_VERSION,
            'count' => count($notifications),
            'notifications' => $notifications,
            'idempotency_reused' => $allReused,
        ];
    }
}
