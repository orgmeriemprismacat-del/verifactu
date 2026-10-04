<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Contract\DebtClaimAuthorizationPolicyInterface;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\DebtClaimRepository;
use Prisma\Sif\Repository\DebtSnapshotRepository;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Repository\SifAuditEventRepository;

final class DebtClaimCoordinator
{
    private const NOTICE_TEMPLATES = [
        'FINAL_REMINDER' => 'DEBT_FINAL_REMINDER',
        'FIRST_CLAIM' => 'DEBT_FIRST_CLAIM',
        'FINAL_CLAIM' => 'DEBT_FINAL_CLAIM',
    ];

    private const STAGE_ORDER = [
        'DETECTED' => 0,
        'FINAL_REMINDER' => 1,
        'FIRST_CLAIM' => 2,
        'FINAL_CLAIM' => 3,
        'RESOLVED' => 4,
    ];

    public function __construct(
        private DebtSnapshotRepository $snapshots,
        private DebtClaimRepository $claims,
        private NotificationOutboxRepository $outbox,
        private OperationalEventRepository $operationalEvents,
        private SifAuditEventRepository $auditEvents,
        private DebtClaimAuthorizationPolicyInterface $authorization
    ) {
    }

    public function preview(\PDO $db, string $uuidFactura, array $actor): array
    {
        $snapshot = $this->requireSnapshot($db, $uuidFactura, false);
        if (!$this->authorization->canView($actor, $this->policyInvoice($snapshot))) {
            throw SifException::forbidden('Debt claim invoice is outside actor scope');
        }

        $case = $this->claims->findByInvoice($db, $snapshot['uuid_factura']);

        return [
            'uuid_factura' => $snapshot['uuid_factura'],
            'num_visible' => $snapshot['num_visible'],
            'total' => $snapshot['total'],
            'charged' => $snapshot['charged'],
            'refunded' => $snapshot['refunded'],
            'outstanding' => $snapshot['outstanding'],
            'is_outstanding' => $snapshot['is_outstanding'],
            'payment_status' => $snapshot['payment_status'],
            'invoice_status' => $snapshot['invoice_status'],
            'recipient_ready' => $this->recipientHash($snapshot) !== null,
            'payer_key_hash' => hash(
                'sha256',
                strtoupper(trim((string) ($snapshot['billing_tax_id'] ?? '')))
            ),
            'claim' => $case === null ? null : $this->caseSnapshot($case),
        ];
    }

    public function recordNotice(
        \PDO $db,
        string $uuidFactura,
        string $action,
        array $command,
        array $actor
    ): array {
        $action = strtoupper(trim($action));
        if (!isset(self::NOTICE_TEMPLATES[$action])) {
            throw SifException::validation('Invalid debt claim notice action');
        }

        $context = $this->context($command, $actor);
        $idempotencyPayload = $this->idempotencyPayload(
            $uuidFactura,
            $action,
            $context,
            $actor
        );

        if ($db->inTransaction()) {
            throw new \LogicException('Debt claim notice requires an independent transaction');
        }

        $db->beginTransaction();

        try {
            $snapshot = $this->requireSnapshot($db, $uuidFactura, true);
            $this->assertManageAllowed($actor, $snapshot, $action);

            $reused = $this->claims->reuseEventIfSame(
                $db,
                $context['idempotency_key'],
                $idempotencyPayload,
                true
            );
            if ($reused !== null) {
                $case = $this->claims->findByInvoice($db, $snapshot['uuid_factura'], true);
                $db->commit();

                return $this->result($reused, $case, true);
            }

            $case = $this->getOrCreateCase($db, $snapshot, $context, $actor);
            $before = $this->caseSnapshot($case);

            if (!$snapshot['is_outstanding'] || $snapshot['invoice_status'] !== 'ISSUED') {
                $cancelled = $this->outbox->cancelPendingForInvoice(
                    $db,
                    $snapshot['uuid_factura'],
                    array_values(self::NOTICE_TEMPLATES)
                );
                $case = $this->claims->updateCase(
                    $db,
                    (string) $case['UUID_CLAIM'],
                    (int) $case['LOCK_VERSION'],
                    [
                        'status' => 'RESOLVED',
                        'stage' => 'RESOLVED',
                        'outstanding_amount' => $snapshot['outstanding'],
                        'recipient_type' => 'BILLING_PARTY',
                        'recipient_hash' => $this->recipientHash($snapshot),
                        'correlation_id' => $context['correlation_id'],
                        'resolved_at' => $context['occurred_at'],
                    ]
                );
                $event = $this->claims->appendEvent(
                    $db,
                    $this->eventPayload(
                        $case,
                        $action,
                        'NO_CHANGE',
                        $before['stage'] ?? null,
                        'RESOLVED',
                        $snapshot,
                        $context,
                        $actor,
                        null,
                        ['cancelled_notifications' => $cancelled]
                    ),
                    $idempotencyPayload
                );
                $this->appendTrace(
                    $db,
                    $action,
                    'NO_CHANGE',
                    $before,
                    $this->caseSnapshot($case),
                    $case,
                    $snapshot,
                    $context,
                    $actor,
                    null
                );
                $db->commit();

                return $this->result($event, $case, false);
            }

            $recipientHash = $this->recipientHash($snapshot);
            if ($recipientHash === null) {
                throw SifException::conflict(
                    'Debt claim invoice has no valid billing email for notification'
                );
            }

            $fromStage = strtoupper((string) $case['STAGE']);
            if (strtoupper((string) $case['STATUS']) !== 'RESOLVED') {
                $currentOrder = self::STAGE_ORDER[$fromStage] ?? null;
                $targetOrder = self::STAGE_ORDER[$action] ?? null;
                if ($currentOrder === null || $targetOrder === null || $currentOrder >= $targetOrder) {
                    throw SifException::conflict(
                        'Debt claim notice would repeat or move the case backwards'
                    );
                }
            }

            $notification = $this->outbox->enqueue($db, [
                'idempotency_key' => $this->notificationKey(
                    $snapshot['uuid_factura'],
                    $action,
                    $context['idempotency_key']
                ),
                'template_code' => self::NOTICE_TEMPLATES[$action],
                'template_version' => '1',
                'recipient_type' => 'BILLING_PARTY',
                'recipient_hash' => $recipientHash,
                'uuid_factura' => $snapshot['uuid_factura'],
                'correlation_id' => $context['correlation_id'],
                'payload' => [
                    'uc' => 'UC-012',
                    'action' => $action,
                    'uuid_claim' => (string) $case['UUID_CLAIM'],
                    'uuid_factura' => $snapshot['uuid_factura'],
                    'num_visible' => $snapshot['num_visible'],
                    'outstanding_amount' => $snapshot['outstanding'],
                    'currency' => 'EUR',
                    'recipient_resolution' => 'INVOICE_BILLING_EMAIL_HASH',
                    'reason_code' => $context['reason_code'],
                ],
            ]);

            $case = $this->claims->updateCase(
                $db,
                (string) $case['UUID_CLAIM'],
                (int) $case['LOCK_VERSION'],
                [
                    'status' => 'OPEN',
                    'stage' => $action,
                    'outstanding_amount' => $snapshot['outstanding'],
                    'recipient_type' => 'BILLING_PARTY',
                    'recipient_hash' => $recipientHash,
                    'correlation_id' => $context['correlation_id'],
                    'resolved_at' => null,
                ]
            );

            $event = $this->claims->appendEvent(
                $db,
                $this->eventPayload(
                    $case,
                    $action,
                    'CREATED',
                    $fromStage,
                    $action,
                    $snapshot,
                    $context,
                    $actor,
                    (string) $notification['uuid_notification'],
                    ['notification_reused' => (bool) $notification['idempotency_reused']]
                ),
                $idempotencyPayload
            );

            $this->appendTrace(
                $db,
                $action,
                'CREATED',
                $before,
                $this->caseSnapshot($case),
                $case,
                $snapshot,
                $context,
                $actor,
                (string) $notification['uuid_notification']
            );

            $db->commit();

            return $this->result($event, $case, false);
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function reconcileAfterPayment(
        \PDO $db,
        string $uuidFactura,
        string $uuidPayment,
        array $command,
        array $actor
    ): array {
        $uuidPayment = trim($uuidPayment);
        if ($uuidPayment === '') {
            throw SifException::validation('Debt claim payment UUID is required');
        }

        $action = 'PAYMENT_RECONCILED';
        $context = $this->context($command, $actor);
        $idempotencyPayload = $this->idempotencyPayload(
            $uuidFactura,
            $action,
            $context,
            $actor,
            ['uuid_payment' => $uuidPayment]
        );

        if ($db->inTransaction()) {
            throw new \LogicException('Debt claim payment reconciliation requires an independent transaction');
        }

        $db->beginTransaction();

        try {
            $snapshot = $this->requireSnapshot($db, $uuidFactura, true);
            $this->assertManageAllowed($actor, $snapshot, $action);

            $allocation = $this->snapshots->findConfirmedPaymentAllocation(
                $db,
                $uuidPayment,
                $snapshot['uuid_factura']
            );
            if ($allocation === null) {
                throw SifException::conflict(
                    'Confirmed payment is not allocated to the debt claim invoice'
                );
            }

            $reused = $this->claims->reuseEventIfSame(
                $db,
                $context['idempotency_key'],
                $idempotencyPayload,
                true
            );
            if ($reused !== null) {
                $case = $this->claims->findByInvoice($db, $snapshot['uuid_factura'], true);
                $db->commit();

                return $this->result($reused, $case, true);
            }

            $case = $this->getOrCreateCase($db, $snapshot, $context, $actor);
            $before = $this->caseSnapshot($case);
            $fromStage = strtoupper((string) $case['STAGE']);

            $cancelled = $this->outbox->cancelPendingForInvoice(
                $db,
                $snapshot['uuid_factura'],
                array_values(self::NOTICE_TEMPLATES)
            );

            $resolved = !$snapshot['is_outstanding'];
            $toStage = $resolved
                ? 'RESOLVED'
                : ($fromStage === 'RESOLVED' ? 'DETECTED' : $fromStage);

            $case = $this->claims->updateCase(
                $db,
                (string) $case['UUID_CLAIM'],
                (int) $case['LOCK_VERSION'],
                [
                    'status' => $resolved ? 'RESOLVED' : 'OPEN',
                    'stage' => $toStage,
                    'outstanding_amount' => $snapshot['outstanding'],
                    'recipient_type' => 'BILLING_PARTY',
                    'recipient_hash' => $this->recipientHash($snapshot),
                    'correlation_id' => $context['correlation_id'],
                    'resolved_at' => $resolved ? $context['occurred_at'] : null,
                ]
            );

            $event = $this->claims->appendEvent(
                $db,
                $this->eventPayload(
                    $case,
                    $action,
                    $resolved ? 'RESOLVED' : 'PARTIAL',
                    $fromStage,
                    $toStage,
                    $snapshot,
                    $context,
                    $actor,
                    null,
                    [
                        'uuid_payment' => $uuidPayment,
                        'payment_allocation' => $allocation,
                        'cancelled_notifications' => $cancelled,
                    ]
                ),
                $idempotencyPayload
            );

            $this->appendTrace(
                $db,
                $action,
                $resolved ? 'RESOLVED' : 'PARTIAL',
                $before,
                $this->caseSnapshot($case),
                $case,
                $snapshot,
                $context,
                $actor,
                null,
                $uuidPayment
            );

            $db->commit();

            return $this->result($event, $case, false);
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    private function requireSnapshot(
        \PDO $db,
        string $uuidFactura,
        bool $forUpdate
    ): array {
        $snapshot = $this->snapshots->findByUuid($db, trim($uuidFactura), $forUpdate);
        if ($snapshot === null) {
            throw SifException::notFound('Debt claim invoice was not found');
        }

        return $snapshot;
    }

    private function assertManageAllowed(array $actor, array $snapshot, string $action): void
    {
        if (!$this->authorization->canManage($actor, $this->policyInvoice($snapshot), $action)) {
            throw SifException::forbidden('Debt claim mutation is outside actor scope');
        }
    }

    private function policyInvoice(array $snapshot): array
    {
        return [
            'UUID_FACTURA' => $snapshot['uuid_factura'],
            'NUM_VISIBLE' => $snapshot['num_visible'],
        ];
    }

    private function getOrCreateCase(
        \PDO $db,
        array $snapshot,
        array $context,
        array $actor
    ): array {
        $case = $this->claims->findByInvoice($db, $snapshot['uuid_factura'], true);
        if ($case !== null) {
            return $case;
        }

        return $this->claims->createCase($db, [
            'uuid_factura' => $snapshot['uuid_factura'],
            'status' => $snapshot['is_outstanding'] ? 'OPEN' : 'RESOLVED',
            'stage' => $snapshot['is_outstanding'] ? 'DETECTED' : 'RESOLVED',
            'outstanding_amount' => $snapshot['outstanding'],
            'currency' => 'EUR',
            'recipient_type' => 'BILLING_PARTY',
            'recipient_hash' => $this->recipientHash($snapshot),
            'correlation_id' => $context['correlation_id'],
            'created_by' => $this->optionalString($actor['actor_id'] ?? null),
            'resolved_at' => $snapshot['is_outstanding'] ? null : $context['occurred_at'],
        ]);
    }

    private function recipientHash(array $snapshot): ?string
    {
        $email = strtolower(trim((string) ($snapshot['billing_email'] ?? '')));
        if ($email === '') {
            return null;
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw SifException::conflict('Invoice billing email is invalid');
        }

        return hash('sha256', $email);
    }

    private function context(array $command, array $actor): array
    {
        $context = [];
        foreach ([
            'idempotency_key',
            'request_id',
            'correlation_id',
            'reason_code',
            'source_environment',
            'source_channel',
            'occurred_at',
        ] as $field) {
            $value = trim((string) ($command[$field] ?? ''));
            if ($value === '') {
                throw SifException::validation('Missing debt claim command field: ' . $field);
            }
            $context[$field] = $value;
        }

        $actorType = trim((string) ($actor['actor_type'] ?? ''));
        if ($actorType === '') {
            throw SifException::validation('Missing debt claim actor type');
        }

        if (strlen($context['idempotency_key']) > 140) {
            throw SifException::validation('Debt claim idempotency key is too long');
        }

        return $context;
    }

    private function idempotencyPayload(
        string $uuidFactura,
        string $action,
        array $context,
        array $actor,
        array $extra = []
    ): array {
        return array_merge([
            'uuid_factura' => trim($uuidFactura),
            'action' => strtoupper(trim($action)),
            'request_id' => $context['request_id'],
            'correlation_id' => $context['correlation_id'],
            'reason_code' => strtoupper($context['reason_code']),
            'source_environment' => strtoupper($context['source_environment']),
            'source_channel' => strtoupper($context['source_channel']),
            'actor_type' => strtoupper(trim((string) ($actor['actor_type'] ?? ''))),
            'actor_id' => $this->optionalString($actor['actor_id'] ?? null),
            'actor_role' => $this->optionalString($actor['actor_role'] ?? null),
        ], $extra);
    }

    private function eventPayload(
        array $case,
        string $action,
        string $result,
        ?string $fromStage,
        string $toStage,
        array $snapshot,
        array $context,
        array $actor,
        ?string $uuidNotification,
        array $metadata
    ): array {
        return [
            'uuid_claim' => (string) $case['UUID_CLAIM'],
            'action' => $action,
            'result' => $result,
            'from_stage' => $fromStage,
            'to_stage' => $toStage,
            'outstanding_amount' => $snapshot['outstanding'],
            'idempotency_key' => $context['idempotency_key'],
            'request_id' => $context['request_id'],
            'correlation_id' => $context['correlation_id'],
            'actor_type' => (string) $actor['actor_type'],
            'actor_id' => $this->optionalString($actor['actor_id'] ?? null),
            'actor_role' => $this->optionalString($actor['actor_role'] ?? null),
            'reason_code' => $context['reason_code'],
            'uuid_notification' => $uuidNotification,
            'metadata' => $metadata,
            'occurred_at' => $context['occurred_at'],
        ];
    }

    private function appendTrace(
        \PDO $db,
        string $action,
        string $result,
        array $before,
        array $after,
        array $case,
        array $snapshot,
        array $context,
        array $actor,
        ?string $uuidNotification,
        ?string $uuidPayment = null
    ): void {
        $this->operationalEvents->append($db, [
            'operation_type' => 'DEBT_CLAIM_' . $action,
            'source_type' => 'DEBT_CLAIM',
            'source_id' => (string) $case['UUID_CLAIM'],
            'uuid_factura' => $snapshot['uuid_factura'],
            'uuid_payment' => $uuidPayment,
            'fiscal_impact' => 'NONE',
            'economic_impact' => 'NONE',
            'status' => $result,
            'reason_code' => $context['reason_code'],
            'before_snapshot' => $before,
            'after_snapshot' => $after,
            'actor_type' => (string) $actor['actor_type'],
            'actor_id' => $this->optionalString($actor['actor_id'] ?? null),
            'actor_role' => $this->optionalString($actor['actor_role'] ?? null),
            'source_channel' => $context['source_channel'],
            'correlation_id' => $context['correlation_id'],
            'occurred_at' => $context['occurred_at'],
        ]);

        $this->auditEvents->append($db, [
            'request_id' => $context['request_id'],
            'correlation_id' => $context['correlation_id'],
            'action' => 'DEBT_CLAIM_' . $action,
            'result' => $result,
            'resource_type' => 'DEBT_CLAIM',
            'resource_id' => (string) $case['UUID_CLAIM'],
            'source_environment' => $context['source_environment'],
            'source_channel' => $context['source_channel'],
            'actor_type' => (string) $actor['actor_type'],
            'actor_id' => $this->optionalString($actor['actor_id'] ?? null),
            'actor_role' => $this->optionalString($actor['actor_role'] ?? null),
            'reason_code' => $context['reason_code'],
            'before_hash' => $this->snapshotHash($before),
            'after_hash' => $this->snapshotHash($after),
            'changeset' => [
                'action' => $action,
                'result' => $result,
                'uuid_factura' => $snapshot['uuid_factura'],
                'uuid_notification' => $uuidNotification,
                'uuid_payment' => $uuidPayment,
                'outstanding_amount' => $snapshot['outstanding'],
            ],
            'occurred_at' => $context['occurred_at'],
        ]);
    }

    private function result(?array $event, ?array $case, bool $reused): array
    {
        if ($event === null || $case === null) {
            throw new \RuntimeException('Debt claim result is incomplete');
        }

        return [
            'ok' => true,
            'idempotency_reused' => $reused,
            'uuid_claim' => (string) $case['UUID_CLAIM'],
            'status' => (string) $case['STATUS'],
            'stage' => (string) $case['STAGE'],
            'outstanding_amount' => (string) $case['OUTSTANDING_AMOUNT'],
            'uuid_event' => (string) $event['UUID_EVENT'],
            'event_result' => (string) $event['RESULT'],
            'uuid_notification' => $this->optionalString(
                $event['UUID_NOTIFICATION'] ?? null
            ),
        ];
    }

    private function caseSnapshot(array $case): array
    {
        return [
            'uuid_claim' => (string) $case['UUID_CLAIM'],
            'status' => (string) $case['STATUS'],
            'stage' => (string) $case['STAGE'],
            'outstanding_amount' => (string) $case['OUTSTANDING_AMOUNT'],
            'currency' => (string) $case['CURRENCY'],
            'lock_version' => (int) $case['LOCK_VERSION'],
        ];
    }

    private function snapshotHash(array $snapshot): string
    {
        ksort($snapshot);

        return hash(
            'sha256',
            json_encode(
                $snapshot,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
            )
        );
    }

    private function notificationKey(
        string $uuidFactura,
        string $action,
        string $idempotencyKey
    ): string {
        return 'DEBT|' . $action
            . '|F:' . substr(hash('sha256', $uuidFactura), 0, 24)
            . '|K:' . hash('sha256', $idempotencyKey);
    }

    private function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
