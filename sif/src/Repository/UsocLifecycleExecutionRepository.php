<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

final class UsocLifecycleExecutionRepository
{
    public function __construct(private UuidGenerator $uuids)
    {
    }

    public function begin(
        \PDO $db,
        string $requestId,
        string $correlationId,
        int $idInsc,
        int $idpag,
        string $operation,
        string $actorId,
        array $roles,
        array $request,
        array $plan
    ): array {
        $operation = strtoupper(trim($operation));
        $requestHash = $this->hash($request);
        $planHash = $this->hash($plan);

        $existing = $this->findByRequestId($db, $requestId, true);
        if ($existing !== null) {
            $this->assertSameRequest(
                $existing,
                $idInsc,
                $idpag,
                $operation,
                $actorId,
                $requestHash
            );
            return $existing;
        }

        $stmt = $db->prepare(
            'INSERT INTO usoc_lifecycle_execution (
                UUID_EXECUTION, REQUEST_ID, CORRELATION_ID,
                ID_INSC, IDPAG, OPERATION, STATE,
                ACTOR_ID, ACTOR_ROLES, REQUEST_HASH, REQUEST_JSON,
                PLAN_HASH, PLAN_JSON
             ) VALUES (?, ?, ?, ?, ?, ?, \'REQUESTED\', ?, ?, ?, ?, ?, ?)'
        );

        $requestJson = json_encode(
            $request,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $planJson = json_encode(
            $plan,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        try {
            $stmt->execute([
                $this->uuids->generate(),
                $requestId,
                $correlationId,
                $idInsc,
                $idpag,
                $operation,
                $actorId,
                implode(',', $this->normalizeRoles($roles)),
                $requestHash,
                $requestJson,
                $planHash,
                $planJson,
            ]);
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            $raced = $this->findByRequestId($db, $requestId, true);
            if ($raced === null) {
                throw $exception;
            }

            $this->assertSameRequest(
                $raced,
                $idInsc,
                $idpag,
                $operation,
                $actorId,
                $requestHash
            );
            return $raced;
        }

        return $this->findByRequestId($db, $requestId, true)
            ?? throw new \RuntimeException('USOC lifecycle execution could not be reloaded');
    }

    public function recordRequestedResult(
        \PDO $db,
        string $requestId,
        array $result
    ): array {
        $existing = $this->findByRequestId($db, $requestId, true);
        if ($existing === null) {
            throw SifException::conflict('USOC lifecycle execution not found');
        }
        if ((string) $existing['STATE'] !== 'REQUESTED') {
            throw SifException::conflict(
                'USOC lifecycle requested result can only be recorded while REQUESTED'
            );
        }

        $resultJson = json_encode(
            $result,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $storedJson = trim((string) ($existing['RESULT_JSON'] ?? ''));
        if ($storedJson !== '') {
            $stored = json_decode($storedJson, true);
            if (!is_array($stored) || $this->hash($stored) !== $this->hash($result)) {
                throw SifException::conflict(
                    'USOC lifecycle requested result already differs from payload'
                );
            }

            return $existing;
        }

        $stmt = $db->prepare(
            "UPDATE usoc_lifecycle_execution
             SET RESULT_JSON = ?
             WHERE REQUEST_ID = ?
               AND STATE = 'REQUESTED'
               AND RESULT_JSON IS NULL"
        );
        $stmt->execute([$resultJson, $requestId]);

        if ($stmt->rowCount() !== 1) {
            $raced = $this->findByRequestId($db, $requestId, true);
            if (
                $raced === null
                || (string) $raced['STATE'] !== 'REQUESTED'
                || trim((string) ($raced['RESULT_JSON'] ?? '')) === ''
            ) {
                throw SifException::conflict(
                    'USOC lifecycle requested result changed during update'
                );
            }

            $stored = json_decode((string) $raced['RESULT_JSON'], true);
            if (!is_array($stored) || $this->hash($stored) !== $this->hash($result)) {
                throw SifException::conflict(
                    'USOC lifecycle requested result already differs from payload'
                );
            }

            return $raced;
        }

        return $this->findByRequestId($db, $requestId, true)
            ?? throw SifException::conflict('USOC lifecycle execution not found');
    }

    public function advanceRequestedResult(
        \PDO $db,
        string $requestId,
        string $expectedPhase,
        array $result
    ): array {
        $existing = $this->findByRequestId($db, $requestId, true);
        if ($existing === null) {
            throw SifException::conflict('USOC lifecycle execution not found');
        }
        if ((string) $existing['STATE'] !== 'REQUESTED') {
            throw SifException::conflict(
                'USOC lifecycle requested result can only advance while REQUESTED'
            );
        }

        $storedJson = trim((string) ($existing['RESULT_JSON'] ?? ''));
        if ($storedJson === '') {
            throw SifException::conflict(
                'USOC lifecycle requested result has not been recorded'
            );
        }

        $stored = json_decode($storedJson, true);
        if (!is_array($stored)) {
            throw SifException::conflict(
                'USOC lifecycle requested result is invalid'
            );
        }

        $expectedPhase = strtoupper(trim($expectedPhase));
        $storedPhase = strtoupper(trim((string) ($stored['phase'] ?? '')));
        $newPhase = strtoupper(trim((string) ($result['phase'] ?? '')));
        if ($expectedPhase === '' || $newPhase === '') {
            throw SifException::validation(
                'USOC lifecycle requested result phase is required'
            );
        }

        if ($storedPhase === $newPhase) {
            if ($this->hash($stored) !== $this->hash($result)) {
                throw SifException::conflict(
                    'USOC lifecycle requested result already differs from advanced payload'
                );
            }
            return $existing;
        }

        if ($storedPhase !== $expectedPhase) {
            throw SifException::conflict(
                'USOC lifecycle requested result is not in expected phase'
            );
        }

        $resultJson = json_encode(
            $result,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $stmt = $db->prepare(
            "UPDATE usoc_lifecycle_execution
             SET RESULT_JSON = ?
             WHERE REQUEST_ID = ?
               AND STATE = 'REQUESTED'"
        );
        $stmt->execute([$resultJson, $requestId]);

        return $this->findByRequestId($db, $requestId, true)
            ?? throw SifException::conflict('USOC lifecycle execution not found');
    }

    public function complete(
        \PDO $db,
        string $requestId,
        array $result
    ): array {
        $resultJson = json_encode(
            $result,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $stmt = $db->prepare(
            "UPDATE usoc_lifecycle_execution
             SET STATE = 'COMPLETED',
                 RESULT_JSON = ?,
                 REVIEW_REASON = NULL,
                 COMPLETED_AT = COALESCE(COMPLETED_AT, CURRENT_TIMESTAMP(6))
             WHERE REQUEST_ID = ?
               AND STATE IN ('REQUESTED', 'COMPLETED')"
        );
        $stmt->execute([$resultJson, $requestId]);

        return $this->findByRequestId($db, $requestId, true)
            ?? throw SifException::conflict('USOC lifecycle execution not found');
    }

    public function markReviewRequired(
        \PDO $db,
        string $requestId,
        string $reason,
        ?array $result = null
    ): array {
        $resultJson = $result === null ? null : json_encode(
            $result,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $stmt = $db->prepare(
            "UPDATE usoc_lifecycle_execution
             SET STATE = 'REVIEW_REQUIRED',
                 RESULT_JSON = ?,
                 REVIEW_REASON = ?
             WHERE REQUEST_ID = ?
               AND STATE <> 'COMPLETED'"
        );
        $stmt->execute([$resultJson, $reason, $requestId]);

        return $this->findByRequestId($db, $requestId, true)
            ?? throw SifException::conflict('USOC lifecycle execution not found');
    }

    public function findByRequestId(
        \PDO $db,
        string $requestId,
        bool $forUpdate = false
    ): ?array {
        $sql = 'SELECT * FROM usoc_lifecycle_execution WHERE REQUEST_ID = ?';
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
        int $idpag,
        string $operation,
        string $actorId,
        string $requestHash
    ): void {
        if (
            (int) $existing['ID_INSC'] !== $idInsc
            || (int) $existing['IDPAG'] !== $idpag
            || (string) $existing['OPERATION'] !== $operation
            || (string) $existing['ACTOR_ID'] !== $actorId
            || (string) $existing['REQUEST_HASH'] !== $requestHash
        ) {
            throw SifException::conflict(
                'USOC lifecycle request id already exists with different payload'
            );
        }
    }

    private function hash(array $request): string
    {
        return hash(
            'sha256',
            json_encode(
                $request,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
            )
        );
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
