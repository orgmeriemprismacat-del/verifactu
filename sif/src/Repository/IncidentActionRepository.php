<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\PayloadIdempotencyValidator;

final class IncidentActionRepository
{
    public function __construct(private ?UuidGenerator $uuidGenerator = null)
    {
        $this->uuidGenerator ??= new UuidGenerator();
    }

    public function append(\PDO $db, array $action): array
    {
        $incidentId = (int) ($action['incident_id'] ?? 0);
        $actionType = strtoupper(trim((string) ($action['action_type'] ?? '')));
        $previousStatus = $this->nullableUpper($action, 'previous_status', 30);
        $newStatus = strtoupper(trim((string) ($action['new_status'] ?? '')));
        $severity = strtoupper(trim((string) ($action['severity'] ?? 'MEDIUM')));
        $assigneeId = $this->nullable($action, 'assignee_id', 120);
        $actorId = $this->nullable($action, 'actor_id', 120);
        $actorRole = $this->nullableUpper($action, 'actor_role', 80);
        $reasonCode = strtoupper(trim((string) ($action['reason_code'] ?? '')));
        $details = $this->nullableText($action, 'details');
        $correlationId = trim((string) ($action['correlation_id'] ?? ''));
        $idempotencyKey = trim((string) ($action['idempotency_key'] ?? ''));
        $evidenceJson = $this->encodeEvidence($action['evidence'] ?? null);

        if ($incidentId < 1) {
            throw SifException::validation('Invalid incident action incident_id');
        }
        if ($actionType === '' || strlen($actionType) > 50) {
            throw SifException::validation('Invalid incident action type');
        }
        if ($newStatus === '' || strlen($newStatus) > 30) {
            throw SifException::validation('Invalid incident action status');
        }
        if (!in_array($severity, ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'], true)) {
            throw SifException::validation('Invalid incident action severity');
        }
        if ($reasonCode === '' || strlen($reasonCode) > 80) {
            throw SifException::validation('Invalid incident action reason');
        }
        if ($correlationId === '' || strlen($correlationId) > 120) {
            throw SifException::validation('Invalid incident action correlation');
        }
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 140) {
            throw SifException::validation('Invalid incident action idempotency key');
        }

        $idempotencyPayload = [
            'incident_id' => $incidentId,
            'action_type' => $actionType,
            'new_status' => $newStatus,
            'severity' => $severity,
            'assignee_id' => $assigneeId,
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'reason_code' => $reasonCode,
            'details' => $details,
            'evidence' => $action['evidence'] ?? null,
        ];
        $idempotencyValidator = new PayloadIdempotencyValidator();
        $payloadHash = $idempotencyValidator->calculateHash($idempotencyPayload);

        $existing = $this->findByIdempotencyKey($db, $idempotencyKey);
        if ($existing !== null) {
            $idempotencyValidator->assertMatches(
                $idempotencyPayload,
                (string) ($existing['PAYLOAD_HASH'] ?? '')
            );

            return [
                'reused' => true,
                'action_id' => (int) $existing['ID'],
                'uuid_action' => (string) $existing['UUID_ACTION'],
            ];
        }

        $uuidAction = $this->uuidGenerator->generate();

        try {
            $db->prepare(
                'INSERT INTO sif_incident_action (
                    UUID_ACTION, IDEMPOTENCY_KEY, PAYLOAD_HASH, INCIDENT_ID, ACTION_TYPE,
                    PREVIOUS_STATUS, NEW_STATUS, SEVERITY, ASSIGNEE_ID,
                    ACTOR_ID, ACTOR_ROLE, REASON_CODE, DETAILS,
                    EVIDENCE_JSON, CORRELATION_ID
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $uuidAction,
                $idempotencyKey,
                $payloadHash,
                $incidentId,
                $actionType,
                $previousStatus,
                $newStatus,
                $severity,
                $assigneeId,
                $actorId,
                $actorRole,
                $reasonCode,
                $details,
                $evidenceJson,
                $correlationId,
            ]);
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                $existing = $this->findByIdempotencyKey($db, $idempotencyKey);
                if ($existing !== null) {
                    $idempotencyValidator->assertMatches(
                        $idempotencyPayload,
                        (string) ($existing['PAYLOAD_HASH'] ?? '')
                    );

                    return [
                        'reused' => true,
                        'action_id' => (int) $existing['ID'],
                        'uuid_action' => (string) $existing['UUID_ACTION'],
                    ];
                }
            }

            throw $exception;
        }

        return [
            'reused' => false,
            'action_id' => (int) $db->lastInsertId(),
            'uuid_action' => $uuidAction,
        ];
    }

    public function listForIncident(\PDO $db, int $incidentId): array
    {
        if ($incidentId < 1) {
            throw SifException::validation('Invalid incident id');
        }

        $stmt = $db->prepare(
            'SELECT * FROM sif_incident_action
             WHERE INCIDENT_ID = ?
             ORDER BY CREATED_AT ASC, ID ASC'
        );
        $stmt->execute([$incidentId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function findByIdempotencyKey(\PDO $db, string $key): ?array
    {
        $stmt = $db->prepare('SELECT * FROM sif_incident_action WHERE IDEMPOTENCY_KEY = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function nullable(array $input, string $field, int $maxLength): ?string
    {
        $value = $input[$field] ?? null;
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '' || strlen($value) > $maxLength) {
            throw SifException::validation('Invalid incident action field: ' . $field);
        }

        return $value;
    }

    private function nullableUpper(array $input, string $field, int $maxLength): ?string
    {
        $value = $this->nullable($input, $field, $maxLength);
        return $value === null ? null : strtoupper($value);
    }

    private function nullableText(array $input, string $field): ?string
    {
        $value = $input[$field] ?? null;
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function encodeEvidence(mixed $evidence): ?string
    {
        if ($evidence === null) {
            return null;
        }
        if (!is_array($evidence)) {
            throw SifException::validation('Incident evidence must be an object or array');
        }

        return json_encode(
            $evidence,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }
}
