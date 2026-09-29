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
        return $this->openDetailed($db, [
            'uuid_factura' => $uuidFactura,
            'resource_type' => $uuidFactura !== null ? 'INVOICE' : null,
            'resource_id' => $uuidFactura,
            'source_type' => 'LEGACY_OPEN',
            'type' => $type,
            'message' => $message,
            'severity' => 'MEDIUM',
        ]);
    }

    public function openDetailed(\PDO $db, array $input): array
    {
        $type = strtoupper(trim((string) ($input['type'] ?? '')));
        $message = trim((string) ($input['message'] ?? ''));
        $severity = strtoupper(trim((string) ($input['severity'] ?? 'MEDIUM')));
        $uuidFactura = $this->nullableString($input, 'uuid_factura', 36);
        $uuidPayment = $this->nullableString($input, 'uuid_payment', 36);
        $resourceType = $this->nullableUpperString($input, 'resource_type', 40);
        $resourceId = $this->nullableString($input, 'resource_id', 120);
        $sourceType = $this->nullableUpperString($input, 'source_type', 40);
        $sourceId = $this->nullableString($input, 'source_id', 120);
        $correlationId = $this->nullableString($input, 'correlation_id', 120);
        $idempotencyKey = $this->nullableString($input, 'idempotency_key', 140);
        $reasonCode = $this->nullableUpperString($input, 'reason_code', 80);

        $idempotencyPayload = [
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
        $idempotencyValidator = new PayloadIdempotencyValidator();
        $idempotencyPayloadHash = $idempotencyKey !== null
            ? $idempotencyValidator->calculateHash($idempotencyPayload)
            : null;

        if ($type === '' || strlen($type) > 50) {
            throw SifException::validation('Invalid incident type');
        }
        if ($message === '') {
            throw SifException::validation('Invalid incident message');
        }
        if (!in_array($severity, ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'], true)) {
            throw SifException::validation('Invalid incident severity');
        }
        if (($resourceType === null) !== ($resourceId === null)) {
            throw SifException::validation('Incident resource_type and resource_id must be provided together');
        }

        if ($uuidFactura !== null) {
            $this->assertUuid($uuidFactura, 'invoice');
            $stmt = $db->prepare('SELECT 1 FROM factura WHERE UUID_FACTURA = ? LIMIT 1');
            $stmt->execute([$uuidFactura]);
            if ($stmt->fetchColumn() === false) {
                throw SifException::notFound('Incident invoice not found');
            }
        }

        if ($uuidPayment !== null) {
            $this->assertUuid($uuidPayment, 'payment');
            $stmt = $db->prepare('SELECT 1 FROM payment_transaction WHERE UUID_PAYMENT = ? LIMIT 1');
            $stmt->execute([$uuidPayment]);
            if ($stmt->fetchColumn() === false) {
                throw SifException::notFound('Incident payment not found');
            }
        }

        if ($idempotencyKey !== null) {
            $existing = $this->findByIdempotencyKey($db, $idempotencyKey);
            if ($existing !== null) {
                $idempotencyValidator->assertMatches(
                    $idempotencyPayload,
                    (string) ($existing['IDEMPOTENCY_PAYLOAD_HASH'] ?? '')
                );

                return [
                    'ok' => true,
                    'reused' => true,
                    'incident_id' => (int) $existing['ID'],
                    'uuid_incident' => (string) $existing['UUID_INCIDENT'],
                    'status' => (string) $existing['ESTAT'],
                ];
            }
        }

        $uuidIncident = $this->uuidGenerator->generate();

        try {
            $db->prepare(
                'INSERT INTO errors_verifactu (
                    UUID_INCIDENT, UUID_FACTURA, UUID_PAYMENT, RESOURCE_TYPE, RESOURCE_ID,
                    SOURCE_TYPE, SOURCE_ID, TIPUS_INCIDENCIA, SEVERITY, ASSIGNED_TO,
                    CORRELATION_ID, IDEMPOTENCY_KEY, IDEMPOTENCY_PAYLOAD_HASH,
                    REASON_CODE, ESTAT, DETAILS
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, \'OPEN\', ?)'
            )->execute([
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
                $idempotencyPayloadHash,
                $reasonCode,
                $message,
            ]);
        } catch (\PDOException $exception) {
            if ($idempotencyKey !== null && (string) $exception->getCode() === '23000') {
                $existing = $this->findByIdempotencyKey($db, $idempotencyKey);
                if ($existing !== null) {
                    $this->assertEquivalentReuse($existing, [
                        'UUID_FACTURA' => $uuidFactura,
                        'UUID_PAYMENT' => $uuidPayment,
                        'RESOURCE_TYPE' => $resourceType,
                        'RESOURCE_ID' => $resourceId,
                        'TIPUS_INCIDENCIA' => $type,
                    ]);

                    return [
                        'ok' => true,
                        'reused' => true,
                        'incident_id' => (int) $existing['ID'],
                        'uuid_incident' => (string) $existing['UUID_INCIDENT'],
                        'status' => (string) $existing['ESTAT'],
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
            'status' => 'OPEN',
        ];
    }

    public function findById(\PDO $db, int $incidentId, bool $forUpdate = false): ?array
    {
        if ($incidentId < 1) {
            throw SifException::validation('Invalid incident id');
        }

        $sql = 'SELECT * FROM errors_verifactu WHERE ID = ?';
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
        $limit = max(1, min(100, $limit));
        $where = [];
        $params = [];

        foreach ([
            'status' => ['ESTAT', 30, true],
            'severity' => ['SEVERITY', 20, true],
            'type' => ['TIPUS_INCIDENCIA', 50, true],
            'assigned_to' => ['ASSIGNED_TO', 120, false],
        ] as $field => [$column, $maxLength, $upper]) {
            if (!array_key_exists($field, $filters) || $filters[$field] === null || $filters[$field] === '') {
                continue;
            }
            $value = trim((string) $filters[$field]);
            if ($value === '' || strlen($value) > $maxLength) {
                throw SifException::validation('Invalid incident filter: ' . $field);
            }
            if ($upper) {
                $value = strtoupper($value);
            }
            $where[] = $column . ' = ?';
            $params[] = $value;
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

    public function updateLifecycle(
        \PDO $db,
        int $incidentId,
        string $status,
        string $severity,
        ?string $assignedTo,
        ?string $resolvedAt,
        ?string $resolutionNotes,
        ?string $closureCriteria
    ): void {
        $db->prepare(
            'UPDATE errors_verifactu
             SET ESTAT = ?, SEVERITY = ?, ASSIGNED_TO = ?, RESOLVED_AT = ?,
                 RESOLUTION_NOTES = ?, CLOSURE_CRITERIA = ?
             WHERE ID = ?'
        )->execute([
            strtoupper(trim($status)),
            strtoupper(trim($severity)),
            $assignedTo,
            $resolvedAt,
            $resolutionNotes,
            $closureCriteria,
            $incidentId,
        ]);
    }

    private function findByIdempotencyKey(\PDO $db, string $key): ?array
    {
        $stmt = $db->prepare('SELECT * FROM errors_verifactu WHERE IDEMPOTENCY_KEY = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function nullableString(array $input, string $field, int $maxLength): ?string
    {
        $value = $input[$field] ?? null;
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '' || strlen($value) > $maxLength) {
            throw SifException::validation('Invalid incident field: ' . $field);
        }

        return $value;
    }

    private function nullableUpperString(array $input, string $field, int $maxLength): ?string
    {
        $value = $this->nullableString($input, $field, $maxLength);
        return $value === null ? null : strtoupper($value);
    }

    private function assertUuid(string $value, string $label): void
    {
        if (preg_match('/^[0-9a-fA-F-]{36}$/D', $value) !== 1) {
            throw SifException::validation('Invalid incident ' . $label . ' UUID');
        }
    }
}
