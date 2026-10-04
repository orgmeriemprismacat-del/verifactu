<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Repository\PaymentActionEventWriter;
use Prisma\Sif\Repository\SifAuditEventRepository;

final class InstallmentPaymentAuditTrail
{
    public function __construct(
        private PaymentActionEventWriter $paymentEvents,
        private OperationalEventRepository $operationalEvents,
        private SifAuditEventRepository $auditEvents,
        private string $environment
    ) {
        $this->environment = $this->normalizeEnvironment($this->environment);
    }

    public function context(array $actor, array $input): array
    {
        $requestId = strtolower(trim((string) ($actor['request_id'] ?? '')));
        if (preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',
            $requestId
        ) !== 1) {
            throw SifException::unauthorized('Installment audit requires internal request id');
        }

        $actorId = trim((string) ($actor['actor_id'] ?? ''));
        $roles = $actor['roles'] ?? [];
        if ($actorId === '' || !is_array($roles) || $roles === []) {
            throw SifException::unauthorized('Installment audit requires actor identity and roles');
        }

        $actorRole = strtoupper(trim((string) reset($roles)));
        if ($actorRole === '') {
            throw SifException::unauthorized('Installment audit requires actor role');
        }

        $operationId = trim((string) ($input['operation_id'] ?? ''));
        $correlationId = $operationId !== '' ? $operationId : $requestId;

        return [
            'request_id' => $requestId,
            'correlation_id' => substr($correlationId, 0, 120),
            'actor_id' => $actorId,
            'actor_role' => substr($actorRole, 0, 80),
            'actor_type' => 'HUMAN',
            'source_environment' => $this->environment,
            'source_channel' => 'INTRANET',
            'occurred_at' => $this->now(),
            'inscription_id' => (int) ($input['id_insc'] ?? 0),
            'amount' => trim((string) ($input['amount'] ?? '')),
            'external_receipt_hash' => $this->externalReceiptHash($input),
        ];
    }

    public function requested(\PDO $db, array $context): void
    {
        $this->paymentEvents->append($db, [
            'request_id' => $context['request_id'],
            'correlation_id' => $context['correlation_id'],
            'action' => 'CREATE_REQUEST',
            'result' => 'REQUESTED',
            'is_terminal' => false,
            'source_environment' => $context['source_environment'],
            'source_channel' => $context['source_channel'],
            'actor_type' => $context['actor_type'],
            'actor_id' => $context['actor_id'],
            'actor_role' => $context['actor_role'],
            'reason_code' => 'UC023_INSTALLMENT',
            'changeset' => $this->changeset($context),
            'occurred_at' => $context['occurred_at'],
        ]);

        $this->auditEvents->append($db, [
            'request_id' => $context['request_id'],
            'correlation_id' => $context['correlation_id'],
            'action' => 'CREATE_REQUEST',
            'result' => 'REQUESTED',
            'resource_type' => 'PAYMENT',
            'source_environment' => $context['source_environment'],
            'source_channel' => $context['source_channel'],
            'actor_type' => $context['actor_type'],
            'actor_id' => $context['actor_id'],
            'actor_role' => $context['actor_role'],
            'reason_code' => 'UC023_INSTALLMENT',
            'changeset' => $this->changeset($context),
            'occurred_at' => $context['occurred_at'],
        ]);
    }

    public function succeeded(
        \PDO $db,
        array $context,
        array $paymentPayload,
        array $result
    ): void {
        $reused = ($result['idempotency_reused'] ?? false) === true;
        $uuidPayment = trim((string) ($result['uuid_payment'] ?? ''));
        $uuidFactura = trim((string) ($result['uuid_factura'] ?? ''));
        $paymentKey = trim((string) ($paymentPayload['idempotency_key'] ?? ''));

        if ($uuidPayment === '' || $uuidFactura === '' || $paymentKey === '') {
            throw SifException::validation('Installment audit terminal result is incomplete');
        }

        $terminalAction = $reused ? 'IDEMPOTENCY_REUSE' : 'CREATE';
        $terminalResult = $reused ? 'REUSED' : 'SUCCEEDED';
        $occurredAt = $this->now();

        $this->paymentEvents->append($db, [
            'uuid_payment' => $uuidPayment,
            'payment_idempotency_key' => $paymentKey,
            'request_id' => $context['request_id'],
            'correlation_id' => $context['correlation_id'],
            'action' => $terminalAction,
            'result' => $terminalResult,
            'is_terminal' => true,
            'source_environment' => $context['source_environment'],
            'source_channel' => $context['source_channel'],
            'actor_type' => $context['actor_type'],
            'actor_id' => $context['actor_id'],
            'actor_role' => $context['actor_role'],
            'reason_code' => 'UC023_INSTALLMENT',
            'changeset' => array_merge($this->changeset($context), [
                'uuid_factura' => $uuidFactura,
                'uuid_payment' => $uuidPayment,
                'reused' => $reused,
                'reconciled_existing' => ($result['reconciled_existing'] ?? false) === true,
            ]),
            'occurred_at' => $occurredAt,
        ]);

        $this->operationalEvents->append($db, [
            'operation_type' => 'REGISTER_INSTALLMENT_PAYMENT',
            'source_type' => 'INSCRIPCIO',
            'source_id' => (string) $context['inscription_id'],
            'uuid_factura' => $uuidFactura,
            'uuid_payment' => $uuidPayment,
            'fiscal_impact' => 'NONE',
            'economic_impact' => $reused ? 'NONE' : 'PAYMENT',
            'status' => $reused ? 'REUSED' : 'RECORDED',
            'reason_code' => $reused ? 'IDEMPOTENCY_REUSE' : 'UC023_INSTALLMENT',
            'after_snapshot' => [
                'amount' => $context['amount'],
                'external_receipt_hash' => $context['external_receipt_hash'],
                'reused' => $reused,
            ],
            'actor_type' => $context['actor_type'],
            'actor_id' => $context['actor_id'],
            'actor_role' => $context['actor_role'],
            'source_channel' => $context['source_channel'],
            'correlation_id' => $context['correlation_id'],
            'occurred_at' => $occurredAt,
        ]);

        $this->auditEvents->append($db, [
            'request_id' => $context['request_id'],
            'correlation_id' => $context['correlation_id'],
            'action' => $terminalAction,
            'result' => $terminalResult,
            'resource_type' => 'PAYMENT',
            'resource_id' => $uuidPayment,
            'source_environment' => $context['source_environment'],
            'source_channel' => $context['source_channel'],
            'actor_type' => $context['actor_type'],
            'actor_id' => $context['actor_id'],
            'actor_role' => $context['actor_role'],
            'reason_code' => 'UC023_INSTALLMENT',
            'changeset' => [
                'uuid_factura' => $uuidFactura,
                'inscription_id' => $context['inscription_id'],
                'reused' => $reused,
            ],
            'occurred_at' => $occurredAt,
        ]);
    }

    public function rejected(
        \PDO $db,
        array $context,
        string $action,
        string $reasonCode
    ): void {
        $this->appendFailure(
            $db,
            $context,
            $action,
            'REJECTED',
            $reasonCode,
            null
        );
    }

    public function failed(\PDO $db, array $context, \Throwable $exception): void
    {
        $status = (int) $exception->getCode();
        $rejected = $status >= 400 && $status < 500;

        $this->appendFailure(
            $db,
            $context,
            $rejected ? 'VALIDATION_REJECTED' : 'MARK_ERROR',
            $rejected ? 'REJECTED' : 'FAILED',
            $rejected ? 'UC023_REJECTED' : 'UC023_FAILED',
            substr(strtoupper(str_replace('\\', '_', $exception::class)), 0, 80)
        );
    }

    private function appendFailure(
        \PDO $db,
        array $context,
        string $action,
        string $result,
        string $reasonCode,
        ?string $errorCode
    ): void {
        $occurredAt = $this->now();

        $event = [
            'request_id' => $context['request_id'],
            'correlation_id' => $context['correlation_id'],
            'action' => $action,
            'result' => $result,
            'is_terminal' => true,
            'source_environment' => $context['source_environment'],
            'source_channel' => $context['source_channel'],
            'actor_type' => $context['actor_type'],
            'actor_id' => $context['actor_id'],
            'actor_role' => $context['actor_role'],
            'reason_code' => $reasonCode,
            'changeset' => $this->changeset($context),
            'occurred_at' => $occurredAt,
        ];
        if ($errorCode !== null) {
            $event['error_code'] = $errorCode;
        }

        $this->paymentEvents->append($db, $event);

        $audit = $event;
        unset($audit['is_terminal']);
        $audit['resource_type'] = 'PAYMENT';
        $this->auditEvents->append($db, $audit);
    }

    private function changeset(array $context): array
    {
        return [
            'inscription_id' => $context['inscription_id'],
            'amount' => $context['amount'],
            'external_receipt_hash' => $context['external_receipt_hash'],
        ];
    }

    private function externalReceiptHash(array $input): ?string
    {
        $value = trim((string) (
            $input['ds_order']
            ?? $input['reference']
            ?? ''
        ));

        return $value === '' ? null : hash('sha256', $value);
    }

    private function normalizeEnvironment(string $environment): string
    {
        return match (strtolower(trim($environment))) {
            'production' => 'PRODUCTION',
            'preproduction', 'pre' => 'PREPRODUCTION',
            'test', 'testing' => 'TEST',
            'migration' => 'MIGRATION',
            default => 'DEVELOPMENT',
        };
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))
            ->format('Y-m-d H:i:s.u');
    }
}
