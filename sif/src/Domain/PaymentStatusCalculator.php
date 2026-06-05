<?php

namespace Prisma\Sif\Domain;

final class PaymentStatusCalculator
{
    public function calculate(string $invoiceTotal, string $charges, string $refunds): string
    {
        $totalCents = $this->toCents($invoiceTotal);
        $chargeCents = $this->toCents($charges);
        $refundCents = $this->toCents($refunds);
        $netCents = $chargeCents - $refundCents;

        if ($refundCents > 0 && $netCents <= 0) {
            return 'REFUNDED';
        }

        if ($refundCents > 0) {
            return 'PARTIALLY_REFUNDED';
        }

        if ($netCents === 0) {
            return 'PENDING';
        }

        if ($netCents < $totalCents) {
            return 'PARTIAL';
        }

        if ($netCents > $totalCents) {
            return 'OVERPAID';
        }

        return 'PAID';
    }

    private function toCents(string $amount): int
    {
        $normalized = str_replace(',', '.', trim($amount));
        $negative = str_starts_with($normalized, '-');

        if ($negative) {
            $normalized = substr($normalized, 1);
        }

        [$whole, $decimal] = array_pad(explode('.', $normalized, 2), 2, '0');
        $decimal = substr(str_pad($decimal, 2, '0'), 0, 2);
        $cents = ((int) $whole * 100) + (int) $decimal;

        return $negative ? -$cents : $cents;
    }
}
