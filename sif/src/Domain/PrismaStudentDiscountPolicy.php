<?php

namespace Prisma\Sif\Domain;

/**
 * Compatibility policy that reproduces the current public web eligibility rule
 * without turning unresolved business questions into a new rule.
 *
 * RULE_VERSION must change when PrisMa ratifies or changes the meaning of
 * partial payment, GENERAT, invoice-before-payment or excluded statuses.
 */
final class PrismaStudentDiscountPolicy
{
    public const RULE_VERSION = 'ALUMNE_PRISMA_LEGACY_V1';

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
            $idpag = $this->nullableInt($row['IDPAG'] ?? null);
            $relatedInvoice = $row['FACTURA_RELACIONADA'] ?? null;
            $observations = strtoupper((string) ($row['OBSERVACIONS'] ?? ''));

            $reason = null;
            if ($amountDue > 0.0 && $amountPaid > 0.0) {
                $reason = 'POSITIVE_PAYMENT';
            } elseif ($amountDue === 0.0 && str_contains($observations, 'CURS REGAL')) {
                $reason = 'GIFT_COURSE';
            } elseif ($generated) {
                $reason = 'GENERATED';
            } elseif (
                $amountDue > 0.0
                && $amountPaid === 0.0
                && ($idpag === null || $idpag === 0)
                && $relatedInvoice !== null
                && $relatedInvoice !== ''
            ) {
                // This implements the intended non-null semantics of the legacy SQL branch.
                // Whether the criterion remains valid is still a business decision.
                $reason = 'INVOICED_BEFORE_PAYMENT';
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

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
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
