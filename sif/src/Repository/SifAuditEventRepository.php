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
            $this->nullableString($event, 'causation_id'),
            strtoupper(trim($event['action'])),
            strtoupper(trim($event['result'])),
            strtoupper(trim($event['resource_type'])),
            $this->nullableString($event, 'resource_id'),
            strtoupper(trim($event['source_environment'])),
            strtoupper(trim($event['source_channel'])),
            strtoupper(trim($event['actor_type'])),
            $this->nullableString($event, 'actor_id'),
            $this->nullableString($event, 'actor_role'),
            $this->nullableString($event, 'reason_code'),
            $this->nullableHash($event, 'before_hash'),
            $this->nullableHash($event, 'after_hash'),
            $this->changeset($event['changeset'] ?? null),
            $this->nullableString($event, 'error_code'),
            trim($event['occurred_at']),
        ]);

        return $uuid;
    }

    private function nullableString(array $event, string $field): ?string
    {
        $value = $event[$field] ?? null;
        if ($value === null) {
            return null;
        }

        if (!is_string($value) || trim($value) === '') {
            throw SifException::validation('Invalid SIF audit field: ' . $field);
        }

        return trim($value);
    }

    private function nullableHash(array $event, string $field): ?string
    {
        $value = $this->nullableString($event, $field);
        if ($value !== null && preg_match('/^[a-f0-9]{64}$/i', $value) !== 1) {
            throw SifException::validation('Invalid SIF audit SHA-256 field: ' . $field);
        }

        return $value;
    }

    private function changeset(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw SifException::validation('SIF audit changeset must be an object or array');
        }

        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }
}
