<?php

require_once __DIR__ . '/LegacyUsocCourseChangePricingSourceInterface.php';

final class LegacyUsocCourseChangePricingResolver
{
    public function __construct(
        private LegacyUsocCourseChangePricingSourceInterface $source
    ) {
    }

    public function resolve(
        int $idInsc,
        string $targetYear,
        string $targetMonth,
        string $targetCourse,
        int $changeNumber
    ): array {
        if ($idInsc <= 0) {
            throw new InvalidArgumentException('Invalid USOC course change enrollment id');
        }

        $targetYear = trim($targetYear);
        $targetMonth = trim($targetMonth);
        $targetCourse = trim($targetCourse);

        if (
            preg_match('/^20\d{2}$/D', $targetYear) !== 1
            || $targetMonth === ''
            || strlen($targetMonth) > 16
            || $targetCourse === ''
            || strlen($targetCourse) > 64
        ) {
            throw new InvalidArgumentException('Invalid USOC target edition');
        }

        if ($changeNumber < 0 || $changeNumber > 4) {
            throw new InvalidArgumentException('Invalid course change number');
        }

        $enrollment = $this->source->enrollment($idInsc);
        if (
            (int) ($enrollment['tipus_desc'] ?? 0) !== 4
            || (int) ($enrollment['valid_desc'] ?? 0) !== 1
        ) {
            throw new RuntimeException(
                'USOC source enrollment must be validated before course change',
                409
            );
        }

        $idpag = (int) ($enrollment['idpag'] ?? 0);
        if ($idpag <= 0) {
            throw new RuntimeException(
                'USOC source enrollment has no valid IDPAG',
                409
            );
        }

        $effectiveDate = trim((string) ($enrollment['data_insc'] ?? ''));
        if ($effectiveDate === '') {
            throw new RuntimeException(
                'USOC source enrollment has no pricing effective date',
                409
            );
        }

        $targetEdition = $this->source->edition(
            $targetYear,
            $targetMonth,
            $targetCourse
        );
        $standard = $this->money(
            $this->source->activeStandardPrice(
                (int) ($targetEdition['price_id'] ?? 0)
            ),
            'standard target price'
        );
        $student = $this->money(
            $this->source->activeUsocPrice(
                (int) ($targetEdition['price_id'] ?? 0),
                $targetCourse,
                $targetMonth,
                $effectiveDate
            ),
            'USOC target price'
        );

        if ($student <= 0 || $student >= $standard) {
            throw new RuntimeException(
                'USOC target pricing must preserve positive student and entity parts',
                409
            );
        }

        $sourceEdition = $this->source->edition(
            trim((string) ($enrollment['year'] ?? '')),
            trim((string) ($enrollment['month'] ?? '')),
            trim((string) ($enrollment['course'] ?? ''))
        );

        $managementFee = 0;
        if ($changeNumber === 4) {
            $managementFee = $this->money(
                $this->source->managementFee(
                    trim((string) ($sourceEdition['hours'] ?? ''))
                ),
                'management fee',
                true
            );
        }

        return [
            'ok' => true,
            'id_insc' => $idInsc,
            'idpag' => $idpag,
            'source' => [
                'year' => (string) $enrollment['year'],
                'month' => (string) $enrollment['month'],
                'course' => (string) $enrollment['course'],
                'hours' => (string) $sourceEdition['hours'],
                'pricing_effective_date' => $effectiveDate,
            ],
            'target' => [
                'year' => $targetYear,
                'month' => $targetMonth,
                'course' => $targetCourse,
                'kind' => (string) ($targetEdition['kind'] ?? ''),
                'title' => (string) ($targetEdition['title'] ?? ''),
                'price_id' => (int) ($targetEdition['price_id'] ?? 0),
                'target_standard_course_amount' => $this->format($standard),
                'target_student_course_amount' => $this->format($student),
                'management_fee' => $this->format($managementFee),
            ],
            'change_number' => $changeNumber,
            'pricing_source' => 'LEGACY_SERVER',
            'invariants' => [
                'validated_usoc_source' => true,
                'single_target_edition' => true,
                'single_standard_price' => true,
                'single_usoc_price' => true,
                'management_fee_uses_source_hours' => true,
            ],
        ];
    }

    private function money(
        mixed $value,
        string $label,
        bool $allowZero = false
    ): int {
        $text = str_replace(',', '.', trim((string) $value));
        if (preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $text) !== 1) {
            throw new RuntimeException('Invalid ' . $label, 409);
        }

        [$whole, $decimals] = array_pad(explode('.', $text, 2), 2, '');
        $cents = ((int) $whole * 100)
            + (int) substr(str_pad($decimals, 2, '0'), 0, 2);

        if ((!$allowZero && $cents <= 0) || ($allowZero && $cents < 0)) {
            throw new RuntimeException('Invalid ' . $label, 409);
        }

        return $cents;
    }

    private function format(int $cents): string
    {
        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
