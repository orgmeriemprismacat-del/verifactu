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
                throw SifException::validation('Missing SIF audit field: ' . $field);
            }
        }

        $uuid = $this->uuidGenerator->generate();
        $changeset = $event['changeset'] ?? null;
        if ($changeset !== null && !is_array($changeset)) {
            throw SifException::validation('SIF audit changeset must be an object or array');
        }
        $changesetJson = $changeset === null
            ? null
            : json_encode(
                $changeset,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
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
            $this->nullableString($event['reason_code'] ?? null),
            $this->nullableHash($event['before_hash'] ?? null),
            $this->nullableHash($event['after_hash'] ?? null),
            $changesetJson,
            $this->nullableString($event['error_code'] ?? null),
            trim($event['occurred_at']),
        ]);

        return $uuid;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableHash(mixed $value): ?string
    {
        $value = $this->nullableString($value);
        if ($value !== null && preg_match('/^[a-f0-9]{64}$/i', $value) !== 1) {
            throw SifException::validation('Invalid SIF audit SHA-256 value');
        }

        return $value;
    }
}
