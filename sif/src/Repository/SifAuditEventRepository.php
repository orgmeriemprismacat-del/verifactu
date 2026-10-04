<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

final class SifAuditEventRepository
{
    public function __construct(private UuidGenerator $uuidGenerator)
    {
    }

    public function append(\PDO $db, array $event): string
    {
        foreach ([
            'request_id',
            'correlation_id',
            'action',
            'result',
            'resource_type',
            'source_environment',
            'source_channel',
            'actor_type',
        ] as $field) {
            if (!isset($event[$field]) || !is_string($event[$field]) || trim($event[$field]) === '') {
                throw SifException::validation('Missing SIF audit field: ' . $field);
            }
        }

        $uuid = $this->uuidGenerator->generate();
        $occurredAt = isset($event['occurred_at'])
            ? trim((string) $event['occurred_at'])
            : (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))
                ->format('Y-m-d H:i:s.u');

        if ($occurredAt === '') {
            throw SifException::validation('Missing SIF audit field: occurred_at');
        }

        $changesetJson = $this->json($event['changeset'] ?? null);
        $beforeHash = array_key_exists('before_hash', $event)
            ? $this->nullableHash($event['before_hash'])
            : $this->snapshotHash($event['before_snapshot'] ?? null);
        $afterHash = array_key_exists('after_hash', $event)
            ? $this->nullableHash($event['after_hash'])
            : $this->snapshotHash($event['after_snapshot'] ?? null);

        $db->prepare(
            'INSERT INTO sif_audit_event (
                UUID_EVENT, REQUEST_ID, CORRELATION_ID, CAUSATION_ID,
                ACTION, RESULT, RESOURCE_TYPE, RESOURCE_ID,
                SOURCE_ENVIRONMENT, SOURCE_CHANNEL, ACTOR_TYPE, ACTOR_ID,
                ACTOR_ROLE, REASON_CODE, BEFORE_HASH, AFTER_HASH,
                CHANGESET_JSON, ERROR_CODE, OCCURRED_AT
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uuid,
            trim($event['request_id']),
            trim($event['correlation_id']),
            $this->nullableString($event['causation_id'] ?? null),
            strtoupper(trim($event['action'])),
            strtoupper(trim($event['result'])),
            strtoupper(trim($event['resource_type'])),
            $this->nullableString($event['resource_id'] ?? null),
            strtoupper(trim($event['source_environment'])),
            strtoupper(trim($event['source_channel'])),
            strtoupper(trim($event['actor_type'])),
            $this->nullableString($event['actor_id'] ?? null),
            $this->nullableString($event['actor_role'] ?? null),
            $this->nullableUpper($event['reason_code'] ?? null),
            $beforeHash,
            $afterHash,
            $changesetJson,
            $this->nullableUpper($event['error_code'] ?? null),
            $occurredAt,
        ]);

        return $uuid;
    }

    private function snapshotHash(mixed $snapshot): ?string
    {
        $json = $this->json($snapshot);

        return $json === null ? null : hash('sha256', $json);
    }

    private function json(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw SifException::validation('SIF audit snapshots and changesets must be arrays');
        }

        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableUpper(mixed $value): ?string
    {
        $value = $this->nullableString($value);

        return $value === null ? null : strtoupper($value);
    }

    private function nullableHash(mixed $value): ?string
    {
        $value = $this->nullableString($value);
        if ($value !== null && preg_match('/^[a-f0-9]{64}$/i', $value) !== 1) {
            throw SifException::validation('Invalid SIF audit SHA-256 value');
        }

        return $value === null ? null : strtolower($value);
    }
}
