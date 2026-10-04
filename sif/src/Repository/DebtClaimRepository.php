<?php

declare(strict_types=1);

namespace Prisma\Sif\Repository;

use Prisma\Sif\Contract\PayloadIdempotencyValidatorInterface;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\PayloadIdempotencyValidator;

final class DebtClaimRepository
{
    public function __construct(
        private UuidGenerator $uuidGenerator,
        private ?PayloadIdempotencyValidatorInterface $idempotency = null
    ) {
        $this->idempotency ??= new PayloadIdempotencyValidator();
    }

    public function findByInvoice(\PDO $db, string $uuidFactura, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM debt_claim_case WHERE UUID_FACTURA = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([trim($uuidFactura)]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function createCase(\PDO $db, array $case): array
    {
        foreach ([
            'uuid_factura',
            'outstanding_amount',
            'currency',
            'recipient_type',
            'correlation_id',
        ] as $field) {
            if (!isset($case[$field]) || trim((string) $case[$field]) === '') {
                throw SifException::validation('Missing debt claim case field: ' . $field);
            }
        }

        $uuid = $this->uuidGenerator->generate();
        $db->prepare(
            'INSERT INTO debt_claim_case (
                UUID_CLAIM, UUID_FACTURA, STATUS, STAGE, OUTSTANDING_AMOUNT,
                CURRENCY, RECIPIENT_TYPE, RECIPIENT_HASH, LOCK_VERSION,
                CORRELATION_ID, CREATED_BY, RESOLVED_AT
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)'
        )->execute([
            $uuid,
            trim((string) $case['uuid_factura']),
            strtoupper(trim((string) ($case['status'] ?? 'OPEN'))),
            strtoupper(trim((string) ($case['stage'] ?? 'DETECTED'))),
            trim((string) $case['outstanding_amount']),
            strtoupper(trim((string) $case['currency']),
            ),
            strtoupper(trim((string) $case['recipient_type'])),
            $this->optionalString($case['recipient_hash'] ?? null),
            trim((string) $case['correlation_id']),
            $this->optionalString($case['created_by'] ?? null),
            $this->optionalString($case['resolved_at'] ?? null),
        ]);

        $created = $this->findByInvoice($db, trim((string) $case['uuid_factura']), true);
        if ($created === null) {
            throw new \RuntimeException('Created debt claim case could not be loaded');
        }

        return $created;
    }

    public function updateCase(
        \PDO $db,
        string $uuidClaim,
        int $expectedVersion,
        array $changes
    ): array {
        foreach (['status', 'stage', 'outstanding_amount', 'recipient_type', 'correlation_id'] as $field) {
            if (!isset($changes[$field]) || trim((string) $changes[$field]) === '') {
                throw SifException::validation('Missing debt claim update field: ' . $field);
            }
        }

        $stmt = $db->prepare(
            'UPDATE debt_claim_case
             SET STATUS = ?, STAGE = ?, OUTSTANDING_AMOUNT = ?,
                 RECIPIENT_TYPE = ?, RECIPIENT_HASH = ?, CORRELATION_ID = ?,
                 RESOLVED_AT = ?, LOCK_VERSION = LOCK_VERSION + 1
             WHERE UUID_CLAIM = ? AND LOCK_VERSION = ?'
        );
        $stmt->execute([
            strtoupper(trim((string) $changes['status'])),
            strtoupper(trim((string) $changes['stage'])),
            trim((string) $changes['outstanding_amount']),
            strtoupper(trim((string) $changes['recipient_type'])),
            $this->optionalString($changes['recipient_hash'] ?? null),
            trim((string) $changes['correlation_id']),
            $this->optionalString($changes['resolved_at'] ?? null),
            trim($uuidClaim),
            $expectedVersion,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw SifException::conflict('Debt claim case changed concurrently');
        }

        $read = $db->prepare('SELECT * FROM debt_claim_case WHERE UUID_CLAIM = ? FOR UPDATE');
        $read->execute([$uuidClaim]);
        $row = $read->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new \RuntimeException('Updated debt claim case could not be loaded');
        }

        return $row;
    }

    public function findEventByIdempotencyKey(
        \PDO $db,
        string $key,
        bool $forUpdate = false
    ): ?array {
        $sql = 'SELECT * FROM debt_claim_event WHERE IDEMPOTENCY_KEY = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([trim($key)]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function reuseEventIfSame(
        \PDO $db,
        string $key,
        array $payload,
        bool $forUpdate = false
    ): ?array {
        $row = $this->findEventByIdempotencyKey($db, $key, $forUpdate);
        if ($row === null) {
            return null;
        }

        $this->idempotency->assertMatches($payload, (string) $row['PAYLOAD_HASH']);

        return $row;
    }

    public function appendEvent(\PDO $db, array $event, array $idempotencyPayload): array
    {
        foreach ([
            'uuid_claim',
            'action',
            'result',
            'to_stage',
            'outstanding_amount',
            'idempotency_key',
            'request_id',
            'correlation_id',
            'actor_type',
            'reason_code',
            'occurred_at',
        ] as $field) {
            if (!isset($event[$field]) || trim((string) $event[$field]) === '') {
                throw SifException::validation('Missing debt claim event field: ' . $field);
            }
        }

        $metadata = $event['metadata'] ?? null;
        if ($metadata !== null && !is_array($metadata)) {
            throw SifException::validation('Debt claim event metadata must be an array');
        }

        $uuid = $this->uuidGenerator->generate();
        $db->prepare(
            'INSERT INTO debt_claim_event (
                UUID_EVENT, UUID_CLAIM, ACTION, RESULT, FROM_STAGE, TO_STAGE,
                OUTSTANDING_AMOUNT, IDEMPOTENCY_KEY, PAYLOAD_HASH, REQUEST_ID,
                CORRELATION_ID, ACTOR_TYPE, ACTOR_ID, ACTOR_ROLE, REASON_CODE,
                UUID_NOTIFICATION, METADATA_JSON, OCCURRED_AT
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uuid,
            trim((string) $event['uuid_claim']),
            strtoupper(trim((string) $event['action'])),
            strtoupper(trim((string) $event['result'])),
            $this->optionalUpper($event['from_stage'] ?? null),
            strtoupper(trim((string) $event['to_stage'])),
            trim((string) $event['outstanding_amount']),
            trim((string) $event['idempotency_key']),
            $this->idempotency->calculateHash($idempotencyPayload),
            trim((string) $event['request_id']),
            trim((string) $event['correlation_id']),
            strtoupper(trim((string) $event['actor_type'])),
            $this->optionalString($event['actor_id'] ?? null),
            $this->optionalString($event['actor_role'] ?? null),
            strtoupper(trim((string) $event['reason_code'])),
            $this->optionalString($event['uuid_notification'] ?? null),
            $metadata === null
                ? null
                : json_encode(
                    $metadata,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
                ),
            trim((string) $event['occurred_at']),
        ]);

        $row = $this->findEventByIdempotencyKey(
            $db,
            trim((string) $event['idempotency_key']),
            true
        );
        if ($row === null) {
            throw new \RuntimeException('Created debt claim event could not be loaded');
        }

        return $row;
    }

    private function optionalUpper(mixed $value): ?string
    {
        $value = $this->optionalString($value);
        return $value === null ? null : strtoupper($value);
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
