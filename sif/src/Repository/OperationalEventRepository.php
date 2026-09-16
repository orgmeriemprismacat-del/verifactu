<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

final class OperationalEventRepository
{
    public function __construct(private UuidGenerator $uuidGenerator)
    {
    }

    public function append(\PDO $db, array $event): string
    {
        foreach ([
            'operation_type',
            'source_type',
            'fiscal_impact',
            'economic_impact',
            'status',
            'reason_code',
            'actor_type',
            'source_channel',
            'correlation_id',
            'occurred_at',
        ] as $field) {
            if (!isset($event[$field]) || !is_string($event[$field]) || trim($event[$field]) === '') {
                throw SifException::validation('Missing operational event field: ' . $field);
            }
        }

        $uuid = $this->uuidGenerator->generate();
        $before = $this->snapshot($event['before_snapshot'] ?? null);
        $after = $this->snapshot($event['after_snapshot'] ?? null);

        $db->prepare(
            'INSERT INTO operational_event (
                UUID_OPERATIONAL_EVENT, OPERATION_TYPE, SOURCE_TYPE, SOURCE_ID,
                UUID_FACTURA, UUID_PAYMENT, FISCAL_IMPACT, ECONOMIC_IMPACT,
                STATUS, REASON_CODE, BEFORE_SNAPSHOT_JSON, AFTER_SNAPSHOT_JSON,
                BEFORE_HASH, AFTER_HASH, ACTOR_TYPE, ACTOR_ID, ACTOR_ROLE,
                SOURCE_CHANNEL, CORRELATION_ID, OCCURRED_AT
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uuid,
            strtoupper(trim($event['operation_type'])),
            strtoupper(trim($event['source_type'])),
            $event['source_id'] ?? null,
            $event['uuid_factura'] ?? null,
            $event['uuid_payment'] ?? null,
            strtoupper(trim($event['fiscal_impact'])),
            strtoupper(trim($event['economic_impact'])),
            strtoupper(trim($event['status'])),
            strtoupper(trim($event['reason_code'])),
            $before['json'],
            $after['json'],
            $before['hash'],
            $after['hash'],
            strtoupper(trim($event['actor_type'])),
            $event['actor_id'] ?? null,
            $event['actor_role'] ?? null,
            strtoupper(trim($event['source_channel'])),
            trim($event['correlation_id']),
            trim($event['occurred_at']),
        ]);

        return $uuid;
    }

    private function snapshot(mixed $value): array
    {
        if ($value === null) {
            return ['json' => null, 'hash' => null];
        }
        if (!is_array($value)) {
            throw SifException::validation('Operational snapshots must be objects or arrays');
        }
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return ['json' => $json, 'hash' => hash('sha256', $json)];
    }
}
