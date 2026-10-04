<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class UsocCourseChangeFundPlanService
{
    public function plan(array $lifecyclePlan, array $target): array
    {
        if (
            strtolower(trim((string) ($lifecyclePlan['operation'] ?? ''))) !== 'course_change'
            || ($lifecyclePlan['requires_usoc_orchestration'] ?? false) !== true
        ) {
            throw SifException::conflict(
                'USOC course change fund planning requires an orchestration plan'
            );
        }

        $student = $this->forPayer(
            'student',
            $this->payerAction($lifecyclePlan, 'student'),
            $this->money($target['target_student_total'] ?? null, 'target_student_total')
        );
        $entity = $this->forPayer(
            'entity',
            $this->payerAction($lifecyclePlan, 'entity'),
            $this->money($target['target_entity_total'] ?? null, 'target_entity_total')
        );

        return [
            'ok' => true,
            'operation' => 'course_change',
            'payers' => [
                'student' => $student,
                'entity' => $entity,
            ],
            'totals' => [
                'target_obligation' => $this->format(
                    $this->cents($student['target_obligation'])
                    + $this->cents($entity['target_obligation'])
                ),
                'compensate_amount' => $this->format(
                    $this->cents($student['compensate_amount'])
                    + $this->cents($entity['compensate_amount'])
                ),
                'amount_due' => $this->format(
                    $this->cents($student['amount_due'])
                    + $this->cents($entity['amount_due'])
                ),
                'excess_amount' => $this->format(
                    $this->cents($student['excess_amount'])
                    + $this->cents($entity['excess_amount'])
                ),
            ],
            'invariants' => [
                'never_cross_payer_funds' => true,
                'never_compensate_more_than_real_net_paid' => true,
                'never_compensate_more_than_target_obligation' => true,
                'excess_requires_explicit_resolution' => true,
            ],
        ];
    }

    private function forPayer(string $role, array $source, int $targetCents): array
    {
        $netPaid = $this->money($source['net_paid'] ?? '0.00', $role . '.net_paid');
        $compensate = min($netPaid, $targetCents);
        $due = max(0, $targetCents - $netPaid);
        $excess = max(0, $netPaid - $targetCents);

        return [
            'payer_role' => $role,
            'source_invoice_uuid' => $this->optionalString($source['invoice_uuid'] ?? null),
            'source_net_paid' => $this->format($netPaid),
            'target_obligation' => $this->format($targetCents),
            'compensate_amount' => $this->format($compensate),
            'amount_due' => $this->format($due),
            'excess_amount' => $this->format($excess),
            'compensation_required' => $compensate > 0,
            'excess_requires_decision' => $excess > 0,
            'resolution' => $this->resolution($netPaid, $targetCents),
        ];
    }

    private function resolution(int $netPaid, int $target): string
    {
        if ($netPaid === 0 && $target === 0) {
            return 'NO_FUNDS_NO_OBLIGATION';
        }
        if ($target === 0) {
            return 'EXCESS_TO_RESOLVE';
        }
        if ($netPaid === 0) {
            return 'AMOUNT_DUE';
        }
        if ($netPaid === $target) {
            return 'FULLY_COMPENSATED';
        }
        if ($netPaid < $target) {
            return 'PARTIALLY_COMPENSATED';
        }

        return 'COMPENSATED_WITH_EXCESS';
    }

    private function payerAction(array $plan, string $role): array
    {
        $actions = $plan['actions'] ?? null;
        if (!is_array($actions)) {
            throw SifException::conflict('USOC course change plan has no payer actions');
        }

        foreach ($actions as $action) {
            if (is_array($action) && (string) ($action['payer_role'] ?? '') === $role) {
                return $action;
            }
        }

        throw SifException::conflict('Missing USOC course change payer action: ' . $role);
    }

    private function money(mixed $value, string $field): int
    {
        if ($value === null) {
            throw SifException::validation('Missing USOC course change amount: ' . $field);
        }

        $text = str_replace(',', '.', trim((string) $value));
        if (preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $text) !== 1) {
            throw SifException::validation('Invalid USOC course change amount: ' . $field);
        }

        [$whole, $decimals] = array_pad(explode('.', $text, 2), 2, '');
        $decimals = str_pad($decimals, 2, '0');

        return ((int) $whole * 100) + (int) substr($decimals, 0, 2);
    }

    private function cents(string $value): int
    {
        return $this->money($value, 'stored_value');
    }

    private function format(int $cents): string
    {
        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
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
