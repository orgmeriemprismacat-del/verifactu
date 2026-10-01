<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\NotificationOutboxRepository;

/**
 * Creates one durable notification intent for the complete legacy UC-018 mail
 * bundle. The actual SMTP bodies remain in the legacy web code, but the SIF
 * owns whether that external side effect may be attempted.
 */
final class GiftRedemptionNotificationBundleService
{
    public const TEMPLATE_CODE = 'GIFT_REDEMPTION_LEGACY_MAIL_BUNDLE';
    public const TEMPLATE_VERSION = '1';

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
        $email = trim(strtolower((string) $statement->fetchColumn()));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw SifException::conflict(
                'Gift enrollment has no valid notification email'
            );
        }

        $recipients = [
            $email,
            'inscripcions@prisma.cat',
            'inscripcions.prisma@gmail.com',
            'resguard.secretaria@prisma.cat',
        ];
        sort($recipients, SORT_STRING);
        $recipientHash = hash('sha256', implode('|', $recipients));

        $idempotencyKey = sprintf(
            'GIFT_MAIL_BUNDLE|ENT:%s|INSC:%d|V1',
            $uuidEntitlement,
            $enrollmentId
        );
        $correlationId = sprintf(
            'UC018-MAIL|ENT:%s|INSC:%d',
            $uuidEntitlement,
            $enrollmentId
        );

        return $this->outbox->enqueue(
            $sifDb,
            [
                'idempotency_key' => $idempotencyKey,
                'template_code' => self::TEMPLATE_CODE,
                'template_version' => self::TEMPLATE_VERSION,
                'recipient_type' => 'MULTI_EMAIL',
                'recipient_hash' => $recipientHash,
                'payload' => [
                    'enrollment_id' => $enrollmentId,
                    'uuid_operation' => $uuidOperation,
                    'uuid_entitlement' => $uuidEntitlement,
                    'bundle_version' => self::TEMPLATE_VERSION,
                    'recipient_count' => count($recipients),
                ],
                'correlation_id' => $correlationId,
            ]
        );
    }
}
