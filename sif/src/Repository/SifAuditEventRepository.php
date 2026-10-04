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
            'occurred_at',
        ] as $field) {
            if (!isset($event[$field]) || !is_string($event[$field]) || trim($event[$field]) === '') {
                throw SifException::validation('Missing SIF audit event field: ' . $field);
            }
        }

        $uuid = $this->uuidGenerator->generate();
        $changeset = $event['changeset'] ?? null;
        if ($changeset !== null && !is_array($changeset)) {
            throw SifException::validation('SIF audit changeset must be an array');
        }

        $changesetJson = $changeset === null
            ? null
            : json_encode(
                $changeset,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
            );

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
            $this->optionalString($event['causation_id'] ?? null),
            strtoupper(trim($event['action'])),
            strtoupper(trim($event['result'])),
            strtoupper(trim($event['resource_type'])),
            $this->optionalString($event['resource_id'] ?? null),
            strtoupper(trim($event['source_environment'])),
            strtoupper(trim($event['source_channel'])),
            strtoupper(trim($event['actor_type'])),
            $this->optionalString($event['actor_id'] ?? null),
            $this->optionalString($event['actor_role'] ?? null),
            $this->optionalString($event['reason_code'] ?? null),
            $this->optionalString($event['before_hash'] ?? null),
            $this->optionalString($event['after_hash'] ?? null),
            $changesetJson,
            $this->optionalString($event['error_code'] ?? null),
            trim($event['occurred_at']),
        ]);

        return $uuid;
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
