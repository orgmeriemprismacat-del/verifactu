<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

final class NotificationOutboxRepository
{
    public function __construct(private UuidGenerator $uuidGenerator)
    {
    }

    public function enqueue(\PDO $db, array $message): array
    {
        foreach ([
            'idempotency_key',
            'template_code',
            'template_version',
            'recipient_type',
            'recipient_hash',
            'payload',
            'correlation_id',
        ] as $field) {
            if (!array_key_exists($field, $message) || $message[$field] === '' || $message[$field] === null) {
                throw SifException::validation('Missing notification outbox field ' . $field);
            }
        }
        if (!is_array($message['payload'])) {
            throw SifException::validation('Invalid notification outbox payload');
        }

        $payloadJson = json_encode(
            $message['payload'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
        if ($payloadJson === false) {
            throw SifException::validation('Notification outbox payload is not serializable');
        }

        $normalized = [
            'uuid_notification' => $this->uuidGenerator->generate(),
            'idempotency_key' => trim((string) $message['idempotency_key']),
            'template_code' => trim((string) $message['template_code']),
            'template_version' => trim((string) $message['template_version']),
            'recipient_type' => strtoupper(trim((string) $message['recipient_type'])),
            'recipient_hash' => strtolower(trim((string) $message['recipient_hash'])),
            'payload_json' => $payloadJson,
            'uuid_factura' => $this->optionalString($message['uuid_factura'] ?? null),
            'uuid_payment' => $this->optionalString($message['uuid_payment'] ?? null),
            'correlation_id' => trim((string) $message['correlation_id']),
        ];

        if (!preg_match('/^[a-f0-9]{64}$/D', $normalized['recipient_hash'])) {
            throw SifException::validation('Invalid notification recipient hash');
        }

        $existing = $this->findByIdempotencyKey($db, $normalized['idempotency_key']);
        if ($existing !== null) {
            $this->assertSameMessage($existing, $normalized);
            return $this->result($existing, true);
        }

        try {
            $stmt = $db->prepare(
                'INSERT INTO notification_outbox
                    (UUID_NOTIFICATION, IDEMPOTENCY_KEY, TEMPLATE_CODE, TEMPLATE_VERSION,
                     RECIPIENT_TYPE, RECIPIENT_HASH, PAYLOAD_JSON, UUID_FACTURA,
                     UUID_PAYMENT, STATUS, CORRELATION_ID)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'PENDING\', ?)'
            );
            $stmt->execute([
                $normalized['uuid_notification'],
                $normalized['idempotency_key'],
                $normalized['template_code'],
                $normalized['template_version'],
                $normalized['recipient_type'],
                $normalized['recipient_hash'],
                $normalized['payload_json'],
                $normalized['uuid_factura'],
                $normalized['uuid_payment'],
                $normalized['correlation_id'],
            ]);
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            $existing = $this->findByIdempotencyKey($db, $normalized['idempotency_key']);
            if ($existing === null) {
                throw $exception;
            }
            $this->assertSameMessage($existing, $normalized);
            return $this->result($existing, true);
        }

        $created = $this->findByIdempotencyKey($db, $normalized['idempotency_key']);
        if ($created === null) {
            throw new \RuntimeException('Created notification outbox row could not be loaded');
        }

        return $this->result($created, false);
    }

    public function findByIdempotencyKey(\PDO $db, string $key): ?array
    {
        $stmt = $db->prepare(
            'SELECT UUID_NOTIFICATION, IDEMPOTENCY_KEY, TEMPLATE_CODE, TEMPLATE_VERSION,
                    RECIPIENT_TYPE, RECIPIENT_HASH, PAYLOAD_JSON, UUID_FACTURA,
                    UUID_PAYMENT, STATUS, CORRELATION_ID
             FROM notification_outbox
             WHERE IDEMPOTENCY_KEY = ?'
        );
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function claimNext(\PDO $db, string $channel = 'EMAIL'): ?array
    {
        $channel = strtoupper(trim($channel));
        if ($channel === '' || strlen($channel) > 30) {
            throw SifException::validation('Invalid notification delivery channel');
        }

        $stmt = $db->prepare(
            "SELECT ID, UUID_NOTIFICATION, IDEMPOTENCY_KEY, TEMPLATE_CODE,
                    TEMPLATE_VERSION, RECIPIENT_TYPE, RECIPIENT_HASH,
                    PAYLOAD_JSON, UUID_FACTURA, UUID_PAYMENT, STATUS,
                    NEXT_ATTEMPT_AT, CORRELATION_ID
             FROM notification_outbox
             WHERE STATUS IN ('PENDING', 'RETRY')
               AND (NEXT_ATTEMPT_AT IS NULL OR NEXT_ATTEMPT_AT <= NOW(6))
             ORDER BY ID
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $attemptNoStmt = $db->prepare(
            'SELECT COALESCE(MAX(ATTEMPT_NO), 0) + 1
             FROM notification_delivery_attempt
             WHERE UUID_NOTIFICATION = ?'
        );
        $attemptNoStmt->execute([(string) $row['UUID_NOTIFICATION']]);
        $attemptNo = (int) $attemptNoStmt->fetchColumn();
        if ($attemptNo <= 0) {
            throw new \RuntimeException(
                'Could not allocate notification delivery attempt number'
            );
        }

        $attemptUuid = $this->uuidGenerator->generate();
        $insert = $db->prepare(
            "INSERT INTO notification_delivery_attempt
             (UUID_DELIVERY_ATTEMPT, UUID_NOTIFICATION, ATTEMPT_NO,
              CHANNEL, STATUS, STARTED_AT)
             VALUES (?, ?, ?, ?, 'STARTED', NOW(6))"
        );
        $insert->execute([
            $attemptUuid,
            (string) $row['UUID_NOTIFICATION'],
            $attemptNo,
            $channel,
        ]);

        $update = $db->prepare(
            "UPDATE notification_outbox
             SET STATUS = 'PROCESSING', NEXT_ATTEMPT_AT = NULL
             WHERE ID = ? AND STATUS IN ('PENDING', 'RETRY')"
        );
        $update->execute([(int) $row['ID']]);
        if ($update->rowCount() !== 1) {
            throw new \RuntimeException(
                'Notification outbox item could not be claimed'
            );
        }

        $row['STATUS'] = 'PROCESSING';
        $row['ATTEMPT_UUID'] = $attemptUuid;
        $row['ATTEMPT_NO'] = $attemptNo;
        $row['CHANNEL'] = $channel;

        return $row;
    }

    public function acknowledgeSent(
        \PDO $db,
        string $attemptUuid,
        ?string $providerRef = null
    ): array {
        $attempt = $this->deliveryAttemptForUpdate($db, $attemptUuid);
        $providerRef = $this->boundedOptional($providerRef, 120);

        if ((string) $attempt['STATUS'] === 'SENT') {
            $outbox = $this->outboxForUpdate(
                $db,
                (string) $attempt['UUID_NOTIFICATION']
            );
            if ((string) $outbox['STATUS'] !== 'SENT'
                || (string) ($attempt['PROVIDER_REF'] ?? '')
                    !== (string) ($providerRef ?? '')
            ) {
                throw SifException::conflict(
                    'Notification delivery acknowledgement conflicts with stored result'
                );
            }

            return [
                'uuid_notification' => (string) $outbox['UUID_NOTIFICATION'],
                'attempt_uuid' => $attemptUuid,
                'status' => 'SENT',
                'idempotency_reused' => true,
            ];
        }

        $outbox = $this->assertActiveDeliveryOwnership($db, $attempt);

        $updateAttempt = $db->prepare(
            "UPDATE notification_delivery_attempt
             SET STATUS = 'SENT', PROVIDER_REF = ?, FINISHED_AT = NOW(6),
                 ERROR_CODE = NULL, ERROR_DETAIL = NULL
             WHERE UUID_DELIVERY_ATTEMPT = ? AND STATUS = 'STARTED'"
        );
        $updateAttempt->execute([$providerRef, $attemptUuid]);
        if ($updateAttempt->rowCount() !== 1) {
            throw SifException::conflict(
                'Notification delivery attempt changed before acknowledgement'
            );
        }

        $updateOutbox = $db->prepare(
            "UPDATE notification_outbox
             SET STATUS = 'SENT', SENT_AT = NOW(6), NEXT_ATTEMPT_AT = NULL
             WHERE UUID_NOTIFICATION = ? AND STATUS = 'PROCESSING'"
        );
        $updateOutbox->execute([(string) $outbox['UUID_NOTIFICATION']]);
        if ($updateOutbox->rowCount() !== 1) {
            throw SifException::conflict(
                'Notification outbox ownership was lost before acknowledgement'
            );
        }

        return [
            'uuid_notification' => (string) $outbox['UUID_NOTIFICATION'],
            'attempt_uuid' => $attemptUuid,
            'status' => 'SENT',
            'idempotency_reused' => false,
        ];
    }

    public function failBeforeSend(
        \PDO $db,
        string $attemptUuid,
        string $errorCode,
        string $errorDetail,
        string $nextAttemptAt
    ): array {
        $attempt = $this->deliveryAttemptForUpdate($db, $attemptUuid);
        $errorCode = $this->boundedRequired($errorCode, 'error_code', 80);
        $errorDetail = $this->boundedRequired(
            $errorDetail,
            'error_detail',
            2000
        );
        $nextAttemptAt = $this->dateTime($nextAttemptAt);

        if ((string) $attempt['STATUS'] === 'FAILED') {
            $outbox = $this->outboxForUpdate(
                $db,
                (string) $attempt['UUID_NOTIFICATION']
            );
            if ((string) $outbox['STATUS'] !== 'RETRY') {
                throw SifException::conflict(
                    'Notification retry state conflicts with failed attempt'
                );
            }

            return [
                'uuid_notification' => (string) $outbox['UUID_NOTIFICATION'],
                'attempt_uuid' => $attemptUuid,
                'status' => 'RETRY',
                'idempotency_reused' => true,
            ];
        }

        $outbox = $this->assertActiveDeliveryOwnership($db, $attempt);

        $updateAttempt = $db->prepare(
            "UPDATE notification_delivery_attempt
             SET STATUS = 'FAILED', ERROR_CODE = ?, ERROR_DETAIL = ?,
                 FINISHED_AT = NOW(6)
             WHERE UUID_DELIVERY_ATTEMPT = ? AND STATUS = 'STARTED'"
        );
        $updateAttempt->execute([
            $errorCode,
            $errorDetail,
            $attemptUuid,
        ]);
        if ($updateAttempt->rowCount() !== 1) {
            throw SifException::conflict(
                'Notification delivery attempt changed before retry scheduling'
            );
        }

        $updateOutbox = $db->prepare(
            "UPDATE notification_outbox
             SET STATUS = 'RETRY', NEXT_ATTEMPT_AT = ?, SENT_AT = NULL
             WHERE UUID_NOTIFICATION = ? AND STATUS = 'PROCESSING'"
        );
        $updateOutbox->execute([
            $nextAttemptAt,
            (string) $outbox['UUID_NOTIFICATION'],
        ]);
        if ($updateOutbox->rowCount() !== 1) {
            throw SifException::conflict(
                'Notification outbox ownership was lost before retry scheduling'
            );
        }

        return [
            'uuid_notification' => (string) $outbox['UUID_NOTIFICATION'],
            'attempt_uuid' => $attemptUuid,
            'status' => 'RETRY',
            'idempotency_reused' => false,
        ];
    }

    public function markUncertain(
        \PDO $db,
        string $attemptUuid,
        string $errorCode,
        string $errorDetail
    ): array {
        $attempt = $this->deliveryAttemptForUpdate($db, $attemptUuid);
        $errorCode = $this->boundedRequired($errorCode, 'error_code', 80);
        $errorDetail = $this->boundedRequired(
            $errorDetail,
            'error_detail',
            2000
        );

        if ((string) $attempt['STATUS'] === 'UNCERTAIN') {
            $outbox = $this->outboxForUpdate(
                $db,
                (string) $attempt['UUID_NOTIFICATION']
            );
            if ((string) $outbox['STATUS'] !== 'REVIEW') {
                throw SifException::conflict(
                    'Notification review state conflicts with uncertain attempt'
                );
            }

            return [
                'uuid_notification' => (string) $outbox['UUID_NOTIFICATION'],
                'attempt_uuid' => $attemptUuid,
                'status' => 'REVIEW',
                'idempotency_reused' => true,
            ];
        }

        $outbox = $this->assertActiveDeliveryOwnership($db, $attempt);

        $updateAttempt = $db->prepare(
            "UPDATE notification_delivery_attempt
             SET STATUS = 'UNCERTAIN', ERROR_CODE = ?, ERROR_DETAIL = ?,
                 FINISHED_AT = NOW(6)
             WHERE UUID_DELIVERY_ATTEMPT = ? AND STATUS = 'STARTED'"
        );
        $updateAttempt->execute([
            $errorCode,
            $errorDetail,
            $attemptUuid,
        ]);
        if ($updateAttempt->rowCount() !== 1) {
            throw SifException::conflict(
                'Notification delivery attempt changed before review hold'
            );
        }

        $updateOutbox = $db->prepare(
            "UPDATE notification_outbox
             SET STATUS = 'REVIEW', NEXT_ATTEMPT_AT = NULL
             WHERE UUID_NOTIFICATION = ? AND STATUS = 'PROCESSING'"
        );
        $updateOutbox->execute([(string) $outbox['UUID_NOTIFICATION']]);
        if ($updateOutbox->rowCount() !== 1) {
            throw SifException::conflict(
                'Notification outbox ownership was lost before review hold'
            );
        }

        return [
            'uuid_notification' => (string) $outbox['UUID_NOTIFICATION'],
            'attempt_uuid' => $attemptUuid,
            'status' => 'REVIEW',
            'idempotency_reused' => false,
        ];
    }

    private function deliveryAttemptForUpdate(
        \PDO $db,
        string $attemptUuid
    ): array {
        $attemptUuid = $this->boundedRequired(
            $attemptUuid,
            'delivery_attempt_uuid',
            36
        );
        $stmt = $db->prepare(
            'SELECT UUID_DELIVERY_ATTEMPT, UUID_NOTIFICATION, ATTEMPT_NO,
                    CHANNEL, PROVIDER_REF, STATUS, ERROR_CODE, ERROR_DETAIL,
                    STARTED_AT, FINISHED_AT
             FROM notification_delivery_attempt
             WHERE UUID_DELIVERY_ATTEMPT = ?
             FOR UPDATE'
        );
        $stmt->execute([$attemptUuid]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw SifException::conflict(
                'Notification delivery attempt was not found'
            );
        }

        return $row;
    }

    private function outboxForUpdate(
        \PDO $db,
        string $uuidNotification
    ): array {
        $stmt = $db->prepare(
            'SELECT ID, UUID_NOTIFICATION, STATUS, NEXT_ATTEMPT_AT, SENT_AT
             FROM notification_outbox
             WHERE UUID_NOTIFICATION = ?
             FOR UPDATE'
        );
        $stmt->execute([$uuidNotification]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw SifException::conflict(
                'Notification outbox row was not found'
            );
        }

        return $row;
    }

    private function assertActiveDeliveryOwnership(
        \PDO $db,
        array $attempt
    ): array {
        if ((string) $attempt['STATUS'] !== 'STARTED') {
            throw SifException::conflict(
                'Notification delivery attempt is not active'
            );
        }

        $outbox = $this->outboxForUpdate(
            $db,
            (string) $attempt['UUID_NOTIFICATION']
        );
        if ((string) $outbox['STATUS'] !== 'PROCESSING') {
            throw SifException::conflict(
                'Notification outbox item is not being processed'
            );
        }

        $latest = $db->prepare(
            'SELECT UUID_DELIVERY_ATTEMPT
             FROM notification_delivery_attempt
             WHERE UUID_NOTIFICATION = ?
             ORDER BY ATTEMPT_NO DESC
             LIMIT 1
             FOR UPDATE'
        );
        $latest->execute([(string) $attempt['UUID_NOTIFICATION']]);
        if ((string) $latest->fetchColumn()
            !== (string) $attempt['UUID_DELIVERY_ATTEMPT']
        ) {
            throw SifException::conflict(
                'Notification delivery attempt no longer owns the outbox item'
            );
        }

        return $outbox;
    }

    private function boundedRequired(
        mixed $value,
        string $field,
        int $maxLength
    ): string {
        $value = trim((string) $value);
        if ($value === '' || strlen($value) > $maxLength) {
            throw SifException::validation(
                'Invalid notification delivery field ' . $field
            );
        }

        return $value;
    }

    private function boundedOptional(mixed $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (strlen($value) > $maxLength) {
            throw SifException::validation(
                'Invalid notification delivery provider reference'
            );
        }

        return $value;
    }

    private function dateTime(string $value): string
    {
        $value = trim($value);
        $parsed = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $value,
            new \DateTimeZone('UTC')
        );
        if (!$parsed || $parsed->format('Y-m-d H:i:s') !== $value) {
            throw SifException::validation(
                'Invalid notification retry datetime'
            );
        }

        return $value;
    }

    private function assertSameMessage(array $existing, array $message): void
    {
        $matches =
            (string) $existing['TEMPLATE_CODE'] === $message['template_code']
            && (string) $existing['TEMPLATE_VERSION'] === $message['template_version']
            && (string) $existing['RECIPIENT_TYPE'] === $message['recipient_type']
            && strtolower((string) $existing['RECIPIENT_HASH']) === $message['recipient_hash']
            && $this->canonicalJson((string) $existing['PAYLOAD_JSON']) === $this->canonicalJson($message['payload_json'])
            && (string) ($existing['UUID_FACTURA'] ?? '') === (string) ($message['uuid_factura'] ?? '')
            && (string) ($existing['UUID_PAYMENT'] ?? '') === (string) ($message['uuid_payment'] ?? '')
            && (string) $existing['CORRELATION_ID'] === $message['correlation_id'];

        if (!$matches) {
            throw SifException::conflict('Notification idempotency key already exists with different payload');
        }
    }

    private function canonicalJson(string $json): string
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return $json;
        }
        $this->sortRecursive($decoded);

        return (string) json_encode(
            $decoded,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
    }

    private function sortRecursive(array &$value): void
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sortRecursive($item);
            }
        }
        unset($item);

        if (!array_is_list($value)) {
            ksort($value);
        }
    }

    private function result(array $row, bool $reused): array
    {
        return [
            'uuid_notification' => (string) $row['UUID_NOTIFICATION'],
            'status' => (string) $row['STATUS'],
            'idempotency_reused' => $reused,
        ];
    }

    private function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
