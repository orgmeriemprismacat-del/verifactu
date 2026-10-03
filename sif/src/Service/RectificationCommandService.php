<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Repository\SifAuditEventRepository;

final class RectificationCommandService
{
    public function __construct(
        private \PDO $db,
        private ManualPaymentInvoiceRepository $invoices,
        private ManualRectificationPayloadBuilder $builder,
        private ManualRectificationService $issuer,
        private FiscalCorrectionDecisionGuard $decisionGuard,
        private PayloadIdempotencyValidator $fingerprints,
        private SifAuditEventRepository $auditEvents,
        private OperationalEventRepository $operationalEvents,
        private string $sourceEnvironment
    ) {
        $this->sourceEnvironment = strtoupper(trim($this->sourceEnvironment));
        if ($this->sourceEnvironment === '') {
            throw new \RuntimeException('Rectification source environment is not configured');
        }
    }

    public function preview(
        array $actor,
        string $uuidFactura,
        array $input,
        array $classification,
        array $context = []
    ): array {
        $resolved = $this->assertActor($actor, 'preview');
        $prepared = $this->prepare($uuidFactura, $input, $classification, $resolved['actor_id']);
        $audit = $this->auditBase($resolved, $context, $prepared['classification']);

        $this->auditEvents->append($this->db, array_merge($audit, [
            'action' => 'RECTIFICATION_PREVIEW',
            'result' => 'SUCCEEDED',
            'resource_type' => 'FACTURA',
            'resource_id' => $prepared['invoice']['UUID_FACTURA'],
            'before_snapshot' => $this->invoiceSnapshot($prepared['invoice']),
            'changeset' => [
                'classification' => $prepared['classification'],
                'fingerprint' => $prepared['fingerprint'],
            ],
        ]));

        return [
            'ok' => true,
            'action' => 'preview',
            'uuid_factura_rectificada' => $prepared['invoice']['UUID_FACTURA'],
            'num_visible_rectificada' => $prepared['invoice']['NUM_VISIBLE'],
            'classification' => $prepared['classification'],
            'fingerprint' => $prepared['fingerprint'],
            'billing' => $prepared['payload']['billing'],
            'totals' => $prepared['payload']['totals'],
            'lines' => $prepared['payload']['lines'],
        ];
    }

    public function confirm(
        array $actor,
        string $uuidFactura,
        array $input,
        array $classification,
        string $expectedFingerprint,
        array $context = []
    ): array {
        $resolved = $this->assertActor($actor, 'issue');
        $expectedFingerprint = strtolower(trim($expectedFingerprint));
        if (preg_match('/^[a-f0-9]{64}$/D', $expectedFingerprint) !== 1) {
            throw SifException::validation('Invalid rectification expected fingerprint');
        }

        $prepared = $this->prepare($uuidFactura, $input, $classification, $resolved['actor_id']);
        if (!hash_equals($expectedFingerprint, $prepared['fingerprint'])) {
            throw SifException::conflict(
                'Rectification preview changed before confirmation; create a new preview'
            );
        }

        $audit = $this->auditBase($resolved, $context, $prepared['classification']);
        $this->auditEvents->append($this->db, array_merge($audit, [
            'action' => 'RECTIFICATION_CONFIRM',
            'result' => 'REQUESTED',
            'resource_type' => 'FACTURA',
            'resource_id' => $prepared['invoice']['UUID_FACTURA'],
            'before_snapshot' => $this->invoiceSnapshot($prepared['invoice']),
            'changeset' => [
                'classification' => $prepared['classification'],
                'fingerprint' => $prepared['fingerprint'],
            ],
        ]));

        $terminal = [];

        try {
            $result = $this->issuer->issueByUuid(
                $this->db,
                $prepared['invoice']['UUID_FACTURA'],
                $prepared['input'],
                function (
                    \PDO $db,
                    array $issued,
                    array $lockedOriginal,
                    array $normalizedInput
                ) use (
                    $expectedFingerprint,
                    $prepared,
                    $audit,
                    $resolved,
                    &$terminal
                ): void {
                    $lockedPayload = $this->builder->forOriginalInvoice($lockedOriginal, $normalizedInput);
                    $lockedFingerprint = $this->fingerprint(
                        $lockedOriginal,
                        $lockedPayload,
                        $prepared['classification']
                    );

                    if (!hash_equals($expectedFingerprint, $lockedFingerprint)) {
                        throw SifException::conflict(
                            'Rectification source changed while confirmation was being committed'
                        );
                    }

                    $after = [
                        'uuid_factura_rectificativa' => $issued['uuid_factura'],
                        'num_visible_rectificativa' => $issued['num_visible'],
                        'uuid_factura_rectificada' => $lockedOriginal['UUID_FACTURA'],
                        'mode' => $prepared['classification']['rectification_mode'],
                        'total' => $lockedPayload['totals']['total'],
                    ];

                    $terminal['uuid_operational_event'] = $this->operationalEvents->append($db, [
                        'operation_type' => 'RECTIFICATION',
                        'source_type' => 'FACTURA',
                        'source_id' => $lockedOriginal['ID'] ?? null,
                        'uuid_factura' => $issued['uuid_factura'],
                        'fiscal_impact' => 'RECTIFICATION',
                        'economic_impact' => 'ADJUSTMENT',
                        'status' => ($issued['idempotency_reused'] ?? false) ? 'REUSED' : 'COMMITTED',
                        'reason_code' => $prepared['classification']['reason_code'],
                        'before_snapshot' => $this->invoiceSnapshot($lockedOriginal),
                        'after_snapshot' => $after,
                        'actor_type' => 'INTERNAL_USER',
                        'actor_id' => $resolved['actor_id'],
                        'actor_role' => $resolved['rectification_role'],
                        'source_channel' => $resolved['source_channel'],
                        'correlation_id' => $audit['correlation_id'],
                        'occurred_at' => $this->now(),
                    ]);

                    $terminal['uuid_audit_event'] = $this->auditEvents->append($db, array_merge($audit, [
                        'action' => 'RECTIFICATION_CONFIRM',
                        'result' => ($issued['idempotency_reused'] ?? false) ? 'REUSED' : 'SUCCEEDED',
                        'resource_type' => 'FACTURA',
                        'resource_id' => $issued['uuid_factura'],
                        'before_snapshot' => $this->invoiceSnapshot($lockedOriginal),
                        'after_snapshot' => $after,
                        'changeset' => [
                            'classification' => $prepared['classification'],
                            'fingerprint' => $lockedFingerprint,
                            'idempotency_reused' => (bool) ($issued['idempotency_reused'] ?? false),
                        ],
                    ]));
                }
            );
        } catch (\Throwable $exception) {
            $this->appendFailureAudit($audit, $prepared, $exception);
            throw $exception;
        }

        return array_merge($result, [
            'action' => 'confirm',
            'classification' => $prepared['classification'],
            'fingerprint' => $prepared['fingerprint'],
            'fingerprint_verified' => true,
            'audit' => $terminal,
        ]);
    }

    private function prepare(
        string $uuidFactura,
        array $input,
        array $classification,
        string $actorId
    ): array {
        $uuidFactura = trim($uuidFactura);
        if ($uuidFactura === '') {
            throw SifException::validation('Missing SIF invoice UUID');
        }

        $invoice = $this->invoices->findByUuid($this->db, $uuidFactura);
        if ($invoice === null) {
            throw SifException::validation('SIF invoice not found for rectification command');
        }

        $input['created_by'] = $actorId;
        $decision = $this->decisionGuard->assertRectification($classification, $input);
        $payload = $this->builder->forOriginalInvoice($invoice, $input);

        return [
            'invoice' => $invoice,
            'input' => $input,
            'classification' => $decision,
            'payload' => $payload,
            'fingerprint' => $this->fingerprint($invoice, $payload, $decision),
        ];
    }

    private function fingerprint(array $invoice, array $payload, array $classification): string
    {
        return $this->fingerprints->calculateHash([
            'original' => $this->fingerprintSnapshot($invoice),
            'payload' => $payload,
            'classification' => $classification,
        ]);
    }

    private function assertActor(array $actor, string $capability): array
    {
        $actorId = trim((string) ($actor['actor_id'] ?? ''));
        $scope = $actor['rectification_scope'] ?? null;
        $role = trim((string) ($actor['rectification_role'] ?? ''));
        $sourceChannel = strtoupper(trim((string) ($actor['source_channel'] ?? 'INTERNAL_API')));

        if (
            $actorId === ''
            || $role === ''
            || !is_array($scope)
            || ($scope[$capability] ?? false) !== true
        ) {
            throw SifException::forbidden('Resolved rectification scope is required');
        }

        return [
            'actor_id' => $actorId,
            'rectification_role' => $role,
            'source_channel' => $sourceChannel,
            'request_id' => trim((string) ($actor['request_id'] ?? '')),
        ];
    }

    private function auditBase(array $actor, array $context, array $classification): array
    {
        $requestId = $actor['request_id'];
        if ($requestId === '') {
            throw SifException::validation('Rectification request_id is required for audit');
        }

        $correlationId = trim((string) ($context['correlation_id'] ?? $requestId));
        if ($correlationId === '' || mb_strlen($correlationId, 'UTF-8') > 120) {
            throw SifException::validation('Invalid rectification correlation_id');
        }

        return [
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'causation_id' => $context['causation_id'] ?? null,
            'source_environment' => $this->sourceEnvironment,
            'source_channel' => $actor['source_channel'],
            'actor_type' => 'INTERNAL_USER',
            'actor_id' => $actor['actor_id'],
            'actor_role' => $actor['rectification_role'],
            'reason_code' => $classification['reason_code'],
        ];
    }

    private function appendFailureAudit(array $audit, array $prepared, \Throwable $exception): void
    {
        try {
            $this->auditEvents->append($this->db, array_merge($audit, [
                'action' => 'RECTIFICATION_CONFIRM',
                'result' => 'FAILED',
                'resource_type' => 'FACTURA',
                'resource_id' => $prepared['invoice']['UUID_FACTURA'],
                'before_snapshot' => $this->invoiceSnapshot($prepared['invoice']),
                'changeset' => [
                    'classification' => $prepared['classification'],
                    'fingerprint' => $prepared['fingerprint'],
                ],
                'error_code' => substr(
                    strtoupper(str_replace('\\', '_', $exception::class)),
                    0,
                    80
                ),
            ]));
        } catch (\Throwable) {
            // Preserve the original command failure.
        }
    }

    private function fingerprintSnapshot(array $invoice): array
    {
        $snapshot = [];

        foreach ([
            'UUID_FACTURA',
            'NUM_VISIBLE',
            'TIPUS_SERIE',
            'ANY_FACT',
            'TIPUS_FACTURA',
            'DATA_EMISSIO',
            'BILLING_NOM_RAO',
            'BILLING_NIF_CIF',
            'BILLING_ADRECA',
            'BILLING_CP',
            'BILLING_POBLACIO',
            'BILLING_PROVINCIA',
            'BILLING_PAIS',
            'BILLING_EMAIL',
            'IMPORT_BASE',
            'BASE_IMPOSABLE',
            'IVA_REGIM',
            'IVA_PCT',
            'IVA_IMPORT',
            'CAUSA_EXEMPCIO_NO_SUBJECTA',
            'TOTAL',
        ] as $field) {
            $snapshot[$field] = $invoice[$field] ?? null;
        }

        return $snapshot;
    }

    private function invoiceSnapshot(array $invoice): array
    {
        $snapshot = [];

        foreach ([
            'UUID_FACTURA',
            'NUM_VISIBLE',
            'TIPUS_SERIE',
            'ANY_FACT',
            'TIPUS_FACTURA',
            'ESTAT_FACTURA',
            'ESTAT_COBRAMENT',
            'ESTAT_AEAT',
            'IMPORT_BASE',
            'BASE_IMPOSABLE',
            'IVA_REGIM',
            'IVA_PCT',
            'IVA_IMPORT',
            'CAUSA_EXEMPCIO_NO_SUBJECTA',
            'TOTAL',
        ] as $field) {
            $snapshot[$field] = $invoice[$field] ?? null;
        }

        return $snapshot;
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))
            ->format('Y-m-d H:i:s');
    }
}
