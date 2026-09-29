<?php

declare(strict_types=1);

namespace Prisma\Sif\Domain;

/**
 * UC-111 commercial planning ONLY. All four monetary inputs are from a
 * trusted, approved destination cancellation/price policy, not the browser.
 *
 * Promo-derived value and real money paid stay separate. This class never
 * issues a fiscal rectificative, creates credit_balance, payment_transaction,
 * changes the original novice expiry or performs any refund.
 */
final class NovicePromotionDestinationAdjustmentPolicy
{
    public function planCancellation(
        string $originalPromotionalUse,
        string $originalConfirmedExternalCash,
        string $eligiblePromotionalAmount,
        string $eligibleCashAmount,
        \DateTimeImmutable $cancellationBalanceIssuedAt
    ): array {
        $originalPromo = $this->positive($originalPromotionalUse);
        $originalCash = $this->nonnegative($originalConfirmedExternalCash);
        $eligiblePromo = $this->nonnegative($eligiblePromotionalAmount);
        $eligibleCash = $this->nonnegative($eligibleCashAmount);

        if ($eligiblePromo > $originalPromo || $eligibleCash > $originalCash
            || $eligiblePromo + $eligibleCash <= 0
        ) {
            throw new \InvalidArgumentException('Cancellation value exceeds the documented promotional or cash provenance.');
        }

        $dateLocal = $cancellationBalanceIssuedAt->setTimezone(new \DateTimeZone('Europe/Madrid'));
        $issuedUtc = $dateLocal->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $expiresUtc = $dateLocal->modify('+1 year')
            ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        return [
            'promotional_derived_amount' => $this->money($eligiblePromo),
            'cash_refund_or_credit_eligible' => $this->money($eligibleCash),
            'total_eligible_value' => $this->money($eligiblePromo + $eligibleCash),
            'promotional_forfeited' => $this->money($originalPromo - $eligiblePromo),
            'derived_issued_at_utc' => $eligiblePromo > 0 ? $issuedUtc : null,
            'derived_expires_at_utc' => $eligiblePromo > 0 ? $expiresUtc : null,
            'creates_promotional_derived_right' => $eligiblePromo > 0,
            'requires_approved_rectificative' => true,
        ];
    }

    /**
     * A destination course CHANGE moves the same attribution; it does not
     * restore the original right nor issue a separate new grant.
     * If the new course is cheaper than the previously applied promotion,
     * accounting/fiscal treatment of the difference needs an approved path.
     */
    public function planCourseChange(
        string $promotionalUseToTransfer,
        string $newCourseOrdinaryNetAfterDiscounts
    ): array {
        $applied = $this->positive($promotionalUseToTransfer);
        $newNet = $this->positive($newCourseOrdinaryNetAfterDiscounts);
        if ($applied > $newNet) {
            throw new \InvalidArgumentException('A lower-priced destination requires separate adjustment before transferring promotion.');
        }
        return [
            'transfer_promotion' => $this->money($applied),
            'new_course_remaining_before_real_payments' => $this->money($newNet - $applied),
            'new_promotion_grant' => false,
            'restore_original_novice_balance' => false,
            'requires_fiscal_course_change' => true,
        ];
    }

    private function positive(string $amount): int
    {
        $cents = $this->nonnegative($amount);
        if ($cents <= 0) {
            throw new \InvalidArgumentException('Expected positive monetary amount.');
        }
        return $cents;
    }

    private function nonnegative(string $amount): int
    {
        if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($amount), $match)) {
            throw new \InvalidArgumentException('Expected valid amount with at most two decimals.');
        }
        return (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
