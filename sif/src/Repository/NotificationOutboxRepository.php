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
            'SELECT *
             FROM notification_outbox
             WHERE IDEMPOTENCY_KEY = ?'
        );
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function recoverStaleLocks(
        \PDO $db,
        \DateTimeImmutable $now,
        int $leaseMinutes = 15
    ): int {
        if ($leaseMinutes <= 0) {
            throw SifException::validation('Notification lease minutes must be positive');
        }

        $stmt = $db->prepare(
            "UPDATE notification_outbox
             SET STATUS = 'REVIEW',
                 LOCKED_AT = NULL,
                 LOCKED_BY = NULL,
                 CLAIM_TOKEN = NULL,
                 NEXT_ATTEMPT_AT = NULL,
                 LAST_ERROR = 'Recovered stale notification delivery lock; provider outcome may be ambiguous'
             WHERE STATUS = 'PROCESSING'
               AND LOCKED_AT IS NOT NULL
               AND LOCKED_AT < ?"
        );
        $stmt->execute([
            $now->modify('-' . $leaseMinutes . ' minutes')->format('Y-m-d H:i:s'),
        ]);

        return $stmt->rowCount();
    }

    public function claimNext(
        \PDO $db,
        string $workerId,
        \DateTimeImmutable $now
    ): ?array {
        $workerId = trim($workerId);
        if ($workerId === '') {
            throw SifException::validation('Notification worker ID is required');
        }

        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }

        try {
            $stmt = $db->prepare(
                "SELECT *
                 FROM notification_outbox
                 WHERE STATUS IN ('PENDING', 'RETRY')
                   AND ATTEMPTS < MAX_ATTEMPTS
                   AND (NEXT_ATTEMPT_AT IS NULL OR NEXT_ATTEMPT_AT <= ?)
                 ORDER BY COALESCE(NEXT_ATTEMPT_AT, CREATED_AT), ID
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute([$now->format('Y-m-d H:i:s')]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row === false) {
                if ($ownsTransaction) {
                    $db->commit();
                }

                return null;
            }

            $claimToken = $this->uuidGenerator->generate();
            $update = $db->prepare(
                "UPDATE notification_outbox
                 SET STATUS = 'PROCESSING',
                     ATTEMPTS = ATTEMPTS + 1,
                     LOCKED_AT = ?,
                     LOCKED_BY = ?,
                     CLAIM_TOKEN = ?,
                     LAST_ERROR = NULL
                 WHERE ID = ?
                   AND STATUS IN ('PENDING', 'RETRY')"
            );
            $update->execute([
                $now->format('Y-m-d H:i:s'),
                $workerId,
                $claimToken,
                $row['ID'],
            ]);
            if ($update->rowCount() !== 1) {
                throw SifException::conflict('Notification could not be claimed');
            }

            if ($ownsTransaction) {
                $db->commit();
            }

            $row['STATUS'] = 'PROCESSING';
            $row['ATTEMPTS'] = (int) $row['ATTEMPTS'] + 1;
            $row['LOCKED_AT'] = $now->format('Y-m-d H:i:s');
            $row['LOCKED_BY'] = $workerId;
            $row['CLAIM_TOKEN'] = $claimToken;

            return $row;
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    public function startDeliveryAttempt(
        \PDO $db,
        array $notification,
        string $channel,
        \DateTimeImmutable $now
    ): array {
        $this->assertOwned($notification);
        $channel = strtoupper(trim($channel));
        if ($channel === '') {
            throw SifException::validation('Notification delivery channel is required');
        }

        $uuidAttempt = $this->uuidGenerator->generate();
        $stmt = $db->prepare(
            "INSERT INTO notification_delivery_attempt
                (UUID_DELIVERY_ATTEMPT, UUID_NOTIFICATION, ATTEMPT_NO, CHANNEL,
                 STATUS, STARTED_AT)
             VALUES (?, ?, ?, ?, 'PROCESSING', ?)"
        );
        $stmt->execute([
            $uuidAttempt,
            (string) $notification['UUID_NOTIFICATION'],
            (int) $notification['ATTEMPTS'],
            $channel,
            $now->format('Y-m-d H:i:s'),
        ]);

        return [
            'uuid_delivery_attempt' => $uuidAttempt,
            'attempt_no' => (int) $notification['ATTEMPTS'],
            'channel' => $channel,
        ];
    }

    public function markSent(
        \PDO $db,
        array $notification,
        string $uuidAttempt,
        ?string $providerRef,
        \DateTimeImmutable $now
    ): void {
        $this->finish(
            $db,
            $notification,
            $uuidAttempt,
            'SENT',
            'SENT',
            $providerRef,
            null,
            null,
            $now,
            null
        );
    }

    public function markRetryOrDeadLetter(
        \PDO $db,
        array $notification,
        string $uuidAttempt,
        string $errorCode,
        string $errorDetail,
        \DateTimeImmutable $now,
        \DateTimeImmutable $nextAttemptAt
    ): string {
        $status = (int) $notification['ATTEMPTS'] >= (int) $notification['MAX_ATTEMPTS']
            ? 'DEAD_LETTER'
            : 'RETRY';

        $this->finish(
            $db,
            $notification,
            $uuidAttempt,
            'FAILED',
            $status,
            null,
            $errorCode,
            $errorDetail,
            $now,
            $status === 'RETRY' ? $nextAttemptAt : null
        );

        return $status;
    }

    public function markReview(
        \PDO $db,
        array $notification,
        string $uuidAttempt,
        string $errorCode,
        string $errorDetail,
        ?string $providerRef,
        \DateTimeImmutable $now
    ): void {
        $this->finish(
            $db,
            $notification,
            $uuidAttempt,
            'REVIEW',
            'REVIEW',
            $providerRef,
            $errorCode,
            $errorDetail,
            $now,
            null
        );
    }

    public function markCancelled(
        \PDO $db,
        array $notification,
        string $uuidAttempt,
        string $reasonCode,
        string $reasonDetail,
        \DateTimeImmutable $now
    ): void {
        $this->finish(
            $db,
            $notification,
            $uuidAttempt,
            'CANCELLED',
            'CANCELLED',
            null,
            $reasonCode,
            $reasonDetail,
            $now,
            null
        );
    }

    private function finish(
        \PDO $db,
        array $notification,
        string $uuidAttempt,
        string $attemptStatus,
        string $outboxStatus,
        ?string $providerRef,
        ?string $errorCode,
        ?string $errorDetail,
        \DateTimeImmutable $now,
        ?\DateTimeImmutable $nextAttemptAt
    ): void {
        $this->assertOwned($notification);
        $uuidAttempt = trim($uuidAttempt);
        if ($uuidAttempt === '') {
            throw SifException::validation('Notification delivery attempt UUID is required');
        }

        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }

        try {
            $attempt = $db->prepare(
                "UPDATE notification_delivery_attempt
                 SET PROVIDER_REF = ?,
                     STATUS = ?,
                     ERROR_CODE = ?,
                     ERROR_DETAIL = ?,
                     FINISHED_AT = ?
                 WHERE UUID_DELIVERY_ATTEMPT = ?
                   AND UUID_NOTIFICATION = ?
                   AND ATTEMPT_NO = ?
                   AND STATUS = 'PROCESSING'"
            );
            $attempt->execute([
                $this->truncate($providerRef, 120),
                $attemptStatus,
                $this->truncate($errorCode, 80),
                $errorDetail,
                $now->format('Y-m-d H:i:s'),
                $uuidAttempt,
                (string) $notification['UUID_NOTIFICATION'],
                (int) $notification['ATTEMPTS'],
            ]);
            if ($attempt->rowCount() !== 1) {
                throw SifException::conflict('Notification delivery attempt is no longer active');
            }

            $outbox = $db->prepare(
                "UPDATE notification_outbox
                 SET STATUS = ?,
                     NEXT_ATTEMPT_AT = ?,
                     LOCKED_AT = NULL,
                     LOCKED_BY = NULL,
                     CLAIM_TOKEN = NULL,
                     LAST_ERROR = ?,
                     SENT_AT = CASE WHEN ? = 'SENT' THEN ? ELSE SENT_AT END,
                     CANCELLED_AT = CASE WHEN ? = 'CANCELLED' THEN ? ELSE CANCELLED_AT END
                 WHERE ID = ?
                   AND STATUS = 'PROCESSING'
                   AND CLAIM_TOKEN = ?"
            );
            $outbox->execute([
                $outboxStatus,
                $nextAttemptAt?->format('Y-m-d H:i:s'),
                $errorDetail,
                $outboxStatus,
                $now->format('Y-m-d H:i:s'),
                $outboxStatus,
                $now->format('Y-m-d H:i:s'),
                $notification['ID'],
                (string) $notification['CLAIM_TOKEN'],
            ]);
            if ($outbox->rowCount() !== 1) {
                throw SifException::conflict('Notification delivery ownership was lost');
            }

            if ($ownsTransaction) {
                $db->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    private function assertOwned(array $notification): void
    {
        if (($notification['STATUS'] ?? '') !== 'PROCESSING'
            || trim((string) ($notification['CLAIM_TOKEN'] ?? '')) === ''
            || (int) ($notification['ATTEMPTS'] ?? 0) <= 0
        ) {
            throw SifException::conflict('Notification is not owned by a delivery worker');
        }
    }

    private function truncate(?string $value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $length, 'UTF-8');
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
