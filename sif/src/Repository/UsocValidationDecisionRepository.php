<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

final class UsocValidationDecisionRepository
{
    public function __construct(private UuidGenerator $uuids)
    {
    }

    public function begin(
        \PDO $db,
        string $requestId,
        string $correlationId,
        int $idInsc,
        int $desiredValidDesc,
        string $actorId,
        array $roles,
        int $legacyBeforeValidDesc
    ): array {
        $existing = $this->findByRequestId($db, $requestId, true);
        if ($existing !== null) {
            $this->assertSameRequest($existing, $idInsc, $desiredValidDesc, $actorId);
            return $existing;
        }

        $stmt = $db->prepare(
            'INSERT INTO usoc_validation_decision (
                UUID_DECISION, REQUEST_ID, CORRELATION_ID, ID_INSC,
                DESIRED_VALID_DESC, STATE, ACTOR_ID, ACTOR_ROLES,
                LEGACY_BEFORE_VALID_DESC
             ) VALUES (?, ?, ?, ?, ?, \'REQUESTED\', ?, ?, ?)'
        );
        try {
            $stmt->execute([
                $this->uuids->generate(),
                $requestId,
                $correlationId,
                $idInsc,
                $desiredValidDesc,
                $actorId,
                implode(',', $this->normalizeRoles($roles)),
                $legacyBeforeValidDesc,
            ]);
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }
            $raced = $this->findByRequestId($db, $requestId, true);
            if ($raced === null) {
                throw $exception;
            }
            $this->assertSameRequest($raced, $idInsc, $desiredValidDesc, $actorId);
            return $raced;
        }

        return $this->findByRequestId($db, $requestId, true)
            ?? throw new \RuntimeException('USOC validation decision could not be reloaded after insert');
    }

    public function markCommitted(
        \PDO $db,
        string $requestId,
        int $legacyAfterValidDesc,
        string $legacyStateHash
    ): array {
        $existing = $this->findByRequestId($db, $requestId, true);
        if ($existing === null) {
            throw SifException::conflict('USOC validation decision not found');
        }
        if ((int) $existing['DESIRED_VALID_DESC'] !== $legacyAfterValidDesc) {
            throw SifException::conflict('USOC validation legacy state does not match requested decision');
        }
        if ((string) $existing['STATE'] === 'COMMITTED') {
            if ((int) $existing['LEGACY_AFTER_VALID_DESC'] !== $legacyAfterValidDesc) {
                throw SifException::conflict('USOC validation decision already committed differently');
            }
            return $existing;
        }
        if ((string) $existing['STATE'] === 'REVIEW_REQUIRED') {
            throw SifException::conflict('USOC validation decision requires review');
        }

        $stmt = $db->prepare(
            "UPDATE usoc_validation_decision
             SET STATE = 'COMMITTED',
                 LEGACY_AFTER_VALID_DESC = ?,
                 LEGACY_STATE_HASH = ?,
                 COMMITTED_AT = CURRENT_TIMESTAMP(6),
                 LAST_RECONCILED_AT = CURRENT_TIMESTAMP(6),
                 REVIEW_REASON = NULL
             WHERE REQUEST_ID = ? AND STATE = 'REQUESTED'"
        );
        $stmt->execute([$legacyAfterValidDesc, $legacyStateHash, $requestId]);

        return $this->findByRequestId($db, $requestId, true)
            ?? throw new \RuntimeException('USOC validation decision could not be reloaded after commit');
    }

    public function markReviewRequired(
        \PDO $db,
        string $requestId,
        int $legacyAfterValidDesc,
        string $legacyStateHash,
        string $reason
    ): array {
        $stmt = $db->prepare(
            "UPDATE usoc_validation_decision
             SET STATE = 'REVIEW_REQUIRED',
                 LEGACY_AFTER_VALID_DESC = ?,
                 LEGACY_STATE_HASH = ?,
                 LAST_RECONCILED_AT = CURRENT_TIMESTAMP(6),
                 REVIEW_REASON = ?
             WHERE REQUEST_ID = ? AND STATE <> 'COMMITTED'"
        );
        $stmt->execute([$legacyAfterValidDesc, $legacyStateHash, $reason, $requestId]);

        return $this->findByRequestId($db, $requestId, true)
            ?? throw SifException::conflict('USOC validation decision not found');
    }

    public function findRequested(\PDO $db, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $stmt = $db->prepare(
            "SELECT *
             FROM usoc_validation_decision
             WHERE STATE = 'REQUESTED'
             ORDER BY REQUESTED_AT ASC, ID ASC
             LIMIT ?"
        );
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findByRequestId(\PDO $db, string $requestId, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM usoc_validation_decision WHERE REQUEST_ID = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$requestId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function assertSameRequest(
        array $existing,
        int $idInsc,
        int $desiredValidDesc,
        string $actorId
    ): void {
        if (
            (int) $existing['ID_INSC'] !== $idInsc
            || (int) $existing['DESIRED_VALID_DESC'] !== $desiredValidDesc
            || (string) $existing['ACTOR_ID'] !== $actorId
        ) {
            throw SifException::conflict('USOC validation request id already exists with different payload');
        }
    }

    private function normalizeRoles(array $roles): array
    {
        $normalized = [];
        foreach ($roles as $role) {
            $role = strtoupper(trim((string) $role));
            if ($role !== '') {
                $normalized[$role] = true;
            }
        }
        $result = array_keys($normalized);
        sort($result, SORT_STRING);
        return $result;
    }
}
