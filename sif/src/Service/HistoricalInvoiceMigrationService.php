<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\HistoricalInvoiceMigrationRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Repository\SifAuditEventRepository;

final class HistoricalInvoiceMigrationService
{
    private HistoricalInvoiceMigrationPreflight $preflight;
    private OperationalEventRepository $operationalEvents;
    private SifAuditEventRepository $auditEvents;

    public function __construct(
        private TransactionRunner $transactions,
        private HistoricalInvoicePayloadBuilder $payloadBuilder,
        private HistoricalInvoiceMigrationRepository $historicalInvoices,
        ?HistoricalInvoiceMigrationPreflight $preflight = null,
        ?OperationalEventRepository $operationalEvents = null,
        ?SifAuditEventRepository $auditEvents = null
    ) {
        $this->preflight = $preflight ?? new HistoricalInvoiceMigrationPreflight();
        $this->operationalEvents = $operationalEvents ?? new OperationalEventRepository(new UuidGenerator());
        $this->auditEvents = $auditEvents ?? new SifAuditEventRepository(new UuidGenerator());
    }

    public function importHistoricalInvoice(array $input): array
    {
        $payload = $this->payloadBuilder->build($input);

        return $this->transactions->run(function (\PDO $db) use ($payload): array {
            $this->preflight->assertSafe($db, $payload);
            $result = $this->historicalInvoices->importHistoricalInvoice($db, $payload);

            return $this->appendAudit($db, $payload, $result);
        });
    }

    private function appendAudit(\PDO $db, array $payload, array $result): array
    {
        $reused = (bool) ($result['idempotency_reused'] ?? false);
        $requestId = $this->contextId($payload['request_id'] ?? null, (string) $payload['idempotency_key']);
        $correlationId = $this->contextId($payload['correlation_id'] ?? null, $requestId);
        $actorType = strtoupper(trim((string) ($payload['actor_type'] ?? 'PROCESS')));
        if (!in_array($actorType, ['HUMAN', 'SYSTEM', 'PROCESS'], true)) {
            $actorType = 'PROCESS';
        }

        $occurredAt = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))
            ->format('Y-m-d H:i:s.u');
        $firstRelation = $payload['relations'][0] ?? [];
        $sourceId = $firstRelation['source_id'] ?? null;
        $reasonCode = $reused
            ? 'HISTORICAL_INVOICE_REUSED'
            : 'HISTORICAL_INVOICE_IMPORTED';

        $afterSnapshot = [
            'uuid_factura' => (string) $result['uuid_factura'],
            'num_visible' => (string) $result['num_visible'],
            'invoice_status' => 'HISTORICAL',
            'aeat_status' => 'NO_VERIFACTU',
            'idempotency_reused' => $reused,
        ];

        $this->operationalEvents->append($db, [
            'operation_type' => 'HISTORICAL_INVOICE_IMPORT',
            'source_type' => (string) ($payload['source_type'] ?? 'HISTORIC_WEB_FACTURES'),
            'source_id' => $sourceId === null ? null : (string) $sourceId,
            'uuid_factura' => (string) $result['uuid_factura'],
            'uuid_payment' => null,
            'fiscal_impact' => 'HISTORICAL_NO_VERIFACTU',
            'economic_impact' => 'NONE',
            'status' => 'COMPLETED',
            'reason_code' => $reasonCode,
            'before_snapshot' => null,
            'after_snapshot' => $afterSnapshot,
            'actor_type' => $actorType,
            'actor_id' => $this->nullableContextString($payload['created_by'] ?? null, 120),
            'actor_role' => $this->nullableContextString($payload['actor_role'] ?? null, 80),
            'source_channel' => 'MIGRACIO',
            'correlation_id' => $correlationId,
            'occurred_at' => $occurredAt,
        ]);

        $afterJson = json_encode(
            $afterSnapshot,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $this->auditEvents->append($db, [
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'action' => 'IMPORT_HISTORICAL_INVOICE',
            'result' => $reused ? 'REUSED' : 'SUCCEEDED',
            'resource_type' => 'FACTURA',
            'resource_id' => (string) $result['uuid_factura'],
            'source_environment' => $this->sourceEnvironment(),
            'source_channel' => 'MIGRACIO',
            'actor_type' => $actorType,
            'actor_id' => $this->nullableContextString($payload['created_by'] ?? null, 120),
            'actor_role' => $this->nullableContextString($payload['actor_role'] ?? null, 80),
            'reason_code' => $reasonCode,
            'before_hash' => null,
            'after_hash' => hash('sha256', $afterJson),
            'changeset' => [
                'idempotency_key' => (string) $payload['idempotency_key'],
                'num_visible' => (string) $payload['num_visible'],
                'idempotency_reused' => $reused,
                'aeat_status' => 'NO_VERIFACTU',
            ],
            'occurred_at' => $occurredAt,
        ]);

        $result['correlation_id'] = $correlationId;

        return $result;
    }

    private function contextId(mixed $value, string $fallback): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            $value = $fallback;
        }

        return mb_substr($value, 0, 120, 'UTF-8');
    }

    private function nullableContextString(mixed $value, int $maxLength): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    private function sourceEnvironment(): string
    {
        return match (strtoupper(trim((string) (getenv('SIF_ENV') ?: 'DEVELOPMENT')))) {
            'PROD', 'PRODUCTION' => 'PRODUCTION',
            'PREPROD', 'PREPRODUCTION' => 'PREPRODUCTION',
            'TEST', 'TESTING' => 'TEST',
            'MIGRATION' => 'MIGRATION',
            default => 'DEVELOPMENT',
        };
    }
}
