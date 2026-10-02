<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class UsocCourseChangeTargetResolver
{
    public function resolve(array $input): array
    {
        $standard = $this->money(
            $input['target_standard_course_amount'] ?? null,
            'target_standard_course_amount'
        );
        $studentCourse = $this->money(
            $input['target_student_course_amount'] ?? null,
            'target_student_course_amount'
        );
        $managementFee = $this->money(
            $input['management_fee'] ?? '0.00',
            'management_fee'
        );

        if ($studentCourse <= 0) {
            throw SifException::validation(
                'USOC target student amount must be positive until free-course policy is implemented'
            );
        }

        if ($studentCourse > $standard) {
            throw SifException::validation(
                'USOC target student amount cannot exceed standard target amount'
            );
        }

        $entityCourse = $standard - $studentCourse;
        $studentTotal = $studentCourse + $managementFee;
        $entityTotal = $entityCourse;
        $combined = $studentTotal + $entityTotal;
        $expectedCombined = $standard + $managementFee;

        if ($combined !== $expectedCombined) {
            throw SifException::conflict(
                'USOC course change target amounts do not reconcile'
            );
        }

        return [
            'ok' => true,
            'target_standard_course_amount' => $this->format($standard),
            'target_student_course_amount' => $this->format($studentCourse),
            'target_entity_course_amount' => $this->format($entityCourse),
            'management_fee' => $this->format($managementFee),
            'target_student_total' => $this->format($studentTotal),
            'target_entity_total' => $this->format($entityTotal),
            'target_combined_total' => $this->format($combined),
            'entity_invoice_required' => $entityTotal > 0,
            'invariants' => [
                'entity_is_standard_minus_student' => true,
                'management_fee_belongs_to_student' => true,
                'combined_equals_standard_plus_fee' => true,
                'never_cross_payer_funds' => true,
            ],
        ];
    }

    private function money(mixed $value, string $field): int
    {
        if ($value === null) {
            throw SifException::validation('Missing USOC course change field: ' . $field);
        }

        $text = str_replace(',', '.', trim((string) $value));
        if (preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $text) !== 1) {
            throw SifException::validation(
                'Invalid USOC course change monetary field: ' . $field
            );
        }

        [$whole, $decimals] = array_pad(explode('.', $text, 2), 2, '');
        $decimals = str_pad($decimals, 2, '0');

        return ((int) $whole * 100) + (int) substr($decimals, 0, 2);
    }

    private function format(int $cents): string
    {
        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
