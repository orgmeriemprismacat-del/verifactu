<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

/**
 * Conservative outbox delivery gate.
 *
 * A PENDING notification can be claimed once. If the process disappears while
 * the row is SENDING, the state is treated as ambiguous and is NOT retried
 * automatically. This deliberately prefers a manual reconciliation over a
 * duplicated external side effect.
 */
final class NotificationOutboxDeliveryService
{
    public function __construct(private UuidGenerator $uuids)
    {
    }

    public function claim(
        \PDO $db,
        string $uuidNotification,
        string $channel = 'SMTP'
    ): array {
        $uuidNotification = trim($uuidNotification);
        $channel = strtoupper(trim($channel));

        if ($uuidNotification === ''
            || strlen($uuidNotification) > 36
            || $channel === ''
            || strlen($channel) > 30
        ) {
            throw SifException::validation('Invalid notification delivery claim');
        }

        if ($db->inTransaction()) {
            throw new \LogicException('Notification delivery claim owns its transaction');
        }

        $db->beginTransaction();
        try {
            $row = $this->one(
                $db,
                'SELECT UUID_NOTIFICATION, STATUS, TEMPLATE_CODE, TEMPLATE_VERSION,
                        PAYLOAD_JSON, NEXT_ATTEMPT_AT
                 FROM notification_outbox
                 WHERE UUID_NOTIFICATION = ? FOR UPDATE',
                [$uuidNotification]
            );
            if ($row === null) {
                throw SifException::notFound('Notification outbox row not found');
            }

            $status = strtoupper((string) $row['STATUS']);
            if ($status === 'SENT') {
                $db->commit();

                return [
                    'should_send' => false,
                    'status' => 'SENT',
                    'reason' => 'ALREADY_SENT',
                    'uuid_notification' => $uuidNotification,
                ];
            }

            if ($status === 'SENDING') {
                $db->commit();

                return [
                    'should_send' => false,
                    'status' => 'SENDING',
                    'reason' => 'AMBIGUOUS_IN_FLIGHT_REQUIRES_REVIEW',
                    'uuid_notification' => $uuidNotification,
                ];
            }

            if ($status === 'FAILED') {
                $db->commit();

                return [
                    'should_send' => false,
                    'status' => 'FAILED',
                    'reason' => 'FAILED_REQUIRES_REVIEW',
                    'uuid_notification' => $uuidNotification,
                ];
            }

            if ($status !== 'PENDING') {
                throw SifException::conflict(
                    'Notification cannot be claimed from current status'
                );
            }

            $nextAttemptAt = trim((string) ($row['NEXT_ATTEMPT_AT'] ?? ''));
            if ($nextAttemptAt !== ''
                && $nextAttemptAt > (new \DateTimeImmutable(
                    'now',
                    new \DateTimeZone('Europe/Madrid')
                ))->format('Y-m-d H:i:s.u')
            ) {
                $db->commit();

                return [
                    'should_send' => false,
                    'status' => 'PENDING',
                    'reason' => 'NOT_DUE_YET',
                    'uuid_notification' => $uuidNotification,
                ];
            }

            $attemptNo = (int) $this->scalar(
                $db,
                'SELECT COALESCE(MAX(ATTEMPT_NO), 0) + 1
                 FROM notification_delivery_attempt
                 WHERE UUID_NOTIFICATION = ?',
                [$uuidNotification]
            );
            $uuidAttempt = $this->uuids->generate();

            $this->execute(
                $db,
                'INSERT INTO notification_delivery_attempt
                 (UUID_DELIVERY_ATTEMPT, UUID_NOTIFICATION, ATTEMPT_NO, CHANNEL,
                  STATUS, STARTED_AT)
                 VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP(6))',
                [
                    $uuidAttempt,
                    $uuidNotification,
                    $attemptNo,
                    $channel,
                    'SENDING',
                ]
            );
            $this->execute(
                $db,
                "UPDATE notification_outbox
                 SET STATUS = 'SENDING'
                 WHERE UUID_NOTIFICATION = ? AND STATUS = 'PENDING'",
                [$uuidNotification]
            );

            $db->commit();

            return [
                'should_send' => true,
                'status' => 'SENDING',
                'reason' => 'CLAIMED',
                'uuid_notification' => $uuidNotification,
                'uuid_delivery_attempt' => $uuidAttempt,
                'attempt_no' => $attemptNo,
                'template_code' => (string) $row['TEMPLATE_CODE'],
                'template_version' => (string) $row['TEMPLATE_VERSION'],
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    public function complete(
        \PDO $db,
        string $uuidNotification,
        string $uuidAttempt,
        bool $accepted,
        ?string $providerRef = null,
        ?string $errorCode = null
    ): array {
        $uuidNotification = trim($uuidNotification);
        $uuidAttempt = trim($uuidAttempt);
        $providerRef = $this->optional($providerRef, 120);
        $errorCode = $this->optional($errorCode, 80);

        if ($uuidNotification === '' || $uuidAttempt === '') {
            throw SifException::validation('Invalid notification delivery completion');
        }

        if ($db->inTransaction()) {
            throw new \LogicException('Notification delivery completion owns its transaction');
        }

        $db->beginTransaction();
        try {
            $outbox = $this->one(
                $db,
                'SELECT STATUS FROM notification_outbox
                 WHERE UUID_NOTIFICATION = ? FOR UPDATE',
                [$uuidNotification]
            );
            $attempt = $this->one(
                $db,
                'SELECT STATUS FROM notification_delivery_attempt
                 WHERE UUID_DELIVERY_ATTEMPT = ?
                   AND UUID_NOTIFICATION = ?
                 FOR UPDATE',
                [$uuidAttempt, $uuidNotification]
            );

            if ($outbox === null || $attempt === null) {
                throw SifException::notFound('Notification delivery claim not found');
            }

            $outboxStatus = strtoupper((string) $outbox['STATUS']);
            $attemptStatus = strtoupper((string) $attempt['STATUS']);

            if ($outboxStatus === 'SENT' && $attemptStatus === 'SENT') {
                $db->commit();

                return [
                    'uuid_notification' => $uuidNotification,
                    'status' => 'SENT',
                    'idempotency_reused' => true,
                ];
            }

            if ($outboxStatus !== 'SENDING' || $attemptStatus !== 'SENDING') {
                throw SifException::conflict(
                    'Notification delivery completion does not match active claim'
                );
            }

            $finalStatus = $accepted ? 'SENT' : 'FAILED';
            $finalErrorCode = $accepted
                ? null
                : ($errorCode ?? 'SMTP_SEND_FAILED');

            $this->execute(
                $db,
                'UPDATE notification_delivery_attempt
                 SET STATUS = ?, PROVIDER_REF = ?, ERROR_CODE = ?,
                     FINISHED_AT = CURRENT_TIMESTAMP(6)
                 WHERE UUID_DELIVERY_ATTEMPT = ?
                   AND UUID_NOTIFICATION = ?',
                [
                    $finalStatus,
                    $providerRef,
                    $finalErrorCode,
                    $uuidAttempt,
                    $uuidNotification,
                ]
            );

            if ($accepted) {
                $this->execute(
                    $db,
                    "UPDATE notification_outbox
                     SET STATUS = 'SENT', SENT_AT = CURRENT_TIMESTAMP(6),
                         NEXT_ATTEMPT_AT = NULL
                     WHERE UUID_NOTIFICATION = ?",
                    [$uuidNotification]
                );
            } else {
                $this->execute(
                    $db,
                    "UPDATE notification_outbox
                     SET STATUS = 'FAILED', NEXT_ATTEMPT_AT = NULL
                     WHERE UUID_NOTIFICATION = ?",
                    [$uuidNotification]
                );
            }

            $db->commit();

            return [
                'uuid_notification' => $uuidNotification,
                'status' => $finalStatus,
                'idempotency_reused' => false,
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    private function one(\PDO $db, string $sql, array $params): ?array
    {
        $statement = $db->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function scalar(\PDO $db, string $sql, array $params): mixed
    {
        $statement = $db->prepare($sql);
        $statement->execute($params);

        return $statement->fetchColumn();
    }

    private function execute(\PDO $db, string $sql, array $params): void
    {
        $statement = $db->prepare($sql);
        $statement->execute($params);
    }

    private function optional(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (strlen($value) > $maxLength) {
            throw SifException::validation('Notification delivery metadata too long');
        }

        return $value;
    }
}
