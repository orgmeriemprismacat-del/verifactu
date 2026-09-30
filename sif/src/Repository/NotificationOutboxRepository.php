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
