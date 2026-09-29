<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\PayloadIdempotencyValidator;

final class IncidentRepository
{
    public function __construct(private ?UuidGenerator $uuidGenerator = null)
    {
        $this->uuidGenerator ??= new UuidGenerator();
    }

    public function open(\PDO $db, ?string $uuidFactura, string $type, string $message): array
    {
        $uuid = $this->uuidGenerator->generate();

        $result = $this->openDetailed($db, [
            'uuid_factura' => $uuidFactura,
            'resource_type' => $uuidFactura === null ? 'SYSTEM' : 'INVOICE',
            'resource_id' => $uuidFactura,
            'source_type' => 'SIF',
            'source_id' => null,
            'type' => $type,
            'message' => $message,
            'severity' => 'MEDIUM',
            'correlation_id' => 'INCIDENT:' . $uuid,
            'idempotency_key' => 'INCIDENT|' . $uuid,
            'reason_code' => strtoupper(trim($type)),
        ]);

        return [
            'ok' => true,
            'incident_id' => $result['incident_id'],
            'uuid_incident' => $result['uuid_incident'],
        ];
    }

    public function openDetailed(\PDO $db, array $payload): array
    {
        $uuidFactura = $this->nullable($payload, 'uuid_factura', 36);
        $uuidPayment = $this->nullable($payload, 'uuid_payment', 36);
        $resourceType = $this->nullableUpper($payload, 'resource_type', 40);
        $resourceId = $this->nullable($payload, 'resource_id', 120);
        $sourceType = $this->nullableUpper($payload, 'source_type', 40);
        $sourceId = $this->nullable($payload, 'source_id', 120);
        $type = strtoupper(trim((string) ($payload['type'] ?? '')));
        $message = trim((string) ($payload['message'] ?? ''));
        $severity = strtoupper(trim((string) ($payload['severity'] ?? 'MEDIUM')));
        $correlationId = $this->nullable($payload, 'correlation_id', 120);
        $idempotencyKey = $this->nullable($payload, 'idempotency_key', 140);
        $reasonCode = $this->nullableUpper($payload, 'reason_code', 80);

        if ($type === '' || strlen($type) > 50) {
            throw SifException::validation('Invalid incident type');
        }
        if ($message === '') {
            throw SifException::validation('Invalid incident message');
        }
        if (!in_array($severity, ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'], true)) {
            throw SifException::validation('Invalid incident severity');
        }

        if ($uuidFactura !== null) {
            $stmt = $db->prepare('SELECT 1 FROM factura WHERE UUID_FACTURA = ? LIMIT 1');
            $stmt->execute([$uuidFactura]);
            if ($stmt->fetchColumn() === false) {
                throw SifException::notFound('Incident invoice not found');
            }
        }

        if ($uuidPayment !== null) {
            $stmt = $db->prepare('SELECT 1 FROM payment_transaction WHERE UUID_PAYMENT = ? LIMIT 1');
            $stmt->execute([$uuidPayment]);
            if ($stmt->fetchColumn() === false) {
                throw SifException::notFound('Incident payment not found');
            }
        }

        $normalizedPayload = [
            'uuid_factura' => $uuidFactura,
            'uuid_payment' => $uuidPayment,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'type' => $type,
            'message' => $message,
            'severity' => $severity,
            'reason_code' => $reasonCode,
        ];

        $validator = new PayloadIdempotencyValidator();
        $payloadHash = $validator->calculateHash($normalizedPayload);

        if ($idempotencyKey !== null) {
            $existing = $this->findByIdempotencyKey($db, $idempotencyKey);
            if ($existing !== null) {
                $validator->assertMatches(
                    $normalizedPayload,
                    (string) ($existing['IDEMPOTENCY_PAYLOAD_HASH'] ?? '')
                );

                return [
                    'ok' => true,
                    'reused' => true,
                    'incident_id' => (int) $existing['ID'],
                    'uuid_incident' => (string) $existing['UUID_INCIDENT'],
                ];
            }
        }

        $uuidIncident = $this->uuidGenerator->generate();
        $correlationId ??= 'INCIDENT:' . $uuidIncident;
        $reasonCode ??= $type;

        try {
            $stmt = $db->prepare(
                'INSERT INTO errors_verifactu (
                    UUID_INCIDENT, UUID_FACTURA, UUID_PAYMENT, RESOURCE_TYPE, RESOURCE_ID,
                    SOURCE_TYPE, SOURCE_ID, TIPUS_INCIDENCIA, SEVERITY, ASSIGNED_TO,
                    CORRELATION_ID, IDEMPOTENCY_KEY, IDEMPOTENCY_PAYLOAD_HASH, REASON_CODE,
                    ESTAT, DETAILS
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, \'OPEN\', ?)'
            );
            $stmt->execute([
                $uuidIncident,
                $uuidFactura,
                $uuidPayment,
                $resourceType,
                $resourceId,
                $sourceType,
                $sourceId,
                $type,
                $severity,
                $correlationId,
                $idempotencyKey,
                $idempotencyKey === null ? null : $payloadHash,
                $reasonCode,
                $message,
            ]);
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() === '23000' && $idempotencyKey !== null) {
                $existing = $this->findByIdempotencyKey($db, $idempotencyKey);
                if ($existing !== null) {
                    $validator->assertMatches(
                        $normalizedPayload,
                        (string) ($existing['IDEMPOTENCY_PAYLOAD_HASH'] ?? '')
                    );

                    return [
                        'ok' => true,
                        'reused' => true,
                        'incident_id' => (int) $existing['ID'],
                        'uuid_incident' => (string) $existing['UUID_INCIDENT'],
                    ];
                }
            }
            throw $exception;
        }

        return [
            'ok' => true,
            'reused' => false,
            'incident_id' => (int) $db->lastInsertId(),
            'uuid_incident' => $uuidIncident,
        ];
    }

    public function findById(\PDO $db, int $incidentId, bool $forUpdate = false): ?array
    {
        if ($incidentId < 1) {
            throw SifException::validation('Invalid incident id');
        }

        $sql = 'SELECT * FROM errors_verifactu WHERE ID = ? LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([$incidentId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function list(\PDO $db, array $filters = [], int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $where = [];
        $params = [];

        $map = [
            'status' => ['ESTAT', 30, true],
            'severity' => ['SEVERITY', 20, true],
            'type' => ['TIPUS_INCIDENCIA', 50, true],
            'assignee_id' => ['ASSIGNED_TO', 120, false],
            'uuid_factura' => ['UUID_FACTURA', 36, false],
            'uuid_payment' => ['UUID_PAYMENT', 36, false],
            'resource_type' => ['RESOURCE_TYPE', 40, true],
            'resource_id' => ['RESOURCE_ID', 120, false],
        ];

        foreach ($map as $key => [$column, $maxLength, $upper]) {
            if (!array_key_exists($key, $filters) || $filters[$key] === null || trim((string) $filters[$key]) === '') {
                continue;
            }
            $value = trim((string) $filters[$key]);
            if (strlen($value) > $maxLength) {
                throw SifException::validation('Invalid incident filter: ' . $key);
            }
            $where[] = $column . ' = ?';
            $params[] = $upper ? strtoupper($value) : $value;
        }

        $sql = 'SELECT * FROM errors_verifactu';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY CREATED_AT DESC, ID DESC LIMIT ' . $limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function summary(\PDO $db): array
    {
        $rows = $db->query(
            'SELECT ESTAT, SEVERITY, COUNT(*) AS TOTAL
             FROM errors_verifactu
             GROUP BY ESTAT, SEVERITY'
        )->fetchAll(\PDO::FETCH_ASSOC);

        $byStatus = [];
        $bySeverity = [];
        $openTotal = 0;
        $criticalOpen = 0;

        foreach ($rows as $row) {
            $status = strtoupper((string) ($row['ESTAT'] ?? ''));
            $severity = strtoupper((string) ($row['SEVERITY'] ?? ''));
            $total = (int) ($row['TOTAL'] ?? 0);

            $byStatus[$status] = ($byStatus[$status] ?? 0) + $total;
            $bySeverity[$severity] = ($bySeverity[$severity] ?? 0) + $total;

            if (in_array($status, ['OPEN', 'IN_PROGRESS'], true)) {
                $openTotal += $total;
                if ($severity === 'CRITICAL') {
                    $criticalOpen += $total;
                }
            }
        }

        $lastUpdated = $db->query('SELECT MAX(UPDATED_AT) FROM errors_verifactu')->fetchColumn();

        return [
            'total' => array_sum($byStatus),
            'open_total' => $openTotal,
            'critical_open' => $criticalOpen,
            'by_status' => $byStatus,
            'by_severity' => $bySeverity,
            'last_updated_at' => $lastUpdated === false ? null : $lastUpdated,
        ];
    }

    public function updateLifecycle(
        \PDO $db,
        int $incidentId,
        string $status,
        string $severity,
        ?string $assigneeId,
        ?string $resolvedAt,
        ?string $resolutionNotes,
        ?string $closureCriteria
    ): void {
        if ($incidentId < 1) {
            throw SifException::validation('Invalid incident id');
        }

        $status = strtoupper(trim($status));
        $severity = strtoupper(trim($severity));
        if (!in_array($status, ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'DISMISSED'], true)) {
            throw SifException::validation('Invalid incident status');
        }
        if (!in_array($severity, ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'], true)) {
            throw SifException::validation('Invalid incident severity');
        }
        if ($assigneeId !== null && (trim($assigneeId) === '' || strlen($assigneeId) > 120)) {
            throw SifException::validation('Invalid incident assignee');
        }

        $stmt = $db->prepare(
            'UPDATE errors_verifactu
             SET ESTAT = ?, SEVERITY = ?, ASSIGNED_TO = ?, RESOLVED_AT = ?,
                 RESOLUTION_NOTES = ?, CLOSURE_CRITERIA = ?
             WHERE ID = ?'
        );
        $stmt->execute([
            $status,
            $severity,
            $assigneeId === null ? null : trim($assigneeId),
            $resolvedAt,
            $resolutionNotes,
            $closureCriteria,
            $incidentId,
        ]);
        if ($stmt->rowCount() !== 1) {
            $exists = $this->findById($db, $incidentId);
            if ($exists === null) {
                throw SifException::notFound('Incident not found');
            }
        }
    }

    public function resolveAeatQueueReview(\PDO $db, string $uuidFactura, int $queueId): int
    {
        $prefix = 'Queue ID ' . $queueId . ':%';
        $stmt = $db->prepare(
            "UPDATE errors_verifactu
             SET ESTAT = 'RESOLVED', RESOLVED_AT = COALESCE(RESOLVED_AT, NOW(6)),
                 RESOLUTION_NOTES = COALESCE(RESOLUTION_NOTES, 'AEAT queue reconciled without resend')
             WHERE UUID_FACTURA = ?
               AND ESTAT IN ('OPEN', 'IN_PROGRESS')
               AND TIPUS_INCIDENCIA LIKE 'AEAT_%'
               AND DETAILS LIKE ?"
        );
        $stmt->execute([$uuidFactura, $prefix]);

        return $stmt->rowCount();
    }

    private function findByIdempotencyKey(\PDO $db, string $key): ?array
    {
        $stmt = $db->prepare('SELECT * FROM errors_verifactu WHERE IDEMPOTENCY_KEY = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function nullable(array $input, string $field, int $maxLength): ?string
    {
        if (!array_key_exists($field, $input) || $input[$field] === null) {
            return null;
        }

        $value = trim((string) $input[$field]);
        if ($value === '' || strlen($value) > $maxLength) {
            throw SifException::validation('Invalid incident field: ' . $field);
        }

        return $value;
    }

    private function nullableUpper(array $input, string $field, int $maxLength): ?string
    {
        $value = $this->nullable($input, $field, $maxLength);
        return $value === null ? null : strtoupper($value);
    }
}
