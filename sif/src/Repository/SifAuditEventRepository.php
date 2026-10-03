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
            if (!isset($event[$field]) || trim((string) $event[$field]) === '') {
                throw SifException::validation('Missing SIF audit field: ' . $field);
            }
        }

        $uuid = $this->uuidGenerator->generate();
        $occurredAt = $event['occurred_at'] ?? (new \DateTimeImmutable(
            'now',
            new \DateTimeZone('Europe/Madrid')
        ))->format('Y-m-d H:i:s.u');

        $changeset = $this->json($event['changeset'] ?? null);
        $beforeHash = $event['before_hash'] ?? $this->snapshotHash($event['before_snapshot'] ?? null);
        $afterHash = $event['after_hash'] ?? $this->snapshotHash($event['after_snapshot'] ?? null);

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
            trim((string) $event['request_id']),
            trim((string) $event['correlation_id']),
            $this->nullable($event['causation_id'] ?? null),
            strtoupper(trim((string) $event['action'])),
            strtoupper(trim((string) $event['result'])),
            strtoupper(trim((string) $event['resource_type'])),
            $this->nullable($event['resource_id'] ?? null),
            strtoupper(trim((string) $event['source_environment'])),
            strtoupper(trim((string) $event['source_channel'])),
            strtoupper(trim((string) $event['actor_type'])),
            $this->nullable($event['actor_id'] ?? null),
            $this->nullable($event['actor_role'] ?? null),
            $this->nullableUpper($event['reason_code'] ?? null),
            $beforeHash,
            $afterHash,
            $changeset,
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
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableUpper(mixed $value): ?string
    {
        $value = $this->nullable($value);

        return $value === null ? null : strtoupper($value);
    }
}
