<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

final class PaymentActionEventRepository implements PaymentActionEventWriter
{
    private const RESULTS = [
        'REQUESTED',
        'SUCCEEDED',
        'REUSED',
        'NO_CHANGE',
        'REJECTED',
        'FAILED',
        'QUEUED',
        'PARTIAL',
    ];

    private const ACTIONS = [
        'CREATE_REQUEST',
        'CREATE',
        'IDEMPOTENCY_REUSE',
        'DUPLICATE_DETECTED',
        'SEARCH',
        'VIEW',
        'VIEW_ALLOCATIONS',
        'EXPORT',
        'ALLOCATE',
        'REALLOCATE',
        'UNALLOCATE',
        'SPLIT_ALLOCATION',
        'RECONCILE',
        'MARK_PENDING_REVIEW',
        'RESOLVE_RECONCILIATION',
        'LINK_REFUND',
        'LINK_COMPENSATION',
        'LINK_CLAIM_PAYMENT',
        'CANCEL_OPERATION',
        'MARK_ERROR',
        'RETRY',
        'RECOVER_LOCK',
        'REDSYS_CALLBACK',
        'REDSYS_WORKER_RESULT',
        'SYNC_LEGACY',
        'IMPORT',
        'ACCESS_DENIED',
        'VALIDATION_REJECTED',
        'IMMUTABILITY_BLOCKED',
    ];

    private const SOURCE_ENVIRONMENTS = [
        'PRODUCTION',
        'PREPRODUCTION',
        'TEST',
        'DEVELOPMENT',
        'MIGRATION',
    ];

    private const SOURCE_CHANNELS = [
        'INTRANET',
        'ECOMMERCE',
        'STUDENT_PORTAL',
        'PAY_PRISMA',
        'SIF_PANEL',
        'SIF_API',
        'REDSYS_CALLBACK',
        'REDSYS_WORKER',
        'CLI',
        'SCHEDULED_PROCESS',
        'RECONCILIATION',
        'LEGACY_SYNC',
        'MIGRATION',
        'ADMIN_TOOL',
    ];

    private const ACTOR_TYPES = [
        'HUMAN',
        'SYSTEM',
        'PROCESS',
    ];

    public function __construct(private UuidGenerator $uuidGenerator)
    {
    }

    public function append(\PDO $db, array $event): string
    {
        $normalized = $this->validate($event);
        $uuid = $this->uuidGenerator->generate();

        $db->prepare(
            'INSERT INTO payment_action_event (
                UUID_EVENT, UUID_PAYMENT, PAYMENT_IDEMPOTENCY_KEY, REQUEST_ID,
                CORRELATION_ID, CAUSATION_ID, ACTION, RESULT, IS_TERMINAL,
                SOURCE_ENVIRONMENT, SOURCE_CHANNEL, ACTOR_TYPE, ACTOR_ID,
                ACTOR_ROLE, REASON_CODE, BEFORE_HASH, AFTER_HASH, CHANGESET_JSON,
                ERROR_CODE, OCCURRED_AT
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uuid,
            $normalized['uuid_payment'],
            $normalized['payment_idempotency_key'],
            $normalized['request_id'],
            $normalized['correlation_id'],
            $normalized['causation_id'],
            $normalized['action'],
            $normalized['result'],
            $normalized['is_terminal'] ? 1 : 0,
            $normalized['source_environment'],
            $normalized['source_channel'],
            $normalized['actor_type'],
            $normalized['actor_id'],
            $normalized['actor_role'],
            $normalized['reason_code'],
            $normalized['before_hash'],
            $normalized['after_hash'],
            $this->encodeChangeset($normalized['changeset']),
            $normalized['error_code'],
            $normalized['occurred_at'],
        ]);

        return $uuid;
    }

    private function validate(array $event): array
    {
        foreach ([
            'request_id',
            'correlation_id',
            'action',
            'result',
            'source_environment',
            'source_channel',
            'actor_type',
            'occurred_at',
        ] as $field) {
            if (!isset($event[$field]) || !is_string($event[$field]) || trim($event[$field]) === '') {
                throw SifException::validation('Missing payment audit field: ' . $field);
            }
        }

        $action = $this->controlledValue($event, 'action', self::ACTIONS);
        $result = $this->controlledValue($event, 'result', self::RESULTS);
        $sourceEnvironment = $this->controlledValue($event, 'source_environment', self::SOURCE_ENVIRONMENTS);
        $sourceChannel = $this->controlledValue($event, 'source_channel', self::SOURCE_CHANNELS);
        $actorType = $this->controlledValue($event, 'actor_type', self::ACTOR_TYPES);

        $isTerminal = (bool) ($event['is_terminal'] ?? false);
        if (($result === 'REQUESTED') === $isTerminal) {
            throw SifException::validation('REQUESTED must be non-terminal and every other result must be terminal');
        }

        return [
            'uuid_payment' => $this->nullableString($event, 'uuid_payment'),
            'payment_idempotency_key' => $this->nullableString($event, 'payment_idempotency_key'),
            'request_id' => trim($event['request_id']),
            'correlation_id' => trim($event['correlation_id']),
            'causation_id' => $this->nullableString($event, 'causation_id'),
            'action' => $action,
            'result' => $result,
            'is_terminal' => $isTerminal,
            'source_environment' => $sourceEnvironment,
            'source_channel' => $sourceChannel,
            'actor_type' => $actorType,
            'actor_id' => $this->nullableString($event, 'actor_id'),
            'actor_role' => $this->nullableString($event, 'actor_role'),
            'reason_code' => $this->nullableString($event, 'reason_code'),
            'before_hash' => $this->nullableHash($event, 'before_hash'),
            'after_hash' => $this->nullableHash($event, 'after_hash'),
            'changeset' => $event['changeset'] ?? null,
            'error_code' => $this->nullableString($event, 'error_code'),
            'occurred_at' => trim($event['occurred_at']),
        ];
    }

    private function controlledValue(array $event, string $field, array $allowed): string
    {
        $value = strtoupper(trim((string) $event[$field]));

        if (!in_array($value, $allowed, true)) {
            throw SifException::validation('Invalid payment audit field: ' . $field);
        }

        return $value;
    }

    private function nullableString(array $event, string $field): ?string
    {
        $value = $event[$field] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || trim($value) === '') {
            throw SifException::validation('Invalid payment audit field: ' . $field);
        }
        return trim($value);
    }

    private function nullableHash(array $event, string $field): ?string
    {
        $value = $this->nullableString($event, $field);
        if ($value !== null && preg_match('/^[a-f0-9]{64}$/i', $value) !== 1) {
            throw SifException::validation('Invalid SHA-256 field: ' . $field);
        }
        return $value;
    }

    private function encodeChangeset(mixed $changeset): ?string
    {
        if ($changeset === null) {
            return null;
        }
        if (!is_array($changeset)) {
            throw SifException::validation('Payment audit changeset must be an object or array');
        }
        return json_encode(
            $changeset,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }
}
