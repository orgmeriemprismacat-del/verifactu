<?php

declare(strict_types=1);

namespace Prisma\Sif\Domain;

/**
 * UC-111 commercial amount policy. Pure monetary arithmetic in integer
 * cents, no DB, tax calculation, payment transactions or fiscal effects.
 *
 * The caller must supply the amount AFTER the other ordinary discounts.
 * A single NOVICE balance remains in the same entitlement after partial use.
 */
final class NovicePromotionAmountPolicy
{
    public function allocate(
        string $available,
        string $ordinaryNetAfterOtherDiscounts,
        ?string $requestedAmount = null
    ): array {
        $availableCents = $this->cents($available);
        $ordinaryCents = $this->cents($ordinaryNetAfterOtherDiscounts);
        $requestedCents = $requestedAmount === null ? null : $this->cents($requestedAmount);

        if ($availableCents <= 0 || $ordinaryCents <= 0
            || ($requestedCents !== null && $requestedCents <= 0)
        ) {
            throw new \InvalidArgumentException('Promotional balance, price and chosen amount must be positive.');
        }

        $applied = $requestedCents ?? min($availableCents, $ordinaryCents);
        if ($applied > $availableCents || $applied > $ordinaryCents) {
            throw new \InvalidArgumentException('Promotional amount exceeds available value or ordinary net price.');
        }

        return [
            'applied_amount' => $this->money($applied),
            'remaining_promotion' => $this->money($availableCents - $applied),
            'destination_net_after_promotion' => $this->money($ordinaryCents - $applied),
        ];
    }

    public function cents(string $amount): int
    {
        if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($amount), $match)) {
            throw new \InvalidArgumentException('Monetary amount must have at most two decimal places.');
        }
        return (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
