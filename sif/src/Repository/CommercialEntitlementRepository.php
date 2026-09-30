<?php

declare(strict_types=1);

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

final class CommercialEntitlementRepository
{
    public function __construct(private UuidGenerator $uuids)
    {
    }

    public function findByCodeHash(\PDO $db, string $codeHash, bool $forUpdate = false): ?array
    {
        $codeHash = strtolower(trim($codeHash));
        if (!preg_match('/^[a-f0-9]{64}$/D', $codeHash)) {
            throw SifException::validation('Invalid entitlement code hash');
        }

        $sql = 'SELECT * FROM commercial_entitlement WHERE CODE_HASH = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$codeHash]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function reserve(
        \PDO $db,
        array $entitlement,
        string $correlationId,
        string $actorId,
        string $idempotencyKey,
        ?\DateTimeImmutable $now = null
    ): array {
        $this->assertGift($entitlement);
        $status = strtoupper((string) $entitlement['STATUS']);

        if ($status === 'RESERVED') {
            $event = $this->findEquivalentEvent($db, (string) $entitlement['UUID_ENTITLEMENT'], 'RESERVE', $idempotencyKey);
            if ($event !== null) {
                return ['status' => 'RESERVED', 'reused' => true];
            }
            throw SifException::conflict('Gift entitlement is already reserved');
        }

        if (!in_array($status, ['ISSUED', 'ACTIVE'], true)) {
            throw SifException::conflict('Gift entitlement cannot be reserved from current status');
        }

        $at = $this->utc($now);
        $stmt = $db->prepare(
            "UPDATE commercial_entitlement
             SET STATUS = 'RESERVED', RESERVED_AT = ?
             WHERE UUID_ENTITLEMENT = ? AND STATUS = ?"
        );
        $stmt->execute([$at, $entitlement['UUID_ENTITLEMENT'], $status]);
        if ($stmt->rowCount() !== 1) {
            throw SifException::conflict('Gift entitlement changed concurrently');
        }

        $this->appendEvent($db, [
            'uuid_entitlement' => (string) $entitlement['UUID_ENTITLEMENT'],
            'action' => 'RESERVE',
            'result' => 'SUCCESS',
            'from_status' => $status,
            'to_status' => 'RESERVED',
            'uuid_operation' => null,
            'actor_id' => $actorId,
            'correlation_id' => $correlationId,
            'causation_id' => $idempotencyKey,
            'reason_code' => 'UC018_REDEEM',
            'changeset' => ['idempotency_key_hash' => hash('sha256', $idempotencyKey)],
            'occurred_at' => $at,
        ]);

        return ['status' => 'RESERVED', 'reused' => false];
    }

    public function consume(
        \PDO $db,
        array $entitlement,
        string $uuidOperation,
        string $correlationId,
        string $actorId,
        string $idempotencyKey,
        ?\DateTimeImmutable $now = null
    ): array {
        $this->assertGift($entitlement);
        $uuidEntitlement = (string) $entitlement['UUID_ENTITLEMENT'];
        $status = strtoupper((string) $entitlement['STATUS']);

        if ($status === 'CONSUMED') {
            if ((string) ($entitlement['CONSUMED_UUID_OPERATION'] ?? '') === $uuidOperation) {
                $event = $this->findEquivalentEvent($db, $uuidEntitlement, 'CONSUME', $idempotencyKey);
                return [
                    'status' => 'CONSUMED',
                    'reused' => $event !== null,
                    'uuid_operation' => $uuidOperation,
                ];
            }
            throw SifException::conflict('Gift entitlement was already consumed by another operation');
        }

        if ($status !== 'RESERVED') {
            throw SifException::conflict('Gift entitlement must be reserved before consumption');
        }

        $at = $this->utc($now);
        $stmt = $db->prepare(
            "UPDATE commercial_entitlement
             SET STATUS = 'CONSUMED', CONSUMED_UUID_OPERATION = ?, CONSUMED_AT = ?
             WHERE UUID_ENTITLEMENT = ? AND STATUS = 'RESERVED'"
        );
        $stmt->execute([$uuidOperation, $at, $uuidEntitlement]);
        if ($stmt->rowCount() !== 1) {
            throw SifException::conflict('Gift entitlement changed concurrently');
        }

        $this->appendEvent($db, [
            'uuid_entitlement' => $uuidEntitlement,
            'action' => 'CONSUME',
            'result' => 'SUCCESS',
            'from_status' => 'RESERVED',
            'to_status' => 'CONSUMED',
            'uuid_operation' => $uuidOperation,
            'actor_id' => $actorId,
            'correlation_id' => $correlationId,
            'causation_id' => $idempotencyKey,
            'reason_code' => 'UC018_REDEEM',
            'changeset' => ['idempotency_key_hash' => hash('sha256', $idempotencyKey)],
            'occurred_at' => $at,
        ]);

        return ['status' => 'CONSUMED', 'reused' => false, 'uuid_operation' => $uuidOperation];
    }

    public function release(
        \PDO $db,
        array $entitlement,
        string $correlationId,
        string $actorId,
        string $idempotencyKey,
        string $reasonCode,
        ?\DateTimeImmutable $now = null
    ): array {
        $this->assertGift($entitlement);
        $status = strtoupper((string) $entitlement['STATUS']);
        if ($status !== 'RESERVED') {
            throw SifException::conflict('Only a reserved gift entitlement can be released');
        }

        $at = $this->utc($now);
        $target = $this->isExpired($entitlement, $now) ? 'EXPIRED' : 'ACTIVE';
        $stmt = $db->prepare(
            'UPDATE commercial_entitlement
             SET STATUS = ?, RESERVED_AT = NULL
             WHERE UUID_ENTITLEMENT = ? AND STATUS = ?'
        );
        $stmt->execute([$target, $entitlement['UUID_ENTITLEMENT'], 'RESERVED']);
        if ($stmt->rowCount() !== 1) {
            throw SifException::conflict('Gift entitlement changed concurrently');
        }

        $this->appendEvent($db, [
            'uuid_entitlement' => (string) $entitlement['UUID_ENTITLEMENT'],
            'action' => 'RELEASE',
            'result' => 'SUCCESS',
            'from_status' => 'RESERVED',
            'to_status' => $target,
            'uuid_operation' => null,
            'actor_id' => $actorId,
            'correlation_id' => $correlationId,
            'causation_id' => $idempotencyKey,
            'reason_code' => strtoupper(trim($reasonCode)),
            'changeset' => ['idempotency_key_hash' => hash('sha256', $idempotencyKey)],
            'occurred_at' => $at,
        ]);

        return ['status' => $target, 'reused' => false];
    }

    public function appendEvent(\PDO $db, array $event): string
    {
        $changeset = json_encode($event['changeset'] ?? null, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $uuid = $this->uuids->generate();
        $stmt = $db->prepare(
            'INSERT INTO commercial_entitlement_event
             (UUID_EVENT, UUID_ENTITLEMENT, ACTION, RESULT, FROM_STATUS, TO_STATUS,
              UUID_OPERATION, ACTOR_TYPE, ACTOR_ID, CORRELATION_ID, CAUSATION_ID,
              REASON_CODE, CHANGESET_JSON, OCCURRED_AT)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $uuid,
            $event['uuid_entitlement'],
            strtoupper((string) $event['action']),
            strtoupper((string) $event['result']),
            $event['from_status'] ?? null,
            $event['to_status'] ?? null,
            $event['uuid_operation'] ?? null,
            'PERSON',
            trim((string) ($event['actor_id'] ?? '')) ?: null,
            $event['correlation_id'],
            $event['causation_id'] ?? null,
            $event['reason_code'] ?? null,
            $changeset,
            $event['occurred_at'],
        ]);

        return $uuid;
    }

    public function isExpired(array $entitlement, ?\DateTimeImmutable $now = null): bool
    {
        $expiresAt = trim((string) ($entitlement['EXPIRES_AT'] ?? ''));
        if ($expiresAt === '') {
            return false;
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expiry = new \DateTimeImmutable($expiresAt, new \DateTimeZone('UTC'));

        return $expiry <= $now->setTimezone(new \DateTimeZone('UTC'));
    }

    private function findEquivalentEvent(
        \PDO $db,
        string $uuidEntitlement,
        string $action,
        string $idempotencyKey
    ): ?array {
        $stmt = $db->prepare(
            'SELECT * FROM commercial_entitlement_event
             WHERE UUID_ENTITLEMENT = ? AND ACTION = ? AND CAUSATION_ID = ?
             ORDER BY RECORDED_AT DESC LIMIT 1'
        );
        $stmt->execute([$uuidEntitlement, $action, $idempotencyKey]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function assertGift(array $entitlement): void
    {
        if (strtoupper((string) ($entitlement['ENTITLEMENT_TYPE'] ?? '')) !== 'GIFT') {
            throw SifException::conflict('Entitlement is not a gift');
        }
    }

    private function utc(?\DateTimeImmutable $now): string
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
