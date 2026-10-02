<?php

namespace Prisma\Sif\Domain;

/**
 * Canonical compatibility policy for the executable public-web Alumne PrisMa rule.
 *
 * V2 deliberately follows the SQL behaviour that is actually executable today:
 * a merely related but unpaid invoice does not grant Alumne PrisMa eligibility.
 * The caller is responsible for excluding the enrollment being priced and for
 * freezing the evaluation timestamp.
 */
final class PrismaStudentDiscountPolicy
{
    public const RULE_VERSION = 'ALUMNE_PRISMA_WEB_LEGACY_V2';

    public function evaluate(array $history): array
    {
        foreach ($history as $row) {
            if (!is_array($row)) {
                continue;
            }

            $status = strtoupper(trim((string) ($row['INSC_CURS'] ?? $row['INSC CURS'] ?? '')));
            if (in_array($status, ['D', 'M'], true)) {
                continue;
            }

            $amountDue = $this->number($row['A_PAGAR'] ?? null);
            $amountPaid = $this->number($row['PAGAMENT'] ?? null);
            $generated = $this->truthy($row['GENERAT'] ?? null);
            $observations = strtoupper((string) ($row['OBSERVACIONS'] ?? ''));

            $reason = null;
            if ($amountDue > 0.0 && $amountPaid > 0.0) {
                $reason = 'POSITIVE_PAYMENT';
            } elseif ($amountDue === 0.0 && str_contains($observations, 'CURS REGAL')) {
                $reason = 'GIFT_COURSE';
            } elseif ($generated) {
                $reason = 'GENERATED';
            }

            if ($reason !== null) {
                return [
                    'eligible' => true,
                    'discount_type' => 'ALUMNE_PRISMA',
                    'rule_version' => self::RULE_VERSION,
                    'reason' => $reason,
                    'evidence' => [
                        'source_type' => 'LEGACY_INSCRIPCIO',
                        'source_id' => isset($row['ID']) ? (int) $row['ID'] : null,
                    ],
                ];
            }
        }

        return [
            'eligible' => false,
            'discount_type' => 'ALUMNE_PRISMA',
            'rule_version' => self::RULE_VERSION,
            'reason' => 'NO_ELIGIBLE_HISTORY',
            'evidence' => null,
        ];
    }

    private function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function truthy(mixed $value): bool
    {
        if ($value === null || $value === '' || $value === false) {
            return false;
        }

        if (is_numeric($value)) {
            return (float) $value !== 0.0;
        }

        return true;
    }
}
