<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\IncidentActionRepository;
use Prisma\Sif\Repository\IncidentRepository;

final class IncidentLifecycleService
{
    private array $readRoles;
    private array $manageRoles;

    public function __construct(
        private \PDO $db,
        private TransactionRunner $transactions,
        private IncidentRepository $incidents,
        private IncidentActionRepository $actions,
        array $readRoles,
        array $manageRoles
    ) {
        $this->readRoles = $this->normalizeRoles($readRoles);
        $this->manageRoles = $this->normalizeRoles($manageRoles);
    }

    public function list(array $actor, array $filters = [], int $limit = 50): array
    {
        $this->assertRead($actor);
        $rows = $this->incidents->list($this->db, $filters, $limit);

        return [
            'ok' => true,
            'count' => count($rows),
            'incidents' => $rows,
        ];
    }

    public function view(array $actor, int $incidentId): array
    {
        $this->assertRead($actor);
        $incident = $this->incidents->findById($this->db, $incidentId);
        if ($incident === null) {
            throw SifException::notFound('Incident not found');
        }

        return [
            'ok' => true,
            'incident' => $incident,
            'actions' => $this->actions->listForIncident($this->db, $incidentId),
        ];
    }

    public function open(array $actor, array $payload): array
    {
        $this->assertManage($actor);
        $idempotencyKey = $this->requiredString($payload, 'idempotency_key', 140);
        $correlationId = $this->correlationId($actor, $payload);

        return $this->transactions->run(function (\PDO $db) use (
            $actor,
            $payload,
            $idempotencyKey,
            $correlationId
        ): array {
            $result = $this->incidents->openDetailed($db, [
                'uuid_factura' => $payload['uuid_factura'] ?? null,
                'uuid_payment' => $payload['uuid_payment'] ?? null,
                'resource_type' => $payload['resource_type'] ?? null,
                'resource_id' => $payload['resource_id'] ?? null,
                'source_type' => $payload['source_type'] ?? 'SIF_PANEL',
                'source_id' => $payload['source_id'] ?? ($actor['actor_id'] ?? null),
                'type' => $payload['type'] ?? null,
                'message' => $payload['message'] ?? null,
                'severity' => $payload['severity'] ?? 'MEDIUM',
                'correlation_id' => $correlationId,
                'idempotency_key' => $idempotencyKey,
                'reason_code' => $payload['reason_code'] ?? 'MANUAL_OPEN',
            ]);

            if (!$result['reused']) {
                $incident = $this->requireIncident($db, (int) $result['incident_id'], true);
                $this->actions->append($db, [
                    'incident_id' => (int) $incident['ID'],
                    'action_type' => 'OPEN',
                    'previous_status' => null,
                    'new_status' => 'OPEN',
                    'severity' => (string) $incident['SEVERITY'],
                    'assignee_id' => null,
                    'actor_id' => $actor['actor_id'] ?? null,
                    'actor_role' => $this->primaryRole($actor),
                    'reason_code' => $payload['reason_code'] ?? 'MANUAL_OPEN',
                    'details' => $payload['message'] ?? null,
                    'evidence' => $payload['evidence'] ?? null,
                    'correlation_id' => $correlationId,
                    'idempotency_key' => $this->actionKey('OPEN', $idempotencyKey),
                ]);
            }

            return $result;
        });
    }

    public function assign(array $actor, int $incidentId, array $payload): array
    {
        $this->assertManage($actor);
        $assigneeId = $this->requiredString($payload, 'assignee_id', 120);
        $idempotencyKey = $this->requiredString($payload, 'idempotency_key', 140);
        $reasonCode = strtoupper($this->requiredString($payload, 'reason_code', 80));
        $correlationId = $this->correlationId($actor, $payload);

        return $this->transactions->run(function (\PDO $db) use (
            $actor,
            $incidentId,
            $payload,
            $assigneeId,
            $idempotencyKey,
            $reasonCode,
            $correlationId
        ): array {
            $incident = $this->requireIncident($db, $incidentId, true);

            $severity = strtoupper(trim((string) ($payload['severity'] ?? $incident['SEVERITY'] ?? 'MEDIUM')));
            if (!in_array($severity, ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'], true)) {
                throw SifException::validation('Invalid incident severity');
            }

            $action = $this->actions->append($db, [
                'incident_id' => $incidentId,
                'action_type' => 'ASSIGN',
                'previous_status' => (string) $incident['ESTAT'],
                'new_status' => 'IN_PROGRESS',
                'severity' => $severity,
                'assignee_id' => $assigneeId,
                'actor_id' => $actor['actor_id'] ?? null,
                'actor_role' => $this->primaryRole($actor),
                'reason_code' => $reasonCode,
                'details' => $payload['details'] ?? null,
                'evidence' => $payload['evidence'] ?? null,
                'correlation_id' => $correlationId,
                'idempotency_key' => $idempotencyKey,
            ]);

            if (!$action['reused']) {
                $this->assertActive($incident);
                $this->incidents->updateLifecycle(
                    $db,
                    $incidentId,
                    'IN_PROGRESS',
                    $severity,
                    $assigneeId,
                    null,
                    null,
                    null
                );
            }

            return [
                'ok' => true,
                'reused' => $action['reused'],
                'incident_id' => $incidentId,
                'status' => 'IN_PROGRESS',
                'assignee_id' => $assigneeId,
                'uuid_action' => $action['uuid_action'],
            ];
        });
    }

    public function addEvidence(array $actor, int $incidentId, array $payload): array
    {
        $this->assertManage($actor);
        $idempotencyKey = $this->requiredString($payload, 'idempotency_key', 140);
        $reasonCode = strtoupper($this->requiredString($payload, 'reason_code', 80));
        $correlationId = $this->correlationId($actor, $payload);
        $evidence = $payload['evidence'] ?? null;
        if (!is_array($evidence) || $evidence === []) {
            throw SifException::validation('Incident evidence is required');
        }

        return $this->transactions->run(function (\PDO $db) use (
            $actor,
            $incidentId,
            $payload,
            $idempotencyKey,
            $reasonCode,
            $correlationId,
            $evidence
        ): array {
            $incident = $this->requireIncident($db, $incidentId, true);

            $action = $this->actions->append($db, [
                'incident_id' => $incidentId,
                'action_type' => 'ADD_EVIDENCE',
                'previous_status' => (string) $incident['ESTAT'],
                'new_status' => (string) $incident['ESTAT'],
                'severity' => (string) ($incident['SEVERITY'] ?? 'MEDIUM'),
                'assignee_id' => $incident['ASSIGNED_TO'] ?? null,
                'actor_id' => $actor['actor_id'] ?? null,
                'actor_role' => $this->primaryRole($actor),
                'reason_code' => $reasonCode,
                'details' => $payload['details'] ?? null,
                'evidence' => $evidence,
                'correlation_id' => $correlationId,
                'idempotency_key' => $idempotencyKey,
            ]);

            if (!$action['reused']) {
                $this->assertActive($incident);
            }

            return [
                'ok' => true,
                'reused' => $action['reused'],
                'incident_id' => $incidentId,
                'status' => (string) $incident['ESTAT'],
                'uuid_action' => $action['uuid_action'],
            ];
        });
    }

    public function resolve(array $actor, int $incidentId, array $payload): array
    {
        return $this->close($actor, $incidentId, $payload, 'RESOLVED', 'RESOLVE');
    }

    public function dismiss(array $actor, int $incidentId, array $payload): array
    {
        return $this->close($actor, $incidentId, $payload, 'DISMISSED', 'DISMISS');
    }

    public function reopen(array $actor, int $incidentId, array $payload): array
    {
        $this->assertManage($actor);
        $idempotencyKey = $this->requiredString($payload, 'idempotency_key', 140);
        $reasonCode = strtoupper($this->requiredString($payload, 'reason_code', 80));
        $correlationId = $this->correlationId($actor, $payload);

        return $this->transactions->run(function (\PDO $db) use (
            $actor,
            $incidentId,
            $payload,
            $idempotencyKey,
            $reasonCode,
            $correlationId
        ): array {
            $incident = $this->requireIncident($db, $incidentId, true);

            $action = $this->actions->append($db, [
                'incident_id' => $incidentId,
                'action_type' => 'REOPEN',
                'previous_status' => (string) $incident['ESTAT'],
                'new_status' => 'OPEN',
                'severity' => (string) ($incident['SEVERITY'] ?? 'MEDIUM'),
                'assignee_id' => null,
                'actor_id' => $actor['actor_id'] ?? null,
                'actor_role' => $this->primaryRole($actor),
                'reason_code' => $reasonCode,
                'details' => $payload['details'] ?? null,
                'evidence' => $payload['evidence'] ?? null,
                'correlation_id' => $correlationId,
                'idempotency_key' => $idempotencyKey,
            ]);

            if (!$action['reused']) {
                if (!in_array((string) $incident['ESTAT'], ['RESOLVED', 'DISMISSED'], true)) {
                    throw SifException::conflict('Only closed incidents can be reopened');
                }
                $this->incidents->updateLifecycle(
                    $db,
                    $incidentId,
                    'OPEN',
                    (string) ($incident['SEVERITY'] ?? 'MEDIUM'),
                    null,
                    null,
                    null,
                    null
                );
            }

            return [
                'ok' => true,
                'reused' => $action['reused'],
                'incident_id' => $incidentId,
                'status' => 'OPEN',
                'uuid_action' => $action['uuid_action'],
            ];
        });
    }

    private function close(
        array $actor,
        int $incidentId,
        array $payload,
        string $targetStatus,
        string $actionType
    ): array {
        $this->assertManage($actor);
        $idempotencyKey = $this->requiredString($payload, 'idempotency_key', 140);
        $reasonCode = strtoupper($this->requiredString($payload, 'reason_code', 80));
        $closureCriteria = $this->requiredString($payload, 'closure_criteria', 4000);
        $resolutionNotes = $this->requiredString($payload, 'resolution_notes', 8000);
        $correlationId = $this->correlationId($actor, $payload);
        $evidence = $payload['evidence'] ?? null;

        if ($targetStatus === 'RESOLVED' && (!is_array($evidence) || $evidence === [])) {
            throw SifException::validation('Resolution evidence is required');
        }

        return $this->transactions->run(function (\PDO $db) use (
            $actor,
            $incidentId,
            $payload,
            $targetStatus,
            $actionType,
            $idempotencyKey,
            $reasonCode,
            $closureCriteria,
            $resolutionNotes,
            $correlationId,
            $evidence
        ): array {
            $incident = $this->requireIncident($db, $incidentId, true);

            $action = $this->actions->append($db, [
                'incident_id' => $incidentId,
                'action_type' => $actionType,
                'previous_status' => (string) $incident['ESTAT'],
                'new_status' => $targetStatus,
                'severity' => (string) ($incident['SEVERITY'] ?? 'MEDIUM'),
                'assignee_id' => $incident['ASSIGNED_TO'] ?? null,
                'actor_id' => $actor['actor_id'] ?? null,
                'actor_role' => $this->primaryRole($actor),
                'reason_code' => $reasonCode,
                'details' => $resolutionNotes,
                'evidence' => $evidence,
                'correlation_id' => $correlationId,
                'idempotency_key' => $idempotencyKey,
            ]);

            if (!$action['reused']) {
                $this->assertActive($incident);
                $this->incidents->updateLifecycle(
                    $db,
                    $incidentId,
                    $targetStatus,
                    (string) ($incident['SEVERITY'] ?? 'MEDIUM'),
                    $incident['ASSIGNED_TO'] ?? null,
                    (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))->format('Y-m-d H:i:s.u'),
                    $resolutionNotes,
                    $closureCriteria
                );
            }

            return [
                'ok' => true,
                'reused' => $action['reused'],
                'incident_id' => $incidentId,
                'status' => $targetStatus,
                'uuid_action' => $action['uuid_action'],
            ];
        });
    }

    private function requireIncident(\PDO $db, int $incidentId, bool $forUpdate): array
    {
        $incident = $this->incidents->findById($db, $incidentId, $forUpdate);
        if ($incident === null) {
            throw SifException::notFound('Incident not found');
        }

        return $incident;
    }

    private function assertActive(array $incident): void
    {
        if (in_array((string) ($incident['ESTAT'] ?? ''), ['RESOLVED', 'DISMISSED'], true)) {
            throw SifException::conflict('Incident is already closed');
        }
    }

    private function assertRead(array $actor): void
    {
        if (!$this->hasAnyRole($actor, array_values(array_unique(array_merge($this->readRoles, $this->manageRoles))))) {
            throw SifException::forbidden('Incident read permission denied');
        }
    }

    private function assertManage(array $actor): void
    {
        if (!$this->hasAnyRole($actor, $this->manageRoles)) {
            throw SifException::forbidden('Incident management permission denied');
        }
    }

    private function hasAnyRole(array $actor, array $allowed): bool
    {
        if ($allowed === []) {
            return false;
        }

        $roles = $this->normalizeRoles(is_array($actor['roles'] ?? null) ? $actor['roles'] : []);
        return array_intersect($roles, $allowed) !== [];
    }

    private function normalizeRoles(array $roles): array
    {
        $normalized = [];
        foreach ($roles as $role) {
            $value = strtoupper(trim((string) $role));
            if ($value !== '') {
                $normalized[$value] = true;
            }
        }

        return array_keys($normalized);
    }

    private function primaryRole(array $actor): ?string
    {
        $roles = $this->normalizeRoles(is_array($actor['roles'] ?? null) ? $actor['roles'] : []);
        return $roles[0] ?? null;
    }

    private function correlationId(array $actor, array $payload): string
    {
        $value = trim((string) ($payload['correlation_id'] ?? $actor['request_id'] ?? ''));
        if ($value === '' || strlen($value) > 120) {
            throw SifException::validation('Incident correlation_id is required');
        }

        return $value;
    }

    private function requiredString(array $payload, string $field, int $maxLength): string
    {
        $value = trim((string) ($payload[$field] ?? ''));
        if ($value === '' || strlen($value) > $maxLength) {
            throw SifException::validation('Invalid incident field: ' . $field);
        }

        return $value;
    }

    private function actionKey(string $action, string $idempotencyKey): string
    {
        return 'INCIDENT|' . strtoupper($action) . '|' . hash('sha256', $idempotencyKey);
    }
}
