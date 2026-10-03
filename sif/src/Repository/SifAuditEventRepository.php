<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

final class SifAuditEventRepository
{
    public function __construct(private ?UuidGenerator $uuidGenerator = null)
    {
        $this->uuidGenerator ??= new UuidGenerator();
    }

    public function append(\PDO $db, array $event): string
    {
        foreach (['request_id', 'correlation_id', 'action', 'result', 'resource_type', 'source_environment', 'source_channel', 'actor_type', 'occurred_at'] as $field) {
            if (trim((string) ($event[$field] ?? '')) === '') {
                throw SifException::validation('Missing SIF audit field: ' . $field);
            }
        }

        $changeset = $event['changeset'] ?? null;
        $changesetJson = $changeset === null ? null : json_encode(
            $changeset,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $uuid = $this->uuidGenerator->generate();
        $db->prepare(
            'INSERT INTO sif_audit_event (
                UUID_EVENT, REQUEST_ID, CORRELATION_ID, CAUSATION_ID, ACTION, RESULT,
                RESOURCE_TYPE, RESOURCE_ID, SOURCE_ENVIRONMENT, SOURCE_CHANNEL,
                ACTOR_TYPE, ACTOR_ID, ACTOR_ROLE, REASON_CODE, BEFORE_HASH, AFTER_HASH,
                CHANGESET_JSON, ERROR_CODE, OCCURRED_AT
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uuid,
            $event['request_id'],
            $event['correlation_id'],
            $event['causation_id'] ?? null,
            strtoupper(trim((string) $event['action'])),
            strtoupper(trim((string) $event['result'])),
            strtoupper(trim((string) $event['resource_type'])),
            $event['resource_id'] ?? null,
            strtolower(trim((string) $event['source_environment'])),
            strtoupper(trim((string) $event['source_channel'])),
            strtoupper(trim((string) $event['actor_type'])),
            $event['actor_id'] ?? null,
            $event['actor_role'] ?? null,
            $event['reason_code'] ?? null,
            $event['before_hash'] ?? null,
            $event['after_hash'] ?? null,
            $changesetJson,
            $event['error_code'] ?? null,
            $event['occurred_at'],
        ]);

        return $uuid;
    }
}
