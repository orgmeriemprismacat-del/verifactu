<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\DebtClaimCaseRepository;
use Prisma\Sif\Repository\DebtSnapshotRepository;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Repository\OperationalEventRepository;

final class DebtClaimCoordinator
{
    private const TEMPLATES = [
        'FINAL_REMINDER' => 'DEBT_CLAIM_FINAL_REMINDER',
        'FIRST_CLAIM' => 'DEBT_CLAIM_FIRST_CLAIM',
        'FINAL_CLAIM' => 'DEBT_CLAIM_FINAL_CLAIM',
    ];

    private array $readRoles;
    private array $manageRoles;

    public function __construct(
        private \PDO $db,
        private TransactionRunner $transactions,
        private DebtSnapshotRepository $snapshots,
        private DebtClaimCaseRepository $claims,
        private NotificationOutboxRepository $outbox,
        private OperationalEventRepository $operations,
        array $readRoles,
        array $manageRoles
    ) {
        $this->readRoles = $this->roles($readRoles);
        $this->manageRoles = $this->roles($manageRoles);
    }

    public function preview(array $actor, array $criteria): array
    {
        $this->assertRead($actor);
        $snapshot = $this->snapshot($this->db, $criteria, false);
        $claim = $this->claims->findByInvoice($this->db, $snapshot['uuid_factura']);
        $recipient = $this->recipient($snapshot, false);

        return [
            'ok' => true,
            'status' => $snapshot['is_outstanding'] ? 'OUTSTANDING' : 'SETTLED',
            'snapshot' => $snapshot,
            'claim' => $claim === null ? null : [
                'uuid_claim' => (string) $claim['UUID_CLAIM'],
                'status' => (string) $claim['STATUS'],
                'stage' => (string) $claim['CURRENT_STAGE'],
                'version_no' => (int) $claim['VERSION_NO'],
                'outstanding' => (string) $claim['OUTSTANDING_AMOUNT'],
            ],
            'can_notify' => $snapshot['is_outstanding'] && $recipient !== null,
            'recipient_type' => $recipient['type'] ?? null,
            'recipient_hash' => $recipient['hash'] ?? null,
        ];
    }

    public function recordNotice(array $actor, array $payload): array
    {
        $this->assertManage($actor);
        $action = strtoupper($this->required($payload, 'action', 50));
        if (!isset(self::TEMPLATES[$action])) {
            throw SifException::validation('Invalid debt claim notice action');
        }

        $key = $this->required($payload, 'idempotency_key', 140);
        $reason = strtoupper($this->required($payload, 'reason_code', 80));
        $requestId = $this->requestId($actor, $payload);
        $correlationId = $this->correlationId($actor, $payload);
        $channel = $this->channel($payload);
        $businessPayload = $this->businessPayload($actor, $payload, $action, $reason, $channel);

        $operation = function (\PDO $db) use (
            $actor, $payload, $action, $key, $reason, $requestId,
            $correlationId, $channel, $businessPayload
        ): array {
            $snapshot = $this->snapshot($db, $payload, true);
            $reused = $this->claims->findReusableEvent($db, $key, $businessPayload);
            if ($reused !== null) {
                return $this->reusedNotice($db, $reused, $snapshot);
            }
            if (!$snapshot['is_outstanding']) {
                return $this->noChange($snapshot, 'NO_OUTSTANDING_BALANCE');
            }

            $recipient = $this->recipient($snapshot, true);
            $claim = $this->claims->findByInvoice($db, $snapshot['uuid_factura'], true);
            if ($claim === null) {
                $claim = $this->claims->create(
                    $db, $snapshot['uuid_factura'], $snapshot['outstanding'],
                    $recipient['type'], $recipient['hash'], $correlationId
                );
            }
            if (strtoupper((string) $claim['STATUS']) !== 'OPEN') {
                throw SifException::conflict('Debt claim case is closed');
            }
            $currentStage = strtoupper((string) $claim['CURRENT_STAGE']);
            if ($this->rank($action) <= $this->rank($currentStage)) {
                throw SifException::conflict('Debt claim notice would repeat or regress current stage');
            }

            $at = $this->now();
            $event = $this->claims->appendEvent($db, $claim, [
                'event_type' => $action,
                'idempotency_key' => $key,
                'payload' => $businessPayload,
                'outstanding_before' => (string) $claim['OUTSTANDING_AMOUNT'],
                'outstanding_after' => $snapshot['outstanding'],
                'recipient_type' => $recipient['type'],
                'recipient_hash' => $recipient['hash'],
                'reason_code' => $reason,
                'actor_type' => strtoupper(trim((string) ($actor['actor_type'] ?? 'USER'))),
                'actor_id' => $actor['actor_id'] ?? null,
                'actor_role' => $this->primaryRole($actor),
                'source_channel' => $channel,
                'request_id' => $requestId,
                'correlation_id' => $correlationId,
                'occurred_at' => $at,
            ]);

            $this->claims->updateCase(
                $db, (string) $claim['UUID_CLAIM'], (int) $claim['VERSION_NO'],
                'OPEN', $action, $snapshot['outstanding'],
                $recipient['type'], $recipient['hash'], $correlationId, $at
            );

            $notification = $this->outbox->enqueue($db, [
                'idempotency_key' => $this->notificationKey($key),
                'template_code' => self::TEMPLATES[$action],
                'template_version' => 'v1',
                'recipient_type' => $recipient['type'],
                'recipient_hash' => $recipient['hash'],
                'uuid_factura' => $snapshot['uuid_factura'],
                'correlation_id' => $correlationId,
                'payload' => [
                    'source_type' => 'DEBT_CLAIM',
                    'uuid_claim' => (string) $claim['UUID_CLAIM'],
                    'uuid_claim_event' => $event['uuid_claim_event'],
                    'uuid_factura' => $snapshot['uuid_factura'],
                    'num_visible' => $snapshot['num_visible'],
                    'action' => $action,
                    'outstanding' => $snapshot['outstanding'],
                    'billing_name' => $snapshot['billing_name'],
                    'recipient_resolution' => 'SIF_INVOICE_BILLING_EMAIL_HASH',
                ],
            ]);

            $this->audit($db, $actor, $claim, $snapshot, $currentStage, $action, $reason, $channel, $correlationId, $at);

            return [
                'ok' => true,
                'status' => 'RECORDED',
                'uuid_claim' => (string) $claim['UUID_CLAIM'],
                'uuid_claim_event' => $event['uuid_claim_event'],
                'uuid_notification' => $notification['uuid_notification'],
                'uuid_factura' => $snapshot['uuid_factura'],
                'stage' => $action,
                'outstanding' => $snapshot['outstanding'],
                'idempotency_reused' => false,
            ];
        };

        return $this->runRetryingDuplicate($operation);
    }

    public function reconcileAfterPayment(array $actor, array $payload): array
    {
        $this->assertManage($actor);
        $key = $this->required($payload, 'idempotency_key', 140);
        $reason = strtoupper($this->required($payload, 'reason_code', 80));
        $requestId = $this->requestId($actor, $payload);
        $correlationId = $this->correlationId($actor, $payload);
        $channel = $this->channel($payload);
        $businessPayload = $this->businessPayload(
            $actor, $payload, 'RECONCILE_AFTER_PAYMENT', $reason, $channel
        );

        $operation = function (\PDO $db) use (
            $actor, $payload, $key, $reason, $requestId,
            $correlationId, $channel, $businessPayload
        ): array {
            $snapshot = $this->snapshot($db, $payload, true);
            $claim = $this->claims->findByInvoice($db, $snapshot['uuid_factura'], true);
            if ($claim === null) {
                return [
                    'ok' => true,
                    'status' => 'NO_CLAIM',
                    'uuid_factura' => $snapshot['uuid_factura'],
                    'outstanding' => $snapshot['outstanding'],
                    'idempotency_reused' => false,
                ];
            }

            $reused = $this->claims->findReusableEvent($db, $key, $businessPayload);
            if ($reused !== null) {
                return [
                    'ok' => true,
                    'status' => (string) $claim['STATUS'],
                    'uuid_claim' => (string) $claim['UUID_CLAIM'],
                    'uuid_claim_event' => $reused['uuid_claim_event'],
                    'uuid_factura' => $snapshot['uuid_factura'],
                    'stage' => (string) $claim['CURRENT_STAGE'],
                    'outstanding' => (string) $claim['OUTSTANDING_AMOUNT'],
                    'idempotency_reused' => true,
                ];
            }

            $closed = strtoupper((string) $claim['STATUS']) === 'CLOSED';
            if ($closed && !$snapshot['is_outstanding']) {
                return $this->noChange($snapshot, 'CLAIM_ALREADY_CLOSED', (string) $claim['UUID_CLAIM']);
            }
            if ($closed) {
                throw SifException::conflict('Closed debt claim requires explicit reopen before new debt');
            }

            $resolved = !$snapshot['is_outstanding'];
            $eventType = $resolved ? 'RESOLVED_AFTER_PAYMENT' : 'PAYMENT_PARTIAL_RECALCULATED';
            $targetStatus = $resolved ? 'CLOSED' : 'OPEN';
            $targetStage = $resolved ? 'RESOLVED' : (string) $claim['CURRENT_STAGE'];
            $at = $this->now();

            $event = $this->claims->appendEvent($db, $claim, [
                'event_type' => $eventType,
                'idempotency_key' => $key,
                'payload' => $businessPayload,
                'outstanding_before' => (string) $claim['OUTSTANDING_AMOUNT'],
                'outstanding_after' => $snapshot['outstanding'],
                'recipient_type' => $claim['RECIPIENT_TYPE'] ?? null,
                'recipient_hash' => $claim['RECIPIENT_HASH'] ?? null,
                'reason_code' => $reason,
                'actor_type' => strtoupper(trim((string) ($actor['actor_type'] ?? 'USER'))),
                'actor_id' => $actor['actor_id'] ?? null,
                'actor_role' => $this->primaryRole($actor),
                'source_channel' => $channel,
                'request_id' => $requestId,
                'correlation_id' => $correlationId,
                'occurred_at' => $at,
            ]);

            $this->claims->updateCase(
                $db, (string) $claim['UUID_CLAIM'], (int) $claim['VERSION_NO'],
                $targetStatus, $targetStage, $snapshot['outstanding'],
                $claim['RECIPIENT_TYPE'] ?? null, $claim['RECIPIENT_HASH'] ?? null,
                $correlationId, $at
            );

            $cancelled = $resolved
                ? $this->outbox->cancelPendingForInvoice(
                    $db, $snapshot['uuid_factura'], array_values(self::TEMPLATES)
                )
                : 0;

            $this->audit($db, $actor, $claim, $snapshot, (string) $claim['CURRENT_STAGE'], $targetStage, $reason, $channel, $correlationId, $at, 'DEBT_CLAIM_RECONCILE', $resolved ? 'RESOLVED' : 'RECORDED');

            return [
                'ok' => true,
                'status' => $targetStatus,
                'uuid_claim' => (string) $claim['UUID_CLAIM'],
                'uuid_claim_event' => $event['uuid_claim_event'],
                'uuid_factura' => $snapshot['uuid_factura'],
                'stage' => $targetStage,
                'outstanding' => $snapshot['outstanding'],
                'cancelled_notifications' => $cancelled,
                'idempotency_reused' => false,
            ];
        };

        return $this->runRetryingDuplicate($operation);
    }

    private function runRetryingDuplicate(callable $operation): array
    {
        try {
            return $this->transactions->run($operation);
        } catch (\PDOException $e) {
            if ((string) $e->getCode() !== '23000' && (int) ($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }
            return $this->transactions->run($operation);
        }
    }

    private function reusedNotice(\PDO $db, array $event, array $snapshot): array
    {
        $stmt = $db->prepare('SELECT IDEMPOTENCY_KEY FROM debt_claim_event WHERE UUID_CLAIM_EVENT = ?');
        $stmt->execute([$event['uuid_claim_event']]);
        $key = (string) $stmt->fetchColumn();
        $notification = $this->outbox->findByIdempotencyKey($db, $this->notificationKey($key));

        return [
            'ok' => true,
            'status' => 'RECORDED',
            'uuid_claim' => $event['uuid_claim'],
            'uuid_claim_event' => $event['uuid_claim_event'],
            'uuid_notification' => $notification['UUID_NOTIFICATION'] ?? null,
            'uuid_factura' => $snapshot['uuid_factura'],
            'stage' => $event['event_type'],
            'outstanding' => $event['outstanding_after'],
            'idempotency_reused' => true,
        ];
    }

    private function audit(
        \PDO $db, array $actor, array $claim, array $snapshot,
        string $beforeStage, string $afterStage, string $reason,
        string $channel, string $correlationId, string $at,
        string $operationType = 'DEBT_CLAIM_NOTICE', string $status = 'RECORDED'
    ): void {
        $this->operations->append($db, [
            'operation_type' => $operationType,
            'source_type' => 'DEBT_CLAIM',
            'source_id' => (string) $claim['UUID_CLAIM'],
            'uuid_factura' => $snapshot['uuid_factura'],
            'fiscal_impact' => 'NONE',
            'economic_impact' => 'NONE',
            'status' => $status,
            'reason_code' => $reason,
            'before_snapshot' => [
                'stage' => $beforeStage,
                'outstanding' => (string) $claim['OUTSTANDING_AMOUNT'],
            ],
            'after_snapshot' => [
                'stage' => $afterStage,
                'outstanding' => $snapshot['outstanding'],
            ],
            'actor_type' => strtoupper(trim((string) ($actor['actor_type'] ?? 'USER'))),
            'actor_id' => $actor['actor_id'] ?? null,
            'actor_role' => $this->primaryRole($actor),
            'source_channel' => $channel,
            'correlation_id' => $correlationId,
            'occurred_at' => $at,
        ]);
    }

    private function snapshot(\PDO $db, array $criteria, bool $lock): array
    {
        $uuid = trim((string) ($criteria['uuid_factura'] ?? ''));
        $num = trim((string) ($criteria['num_visible'] ?? ''));
        if (($uuid === '') === ($num === '')) {
            throw SifException::validation('Provide exactly one invoice identifier for debt claim');
        }
        $row = $uuid !== ''
            ? $this->snapshots->findByUuid($db, $uuid, $lock)
            : $this->snapshots->findByNumVisible($db, $num, $lock);
        if ($row === null) {
            throw SifException::notFound('Debt claim invoice not found');
        }
        return $row;
    }

    private function recipient(array $snapshot, bool $required): ?array
    {
        $email = strtolower(trim((string) ($snapshot['billing_email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if ($required) {
                throw SifException::validation('Invoice billing party has no valid email for debt claim notice');
            }
            return null;
        }
        return ['type' => 'BILLING_PARTY', 'hash' => hash('sha256', $email)];
    }

    private function businessPayload(array $actor, array $payload, string $action, string $reason, string $channel): array
    {
        return [
            'invoice' => $this->invoiceIdentity($payload),
            'action' => $action,
            'reason_code' => $reason,
            'notes' => $this->optional($payload['notes'] ?? null, 4000),
            'actor_id' => $this->optional($actor['actor_id'] ?? null, 120),
            'source_channel' => $channel,
        ];
    }

    private function invoiceIdentity(array $payload): string
    {
        $uuid = trim((string) ($payload['uuid_factura'] ?? ''));
        $num = trim((string) ($payload['num_visible'] ?? ''));
        if (($uuid === '') === ($num === '')) {
            throw SifException::validation('Provide exactly one invoice identifier for debt claim');
        }
        return $uuid !== '' ? 'UUID:' . $uuid : 'NUM:' . $num;
    }

    private function noChange(array $snapshot, string $reason, ?string $uuidClaim = null): array
    {
        return [
            'ok' => true,
            'status' => 'NO_CHANGE',
            'reason' => $reason,
            'uuid_claim' => $uuidClaim,
            'uuid_factura' => $snapshot['uuid_factura'],
            'outstanding' => $snapshot['outstanding'],
            'idempotency_reused' => false,
        ];
    }

    private function rank(string $stage): int
    {
        return match (strtoupper($stage)) {
            'DETECTED' => 0,
            'FINAL_REMINDER' => 10,
            'FIRST_CLAIM' => 20,
            'FINAL_CLAIM' => 30,
            'RESOLVED' => 100,
            default => throw SifException::conflict('Unknown debt claim stage'),
        };
    }

    private function notificationKey(string $key): string
    {
        return 'CLAIM_NOTICE|' . hash('sha256', $key);
    }

    private function channel(array $payload): string
    {
        $value = strtoupper(trim((string) ($payload['source_channel'] ?? 'INTRANET')));
        if ($value === '' || strlen($value) > 30) {
            throw SifException::validation('Invalid debt claim source channel');
        }
        return $value;
    }

    private function assertRead(array $actor): void
    {
        if (!$this->hasRole($actor, array_values(array_unique(array_merge($this->readRoles, $this->manageRoles))))) {
            throw SifException::forbidden('Debt claim read permission denied');
        }
    }

    private function assertManage(array $actor): void
    {
        if (!$this->hasRole($actor, $this->manageRoles)) {
            throw SifException::forbidden('Debt claim management permission denied');
        }
    }

    private function hasRole(array $actor, array $allowed): bool
    {
        $roles = $this->roles(is_array($actor['roles'] ?? null) ? $actor['roles'] : []);
        return $allowed !== [] && array_intersect($roles, $allowed) !== [];
    }

    private function roles(array $roles): array
    {
        $out = [];
        foreach ($roles as $role) {
            $role = strtoupper(trim((string) $role));
            if ($role !== '') {
                $out[$role] = true;
            }
        }
        return array_keys($out);
    }

    private function primaryRole(array $actor): ?string
    {
        $roles = $this->roles(is_array($actor['roles'] ?? null) ? $actor['roles'] : []);
        foreach ($roles as $role) {
            if (in_array($role, $this->manageRoles, true)) {
                return $role;
            }
        }
        return $roles[0] ?? null;
    }

    private function requestId(array $actor, array $payload): string
    {
        $v = trim((string) ($payload['request_id'] ?? $actor['request_id'] ?? ''));
        if ($v === '' || strlen($v) > 120) {
            throw SifException::validation('Debt claim request_id is required');
        }
        return $v;
    }

    private function correlationId(array $actor, array $payload): string
    {
        $v = trim((string) ($payload['correlation_id'] ?? $actor['request_id'] ?? ''));
        if ($v === '' || strlen($v) > 120) {
            throw SifException::validation('Debt claim correlation_id is required');
        }
        return $v;
    }

    private function required(array $payload, string $field, int $max): string
    {
        $v = trim((string) ($payload[$field] ?? ''));
        if ($v === '' || strlen($v) > $max) {
            throw SifException::validation('Invalid debt claim field: ' . $field);
        }
        return $v;
    }

    private function optional(mixed $value, int $max): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $v = trim((string) $value);
        if (strlen($v) > $max) {
            throw SifException::validation('Debt claim optional field is too long');
        }
        return $v;
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))
            ->format('Y-m-d H:i:s.u');
    }
}
