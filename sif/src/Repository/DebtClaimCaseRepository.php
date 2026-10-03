<?php

declare(strict_types=1);

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\PayloadIdempotencyValidator;

final class DebtClaimCaseRepository
{
    public function __construct(
        private UuidGenerator $uuids,
        private ?PayloadIdempotencyValidator $idempotency = null
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
        $stmt->execute([$uuidFactura]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function create(
        \PDO $db,
        string $uuidFactura,
        string $outstanding,
        ?string $recipientType,
        ?string $recipientHash,
        string $correlationId
    ): array {
        $uuidClaim = $this->uuids->generate();

        $db->prepare(
            'INSERT INTO debt_claim_case (
                UUID_CLAIM, UUID_FACTURA, STATUS, CURRENT_STAGE, VERSION_NO,
                OUTSTANDING_AMOUNT, RECIPIENT_TYPE, RECIPIENT_HASH, CORRELATION_ID
            ) VALUES (?, ?, \'OPEN\', \'DETECTED\', 0, ?, ?, ?, ?)'
        )->execute([
            $uuidClaim,
            $uuidFactura,
            $outstanding,
            $recipientType,
            $recipientHash,
            $correlationId,
        ]);

        $created = $this->findByInvoice($db, $uuidFactura, true);
        if ($created === null) {
            throw new \RuntimeException('Created debt claim case could not be loaded');
        }

        return $created;
    }

    public function findReusableEvent(
        \PDO $db,
        string $idempotencyKey,
        array $payload
    ): ?array {
        $stmt = $db->prepare(
            'SELECT UUID_CLAIM_EVENT, UUID_CLAIM, UUID_FACTURA, EVENT_TYPE,
                    IDEMPOTENCY_KEY, PAYLOAD_HASH, VERSION_NO, OUTSTANDING_BEFORE,
                    OUTSTANDING_AFTER, RECIPIENT_TYPE, RECIPIENT_HASH, REASON_CODE,
                    CORRELATION_ID, REQUEST_ID
             FROM debt_claim_event
             WHERE IDEMPOTENCY_KEY = ?'
        );
        $stmt->execute([$idempotencyKey]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $this->idempotency->assertMatches($payload, (string) $row['PAYLOAD_HASH']);

        return [
            'uuid_claim_event' => (string) $row['UUID_CLAIM_EVENT'],
            'uuid_claim' => (string) $row['UUID_CLAIM'],
            'uuid_factura' => (string) $row['UUID_FACTURA'],
            'event_type' => (string) $row['EVENT_TYPE'],
            'version_no' => (int) $row['VERSION_NO'],
            'outstanding_before' => (string) $row['OUTSTANDING_BEFORE'],
            'outstanding_after' => (string) $row['OUTSTANDING_AFTER'],
            'recipient_type' => $row['RECIPIENT_TYPE'] === null ? null : (string) $row['RECIPIENT_TYPE'],
            'recipient_hash' => $row['RECIPIENT_HASH'] === null ? null : (string) $row['RECIPIENT_HASH'],
            'reason_code' => (string) $row['REASON_CODE'],
            'correlation_id' => (string) $row['CORRELATION_ID'],
            'request_id' => (string) $row['REQUEST_ID'],
            'idempotency_reused' => true,
        ];
    }

    public function appendEvent(\PDO $db, array $claim, array $event): array
    {
        foreach ([
            'event_type',
            'idempotency_key',
            'payload',
            'outstanding_before',
            'outstanding_after',
            'reason_code',
            'actor_type',
            'source_channel',
            'request_id',
            'correlation_id',
            'occurred_at',
        ] as $field) {
            if (!array_key_exists($field, $event) || $event[$field] === null || $event[$field] === '') {
                throw SifException::validation('Missing debt claim event field: ' . $field);
            }
        }
        if (!is_array($event['payload'])) {
            throw SifException::validation('Debt claim event payload must be an array');
        }

        $key = trim((string) $event['idempotency_key']);
        if ($key === '' || strlen($key) > 140) {
            throw SifException::validation('Invalid debt claim idempotency key');
        }

        $reused = $this->findReusableEvent($db, $key, $event['payload']);
        if ($reused !== null) {
            return $reused;
        }

        $version = (int) $claim['VERSION_NO'] + 1;
        $uuidEvent = $this->uuids->generate();
        $json = json_encode(
            $event['payload'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );
        $hash = $this->idempotency->calculateHash($event['payload']);

        $db->prepare(
            'INSERT INTO debt_claim_event (
                UUID_CLAIM_EVENT, UUID_CLAIM, UUID_FACTURA, EVENT_TYPE,
                IDEMPOTENCY_KEY, PAYLOAD_HASH, VERSION_NO,
                OUTSTANDING_BEFORE, OUTSTANDING_AFTER,
                RECIPIENT_TYPE, RECIPIENT_HASH, REASON_CODE,
                ACTOR_TYPE, ACTOR_ID, ACTOR_ROLE, SOURCE_CHANNEL,
                REQUEST_ID, CORRELATION_ID, PAYLOAD_JSON, OCCURRED_AT
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uuidEvent,
            (string) $claim['UUID_CLAIM'],
            (string) $claim['UUID_FACTURA'],
            strtoupper(trim((string) $event['event_type'])),
            $key,
            $hash,
            $version,
            $event['outstanding_before'],
            $event['outstanding_after'],
            $this->nullableUpper($event['recipient_type'] ?? null),
            $this->nullableLower($event['recipient_hash'] ?? null),
            strtoupper(trim((string) $event['reason_code'])),
            strtoupper(trim((string) $event['actor_type'])),
            $this->nullable($event['actor_id'] ?? null),
            $this->nullableUpper($event['actor_role'] ?? null),
            strtoupper(trim((string) $event['source_channel'])),
            trim((string) $event['request_id']),
            trim((string) $event['correlation_id']),
            $json,
            trim((string) $event['occurred_at']),
        ]);

        return [
            'uuid_claim_event' => $uuidEvent,
            'uuid_claim' => (string) $claim['UUID_CLAIM'],
            'uuid_factura' => (string) $claim['UUID_FACTURA'],
            'event_type' => strtoupper(trim((string) $event['event_type'])),
            'version_no' => $version,
            'outstanding_before' => (string) $event['outstanding_before'],
            'outstanding_after' => (string) $event['outstanding_after'],
            'recipient_type' => $this->nullableUpper($event['recipient_type'] ?? null),
            'recipient_hash' => $this->nullableLower($event['recipient_hash'] ?? null),
            'reason_code' => strtoupper(trim((string) $event['reason_code'])),
            'correlation_id' => trim((string) $event['correlation_id']),
            'request_id' => trim((string) $event['request_id']),
            'idempotency_reused' => false,
        ];
    }

    public function updateCase(
        \PDO $db,
        string $uuidClaim,
        int $expectedVersion,
        string $status,
        string $stage,
        string $outstanding,
        ?string $recipientType,
        ?string $recipientHash,
        string $correlationId,
        string $lastEventAt
    ): void {
        $stmt = $db->prepare(
            'UPDATE debt_claim_case
             SET STATUS = ?, CURRENT_STAGE = ?, VERSION_NO = VERSION_NO + 1,
                 OUTSTANDING_AMOUNT = ?, RECIPIENT_TYPE = ?, RECIPIENT_HASH = ?,
                 CORRELATION_ID = ?, LAST_EVENT_AT = ?
             WHERE UUID_CLAIM = ? AND VERSION_NO = ?'
        );
        $stmt->execute([
            strtoupper(trim($status)),
            strtoupper(trim($stage)),
            $outstanding,
            $this->nullableUpper($recipientType),
            $this->nullableLower($recipientHash),
            $correlationId,
            $lastEventAt,
            $uuidClaim,
            $expectedVersion,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw SifException::conflict('Debt claim case version changed concurrently');
        }
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

    private function nullableLower(mixed $value): ?string
    {
        $value = $this->nullable($value);

        return $value === null ? null : strtolower($value);
    }
}
