<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\EnrollmentCancellationEventRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Repository\UsocLifecycleExecutionRepository;

final class UsocCancellationExecutionService
{
    public function __construct(
        private UsocLifecyclePlanService $plans,
        private UsocLifecycleExecutionRepository $executions,
        private ManualRectificationService $rectifications,
        private ManualRefundService $refunds,
        private OperationalEventRepository $operationalEvents,
        private EnrollmentCancellationEventRepository $cancellations
    ) {
    }

    public function execute(
        \PDO $db,
        int $idInsc,
        int $idpag,
        string $requestId,
        string $actorId,
        array $roles,
        array $input
    ): array {
        $requestId = $this->requestId($requestId);
        $actorId = trim($actorId);
        if ($idInsc <= 0 || $idpag <= 0 || $actorId === '') {
            throw SifException::validation('Invalid USOC cancellation execution identity');
        }

        $request = $this->normalizeRequest($input);
        $existing = $this->executions->findByRequestId($db, $requestId);

        if ($existing !== null) {
            $plan = $this->decodeObject((string) $existing['PLAN_JSON'], 'stored lifecycle plan');
            $execution = $this->executions->begin(
                $db,
                $requestId,
                'USOC|CANCELLATION|ID_INSC:' . $idInsc,
                $idInsc,
                $idpag,
                'CANCELLATION',
                $actorId,
                $roles,
                $request,
                $plan
            );

            if ((string) $execution['STATE'] === 'COMPLETED') {
                $result = $this->decodeObject((string) $execution['RESULT_JSON'], 'stored lifecycle result');
                $result['idempotency_reused'] = true;
                return $result;
            }

            if ((string) $execution['STATE'] === 'REVIEW_REQUIRED') {
                throw SifException::conflict(
                    'USOC cancellation execution requires review: '
                    . (string) ($execution['REVIEW_REASON'] ?? 'UNKNOWN')
                );
            }
        } else {
            $plan = $this->plans->plan($db, $idInsc, $idpag, 'cancellation');
            if (($plan['requires_usoc_orchestration'] ?? false) !== true) {
                throw SifException::conflict('USOC cancellation orchestration is not required for this enrollment');
            }

            $this->validateRequestAgainstPlan($request, $plan);

            $db->beginTransaction();
            try {
                $this->executions->begin(
                    $db,
                    $requestId,
                    'USOC|CANCELLATION|ID_INSC:' . $idInsc,
                    $idInsc,
                    $idpag,
                    'CANCELLATION',
                    $actorId,
                    $roles,
                    $request,
                    $plan
                );
                $db->commit();
            } catch (\Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $exception;
            }
        }

        $this->validateRequestAgainstPlan($request, $plan);

        $payerResults = [];
        foreach (['student', 'entity'] as $role) {
            $payerPlan = $this->payerPlan($plan, $role);
            $payerResults[$role] = $this->executePayer(
                $db,
                $requestId,
                $actorId,
                $role,
                $payerPlan,
                $request[$role]
            );
        }

        $requiresFollowUp = false;
        foreach ($payerResults as $payerResult) {
            if (
                in_array($payerResult['fiscal_action'], ['DEFER_FISCAL'], true)
                || in_array($payerResult['economic_action'], ['DEFER_REFUND'], true)
            ) {
                $requiresFollowUp = true;
            }
        }

        $result = [
            'ok' => true,
            'request_id' => $requestId,
            'id_insc' => $idInsc,
            'idpag' => $idpag,
            'operation' => 'cancellation',
            'reason_code' => $request['reason_code'],
            'requires_follow_up' => $requiresFollowUp,
            'payers' => $payerResults,
            'idempotency_reused' => false,
        ];

        $db->beginTransaction();
        try {
            $locked = $this->executions->findByRequestId($db, $requestId, true);
            if ($locked === null) {
                throw SifException::conflict('USOC cancellation execution checkpoint not found');
            }

            if ((string) $locked['STATE'] === 'COMPLETED') {
                $db->commit();
                $stored = $this->decodeObject(
                    (string) $locked['RESULT_JSON'],
                    'stored lifecycle result'
                );
                $stored['idempotency_reused'] = true;
                return $stored;
            }

            foreach (['student', 'entity'] as $role) {
                $this->persistPayerEvent(
                    $db,
                    $idInsc,
                    $requestId,
                    $actorId,
                    $roles,
                    $request,
                    $this->payerPlan($plan, $role),
                    $payerResults[$role],
                    $role
                );
            }

            $this->executions->complete($db, $requestId, $result);
            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        return $result;
    }

    private function executePayer(
        \PDO $db,
        string $requestId,
        string $actorId,
        string $role,
        array $plan,
        array $decision
    ): array {
        $invoiceUuid = trim((string) ($plan['invoice_uuid'] ?? ''));
        $rectification = null;
        $refund = null;

        if ($decision['fiscal_action'] === 'RECTIFY') {
            $rectification = $this->rectifications->issueByUuid(
                $db,
                $invoiceUuid,
                [
                    'amount' => $decision['rectification_amount'],
                    'reason' => $decision['rectification_reason'],
                    'mode' => $decision['rectification_mode'],
                    'concept' => 'Baixa USOC · ' . $role,
                    'detail' => $decision['fiscal_reason'],
                    'created_by' => $actorId,
                    'reference' => $this->stableKey($requestId, $role, 'RECT'),
                ]
            );
        }

        if ($decision['economic_action'] === 'REFUND') {
            $refund = $this->refunds->registerByUuid(
                $db,
                $invoiceUuid,
                [
                    'idempotency_key' => $this->stableKey($requestId, $role, 'REFUND'),
                    'amount' => $decision['refund_amount'],
                    'movement_date' => $decision['refund_movement_date'],
                    'reference' => $decision['refund_reference'],
                    'bank' => $decision['refund_bank'],
                    'notes' => $decision['economic_reason'],
                ]
            );
        }

        return [
            'payer_role' => $role,
            'invoice_uuid' => $invoiceUuid === '' ? null : $invoiceUuid,
            'fiscal_action' => $decision['fiscal_action'],
            'rectification_amount' => $decision['rectification_amount'],
            'uuid_rectifying_invoice' => $rectification['uuid_factura'] ?? null,
            'economic_action' => $decision['economic_action'],
            'refund_amount' => $decision['refund_amount'],
            'uuid_refund_payment' => $refund['uuid_payment'] ?? null,
            'max_refundable' => (string) ($plan['max_refundable'] ?? '0.00'),
        ];
    }

    private function persistPayerEvent(
        \PDO $db,
        int $idInsc,
        string $requestId,
        string $actorId,
        array $roles,
        array $request,
        array $plan,
        array $result,
        string $role
    ): void {
        $rectified = $result['uuid_rectifying_invoice'] !== null;
        $refunded = $result['uuid_refund_payment'] !== null;
        $occurredAt = $request['effective_at'];
        $decision = $request[$role];

        $eventUuid = $this->operationalEvents->append($db, [
            'operation_type' => 'USOC_CANCELLATION_' . strtoupper($role),
            'source_type' => 'INSCRIPCIO',
            'source_id' => (string) $idInsc,
            'uuid_factura' => $result['invoice_uuid'],
            'uuid_payment' => $result['uuid_refund_payment'],
            'fiscal_impact' => $rectified ? 'RECTIFICATION' : 'NONE',
            'economic_impact' => $refunded ? 'REFUND' : 'NONE',
            'status' => (
                $decision['fiscal_action'] === 'DEFER_FISCAL'
                || $decision['economic_action'] === 'DEFER_REFUND'
            ) ? 'COMPLETED_WITH_PENDING' : 'COMPLETED',
            'reason_code' => $request['reason_code'],
            'before_snapshot' => $plan,
            'after_snapshot' => $result,
            'actor_type' => 'USER',
            'actor_id' => $actorId,
            'actor_role' => $this->primaryRole($roles),
            'source_channel' => 'INTRANET',
            'correlation_id' => $requestId,
            'occurred_at' => $occurredAt,
        ]);

        $this->cancellations->append($db, [
            'uuid_operational_event' => $eventUuid,
            'enrollment_id' => $idInsc,
            'cancellation_reason' => $request['reason_code'],
            'effective_at' => $occurredAt,
            'economic_decision' => $decision['economic_action'],
            'return_amount' => $decision['refund_amount'],
            'credit_amount' => '0.00',
            'non_return_reason' => in_array(
                $decision['economic_action'],
                ['NO_REFUND', 'DEFER_REFUND'],
                true
            ) ? $decision['economic_reason'] : null,
            'fiscal_decision' => $decision['fiscal_action'],
            'uuid_rectifying_invoice' => $result['uuid_rectifying_invoice'],
            'uuid_refund_payment' => $result['uuid_refund_payment'],
        ]);
    }

    private function validateRequestAgainstPlan(array $request, array $plan): void
    {
        foreach (['student', 'entity'] as $role) {
            $payerPlan = $this->payerPlan($plan, $role);
            $decision = $request[$role];
            $invoiceUuid = trim((string) ($payerPlan['invoice_uuid'] ?? ''));

            if ($invoiceUuid === '') {
                if (
                    $decision['fiscal_action'] !== 'NO_FISCAL_EFFECT'
                    || $decision['economic_action'] !== 'NO_REFUND'
                ) {
                    throw SifException::conflict(
                        'Cannot rectify or refund USOC ' . $role . ' without an issued invoice'
                    );
                }
                continue;
            }

            $invoiceTotal = $this->cents((string) ($payerPlan['invoice_total'] ?? '0.00'));
            $maxRefundable = $this->cents((string) ($payerPlan['max_refundable'] ?? '0.00'));

            if ($decision['fiscal_action'] === 'RECTIFY') {
                $rectification = $this->signedCents($decision['rectification_amount']);
                if ($rectification >= 0 || abs($rectification) > $invoiceTotal) {
                    throw SifException::validation(
                        'Invalid USOC ' . $role . ' rectification amount'
                    );
                }
                if (
                    $decision['rectification_mode'] === 'SUBSTITUCIO'
                    && abs($rectification) !== $invoiceTotal
                ) {
                    throw SifException::validation(
                        'USOC cancellation substitution must rectify the full payer invoice'
                    );
                }
            } elseif ($decision['fiscal_reason'] === '') {
                throw SifException::validation(
                    'USOC ' . $role . ' no-fiscal-effect decision requires a reason'
                );
            }

            if ($decision['economic_action'] === 'REFUND') {
                $refund = $this->cents($decision['refund_amount']);
                if ($refund <= 0 || $refund > $maxRefundable) {
                    throw SifException::validation(
                        'USOC ' . $role . ' refund exceeds real refundable funds'
                    );
                }
            } elseif ($maxRefundable > 0 && $decision['economic_reason'] === '') {
                throw SifException::validation(
                    'USOC ' . $role . ' no-refund decision requires a reason'
                );
            }
        }
    }

    private function payerPlan(array $plan, string $role): array
    {
        foreach ((array) ($plan['actions'] ?? []) as $action) {
            if ((string) ($action['payer_role'] ?? '') === $role) {
                return $action;
            }
        }

        throw SifException::conflict('Missing USOC lifecycle plan for payer: ' . $role);
    }

    private function normalizeRequest(array $input): array
    {
        $reason = strtoupper(trim((string) ($input['reason_code'] ?? '')));
        $effectiveAt = trim((string) ($input['effective_at'] ?? ''));

        if (
            $reason === ''
            || strlen($reason) > 80
            || preg_match('/^[A-Z0-9_:-]+$/D', $reason) !== 1
        ) {
            throw SifException::validation('Invalid USOC cancellation reason code');
        }

        $date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $effectiveAt,
            new \DateTimeZone('Europe/Madrid')
        );
        $dateErrors = \DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || ($dateErrors !== false
                && (($dateErrors['warning_count'] ?? 0) > 0
                    || ($dateErrors['error_count'] ?? 0) > 0))
            || $date->format('Y-m-d H:i:s') !== $effectiveAt
        ) {
            throw SifException::validation('Invalid USOC cancellation effective_at');
        }

        return [
            'reason_code' => $reason,
            'effective_at' => $effectiveAt,
            'student' => $this->normalizePayerDecision($input['student'] ?? null, 'student'),
            'entity' => $this->normalizePayerDecision($input['entity'] ?? null, 'entity'),
        ];
    }

    private function normalizePayerDecision(mixed $value, string $role): array
    {
        if (!is_array($value)) {
            throw SifException::validation('Missing USOC cancellation decision for ' . $role);
        }

        $fiscalAction = strtoupper(trim((string) ($value['fiscal_action'] ?? '')));
        if (!in_array($fiscalAction, ['RECTIFY', 'NO_FISCAL_EFFECT', 'DEFER_FISCAL'], true)) {
            throw SifException::validation('Invalid USOC fiscal action for ' . $role);
        }

        $economicAction = strtoupper(trim((string) ($value['economic_action'] ?? '')));
        if (!in_array($economicAction, ['REFUND', 'NO_REFUND', 'DEFER_REFUND'], true)) {
            throw SifException::validation('Invalid USOC economic action for ' . $role);
        }

        $mode = strtoupper(trim((string) ($value['rectification_mode'] ?? '')));
        if ($fiscalAction === 'RECTIFY' && !in_array($mode, ['DIFERENCIES', 'SUBSTITUCIO'], true)) {
            throw SifException::validation('Invalid USOC rectification mode for ' . $role);
        }

        $rectificationReason = strtoupper(trim((string) ($value['rectification_reason'] ?? '')));
        if ($fiscalAction === 'RECTIFY' && $rectificationReason === '') {
            throw SifException::validation('Missing USOC rectification reason for ' . $role);
        }

        $refundReference = trim((string) ($value['refund_reference'] ?? ''));
        $refundDate = trim((string) ($value['refund_movement_date'] ?? ''));
        if ($economicAction === 'REFUND' && ($refundReference === '' || $refundDate === '')) {
            throw SifException::validation('USOC refund reference and movement date are required');
        }

        return [
            'fiscal_action' => $fiscalAction,
            'rectification_amount' => $fiscalAction === 'RECTIFY'
                ? $this->signedMoney($value['rectification_amount'] ?? null)
                : '0.00',
            'rectification_mode' => $fiscalAction === 'RECTIFY' ? $mode : null,
            'rectification_reason' => $fiscalAction === 'RECTIFY' ? $rectificationReason : null,
            'fiscal_reason' => trim((string) ($value['fiscal_reason'] ?? '')),
            'economic_action' => $economicAction,
            'refund_amount' => $economicAction === 'REFUND'
                ? $this->money($value['refund_amount'] ?? null)
                : '0.00',
            'refund_movement_date' => $economicAction === 'REFUND' ? $refundDate : null,
            'refund_reference' => $economicAction === 'REFUND' ? $refundReference : null,
            'refund_bank' => $economicAction === 'REFUND'
                ? trim((string) ($value['refund_bank'] ?? ''))
                : null,
            'economic_reason' => trim((string) ($value['economic_reason'] ?? '')),
        ];
    }

    private function stableKey(string $requestId, string $role, string $suffix): string
    {
        return 'USOC|CANCEL|' . substr(hash('sha256', $requestId), 0, 32)
            . '|ROLE:' . strtoupper($role) . '|' . $suffix;
    }

    private function requestId(string $value): string
    {
        $value = trim($value);
        if (
            $value === ''
            || strlen($value) > 120
            || preg_match('/^[A-Za-z0-9._:-]+$/D', $value) !== 1
        ) {
            throw SifException::validation('Invalid USOC cancellation request id');
        }

        return $value;
    }

    private function money(mixed $value): string
    {
        if (!is_numeric($value) || (float) $value < 0) {
            throw SifException::validation('Invalid USOC monetary value');
        }
        return number_format((float) $value, 2, '.', '');
    }

    private function signedMoney(mixed $value): string
    {
        if (!is_numeric($value) || abs((float) $value) < 0.005) {
            throw SifException::validation('Invalid USOC signed monetary value');
        }
        return number_format((float) $value, 2, '.', '');
    }

    private function cents(string $value): int
    {
        $value = str_replace(',', '.', trim($value));
        if (preg_match('/^\d+(?:\.\d{1,2})?$/D', $value) !== 1) {
            throw SifException::conflict('Invalid stored USOC monetary value');
        }
        [$whole, $decimals] = array_pad(explode('.', $value, 2), 2, '');
        $decimals = str_pad($decimals, 2, '0');
        return ((int) $whole * 100) + (int) substr($decimals, 0, 2);
    }

    private function signedCents(string $value): int
    {
        $negative = str_starts_with($value, '-');
        $unsigned = $negative ? substr($value, 1) : $value;
        $cents = $this->cents($unsigned);
        return $negative ? -$cents : $cents;
    }

    private function primaryRole(array $roles): ?string
    {
        foreach ($roles as $role) {
            $role = strtoupper(trim((string) $role));
            if ($role !== '') {
                return $role;
            }
        }
        return null;
    }

    private function decodeObject(string $json, string $label): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw SifException::conflict('Invalid ' . $label);
        }
        return $decoded;
    }
}
