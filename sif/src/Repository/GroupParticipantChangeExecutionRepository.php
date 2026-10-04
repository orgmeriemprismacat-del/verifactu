<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

final class GroupParticipantChangeExecutionRepository
{
    public function __construct(private UuidGenerator $uuidGenerator)
    {
    }

    public function createOrReuse(\PDO $db, array $input): array
    {
        foreach ([
            'idempotency_key','change_type','uuid_factura','id_insc',
            'expected_fingerprint','decision_hash','correlation_id','actor_id','plan'
        ] as $field) {
            if (!array_key_exists($field, $input)) {
                throw SifException::validation('Missing group change execution field ' . $field);
            }
        }

        $key = trim((string) $input['idempotency_key']);
        $existing = $this->findByIdempotencyKey($db, $key, true);
        if ($existing !== null) {
            $this->assertSameCommand($existing, $input);
            $existing['idempotency_reused'] = true;
            return $existing;
        }

        $uuid = $this->uuidGenerator->generate();
        $planJson = json_encode(
            $input['plan'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $db->prepare(
            'INSERT INTO group_participant_change_execution (
                UUID_EXECUTION, IDEMPOTENCY_KEY, CHANGE_TYPE, UUID_FACTURA,
                ID_INSC, IDPAG, EXPECTED_FINGERPRINT, DECISION_HASH,
                STATUS, CURRENT_STEP, CORRELATION_ID, ACTOR_ID, PLAN_JSON
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'PLANNED\', NULL, ?, ?, ?)'
        )->execute([
            $uuid,
            $key,
            strtoupper(trim((string) $input['change_type'])),
            trim((string) $input['uuid_factura']),
            (int) $input['id_insc'],
            isset($input['idpag']) ? (int) $input['idpag'] : null,
            strtolower(trim((string) $input['expected_fingerprint'])),
            strtolower(trim((string) $input['decision_hash'])),
            trim((string) $input['correlation_id']),
            trim((string) $input['actor_id']),
            $planJson,
        ]);

        $row = $this->findByUuid($db, $uuid, true);
        if ($row === null) {
            throw new \RuntimeException('Created group participant change execution could not be loaded');
        }
        $row['idempotency_reused'] = false;

        return $row;
    }

    public function findByIdempotencyKey(\PDO $db, string $key, bool $forUpdate = false): ?array
    {
        return $this->findOne(
            $db,
            'SELECT * FROM group_participant_change_execution WHERE IDEMPOTENCY_KEY = ?',
            [trim($key)],
            $forUpdate
        );
    }

    public function findByUuid(\PDO $db, string $uuid, bool $forUpdate = false): ?array
    {
        return $this->findOne(
            $db,
            'SELECT * FROM group_participant_change_execution WHERE UUID_EXECUTION = ?',
            [trim($uuid)],
            $forUpdate
        );
    }

    public function beginStep(
        \PDO $db,
        string $uuidExecution,
        string $stepName,
        int $order,
        string $inputHash
    ): array {
        $stepName = strtoupper(trim($stepName));
        $inputHash = strtolower(trim($inputHash));

        $existing = $this->findStep($db, $uuidExecution, $stepName, true);
        if ($existing !== null) {
            if ((string) $existing['INPUT_HASH'] !== $inputHash) {
                throw SifException::conflict('Group change step input changed on retry');
            }
            return $existing;
        }

        $stepKey = 'GROUP_CHANGE|' . $uuidExecution . '|STEP:' . $stepName;
        $db->prepare(
            'INSERT INTO group_participant_change_step (
                UUID_EXECUTION, STEP_NAME, ORDRE, STATUS,
                IDEMPOTENCY_KEY, INPUT_HASH, STARTED_AT
             ) VALUES (?, ?, ?, \'IN_PROGRESS\', ?, ?, NOW(6))'
        )->execute([$uuidExecution, $stepName, $order, $stepKey, $inputHash]);

        $db->prepare(
            "UPDATE group_participant_change_execution
             SET STATUS='IN_PROGRESS', CURRENT_STEP=?, STARTED_AT=COALESCE(STARTED_AT, NOW(6))
             WHERE UUID_EXECUTION=?"
        )->execute([$stepName, $uuidExecution]);

        $row = $this->findStep($db, $uuidExecution, $stepName, true);
        if ($row === null) {
            throw new \RuntimeException('Created group participant change step could not be loaded');
        }

        return $row;
    }

    public function completeStep(\PDO $db, string $uuidExecution, string $stepName, array $result): void
    {
        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $db->prepare(
            "UPDATE group_participant_change_step
             SET STATUS='COMPLETED', RESULT_JSON=?, ERROR_CODE=NULL, ERROR_MESSAGE=NULL,
                 COMPLETED_AT=COALESCE(COMPLETED_AT, NOW(6))
             WHERE UUID_EXECUTION=? AND STEP_NAME=?"
        )->execute([$json, $uuidExecution, strtoupper(trim($stepName))]);
    }

    public function failStep(
        \PDO $db,
        string $uuidExecution,
        string $stepName,
        string $errorCode,
        string $message
    ): void {
        $db->prepare(
            "UPDATE group_participant_change_step
             SET STATUS='FAILED', ERROR_CODE=?, ERROR_MESSAGE=?
             WHERE UUID_EXECUTION=? AND STEP_NAME=?"
        )->execute([
            substr(strtoupper(trim($errorCode)), 0, 80),
            substr(trim($message), 0, 500),
            $uuidExecution,
            strtoupper(trim($stepName)),
        ]);

        $db->prepare(
            "UPDATE group_participant_change_execution
             SET STATUS='FAILED', CURRENT_STEP=?, ERROR_CODE=?, ERROR_MESSAGE=?
             WHERE UUID_EXECUTION=?"
        )->execute([
            strtoupper(trim($stepName)),
            substr(strtoupper(trim($errorCode)), 0, 80),
            substr(trim($message), 0, 500),
            $uuidExecution,
        ]);
    }

    public function completeExecution(\PDO $db, string $uuidExecution, array $result): void
    {
        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $db->prepare(
            "UPDATE group_participant_change_execution
             SET STATUS='COMPLETED', CURRENT_STEP=NULL, RESULT_JSON=?,
                 ERROR_CODE=NULL, ERROR_MESSAGE=NULL, COMPLETED_AT=COALESCE(COMPLETED_AT, NOW(6))
             WHERE UUID_EXECUTION=?"
        )->execute([$json, $uuidExecution]);
    }

    public function steps(\PDO $db, string $uuidExecution): array
    {
        $stmt = $db->prepare(
            'SELECT * FROM group_participant_change_step
             WHERE UUID_EXECUTION=?
             ORDER BY ORDRE, ID'
        );
        $stmt->execute([$uuidExecution]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findStep(
        \PDO $db,
        string $uuidExecution,
        string $stepName,
        bool $forUpdate = false
    ): ?array {
        $sql = 'SELECT * FROM group_participant_change_step
                WHERE UUID_EXECUTION=? AND STEP_NAME=?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([$uuidExecution, strtoupper(trim($stepName))]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function assertSameCommand(array $existing, array $input): void
    {
        $planJson = json_encode(
            $input['plan'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $same =
            (string) $existing['CHANGE_TYPE'] === strtoupper(trim((string) $input['change_type']))
            && (string) $existing['UUID_FACTURA'] === trim((string) $input['uuid_factura'])
            && (int) $existing['ID_INSC'] === (int) $input['id_insc']
            && (int) ($existing['IDPAG'] ?? 0) === (int) ($input['idpag'] ?? 0)
            && (string) $existing['EXPECTED_FINGERPRINT'] === strtolower(trim((string) $input['expected_fingerprint']))
            && (string) $existing['DECISION_HASH'] === strtolower(trim((string) $input['decision_hash']))
            && (string) $existing['PLAN_JSON'] === $planJson;

        if (!$same) {
            throw SifException::conflict(
                'Group participant change idempotency key already exists with different command'
            );
        }
    }

    private function findOne(\PDO $db, string $sql, array $params, bool $forUpdate): ?array
    {
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
