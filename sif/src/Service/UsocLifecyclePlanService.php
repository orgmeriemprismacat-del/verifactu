<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\UsocFinancingCaseRepository;

final class UsocLifecyclePlanService
{
    public function __construct(
        private UsocFinancingCaseRepository $cases,
        private UsocLifecycleGuardService $guard
    ) {
    }

    public function plan(
        \PDO $db,
        int $idInsc,
        int $idpag,
        string $operation
    ): array {
        $guard = $this->guard->check($db, $idInsc, $idpag, $operation);

        if (($guard['allowed'] ?? false) === true) {
            return [
                'ok' => true,
                'requires_usoc_orchestration' => false,
                'operation' => $operation,
                'id_insc' => $idInsc,
                'idpag' => $idpag,
                'reason' => (string) ($guard['reason'] ?? 'NO_SIF_USOC_CASE'),
                'actions' => [],
            ];
        }

        if (($guard['reason'] ?? '') === 'USOC_FISCAL_EVIDENCE_WITHOUT_CASE_REQUIRES_REVIEW') {
            throw SifException::conflict(
                'USOC fiscal evidence must be reconciled before lifecycle planning'
            );
        }

        $case = $guard['case'] ?? null;
        $payers = $guard['payer_snapshot'] ?? null;
        if (!is_array($case) || !is_array($payers)) {
            throw SifException::conflict('USOC financing case is incomplete for lifecycle planning');
        }

        $student = $this->payer($payers['student'] ?? null, 'student');
        $entity = $this->payer($payers['entity'] ?? null, 'entity');

        return [
            'ok' => true,
            'requires_usoc_orchestration' => true,
            'operation' => $operation,
            'id_insc' => $idInsc,
            'idpag' => $idpag,
            'case_status' => (string) ($case['STATUS'] ?? ''),
            'actions' => [
                $this->actionForPayer($student, $operation),
                $this->actionForPayer($entity, $operation),
            ],
            'invariants' => [
                'never_cross_payer_funds' => true,
                'never_refund_uncollected_amounts' => true,
                'rectify_each_invoice_independently' => true,
                'legacy_mutation_blocked_until_plan_resolved' => true,
            ],
        ];
    }

    private function payer(mixed $value, string $expectedRole): array
    {
        if (!is_array($value) || (string) ($value['role'] ?? '') !== $expectedRole) {
            throw SifException::conflict('Invalid USOC payer snapshot: ' . $expectedRole);
        }

        return $value;
    }

    private function actionForPayer(array $payer, string $operation): array
    {
        $invoiceUuid = trim((string) ($payer['invoice_uuid'] ?? ''));
        $total = $this->money((string) ($payer['total'] ?? '0.00'));
        $netPaid = $this->money((string) ($payer['net_paid'] ?? '0.00'));

        if ($invoiceUuid === '') {
            return [
                'payer_role' => (string) $payer['role'],
                'invoice_uuid' => null,
                'invoice_action' => 'NONE',
                'economic_action' => 'NONE',
                'max_refundable' => '0.00',
                'reason' => 'INVOICE_NOT_ISSUED',
            ];
        }

        $invoiceStatus = strtoupper(trim((string) ($payer['invoice_status'] ?? '')));
        if ($invoiceStatus === 'RECTIFIED' || $invoiceStatus === 'CANCELLED') {
            return [
                'payer_role' => (string) $payer['role'],
                'invoice_uuid' => $invoiceUuid,
                'invoice_action' => 'REVIEW_REQUIRED',
                'economic_action' => 'REVIEW_REQUIRED',
                'max_refundable' => $this->format($netPaid),
                'reason' => 'INVOICE_ALREADY_RECTIFIED_OR_CANCELLED',
            ];
        }

        $invoiceAction = $operation === 'course_change'
            ? 'RECTIFY_BEFORE_REISSUE'
            : 'RECTIFY_OR_CANCEL';

        return [
            'payer_role' => (string) $payer['role'],
            'invoice_uuid' => $invoiceUuid,
            'invoice_action' => $invoiceAction,
            'economic_action' => $netPaid > 0 ? 'RESOLVE_REAL_FUNDS' : 'NO_REFUND',
            'invoice_total' => $this->format($total),
            'net_paid' => $this->format($netPaid),
            'max_refundable' => $this->format($netPaid),
            'reason' => $netPaid > 0
                ? 'REAL_FUNDS_EXIST_FOR_THIS_PAYER'
                : 'NO_REAL_FUNDS_FOR_THIS_PAYER',
        ];
    }

    private function money(string $value): int
    {
        $value = str_replace(',', '.', trim($value));
        if (preg_match('/^\d+(?:\.\d{1,2})?$/D', $value) !== 1) {
            throw SifException::conflict('Invalid stored USOC monetary value');
        }

        [$whole, $decimals] = array_pad(explode('.', $value, 2), 2, '');
        $decimals = str_pad($decimals, 2, '0');

        return ((int) $whole * 100) + (int) substr($decimals, 0, 2);
    }

    private function format(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
