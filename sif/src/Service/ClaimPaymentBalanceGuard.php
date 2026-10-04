<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class ClaimPaymentBalanceGuard
{
    public function assertMayCharge(
        \PDO $db,
        string $uuidFactura,
        string $amount
    ): string {
        $requestedCents = $this->toPositiveCents($amount, 'Invalid claim payment amount');

        $invoiceStmt = $db->prepare(
            'SELECT TOTAL FROM factura WHERE UUID_FACTURA = ? FOR UPDATE'
        );
        $invoiceStmt->execute([$uuidFactura]);
        $total = $invoiceStmt->fetchColumn();
        if ($total === false) {
            throw SifException::validation('SIF invoice not found for claim payment');
        }

        $ledgerStmt = $db->prepare(
            "SELECT COALESCE(SUM(
                CASE
                    WHEN pt.TIPUS_MOVIMENT IN ('CHARGE', 'COMPENSATION') THEN pa.IMPORT_ASSIGNAT
                    WHEN pt.TIPUS_MOVIMENT = 'REFUND' THEN -pa.IMPORT_ASSIGNAT
                    ELSE 0
                END
            ), 0)
             FROM payment_allocation AS pa
             INNER JOIN payment_transaction AS pt
                ON pt.UUID_PAYMENT = pa.UUID_PAYMENT
             WHERE pa.UUID_FACTURA = ?"
        );
        $ledgerStmt->execute([$uuidFactura]);

        $outstandingCents = $this->toCents((string) $total)
            - $this->toCents((string) $ledgerStmt->fetchColumn());

        if ($outstandingCents <= 0) {
            throw SifException::conflict('Claim payment invoice has no outstanding balance');
        }

        if ($requestedCents > $outstandingCents) {
            throw SifException::conflict('Claim payment amount exceeds outstanding balance');
        }

        return $this->fromCents($outstandingCents);
    }

    private function toPositiveCents(string $amount, string $message): int
    {
        $cents = $this->toCents($amount);
        if ($cents <= 0) {
            throw SifException::validation($message);
        }

        return $cents;
    }

    private function toCents(string $amount): int
    {
        $normalized = str_replace(',', '.', trim($amount));
        if (preg_match('/^-?\d+(?:\.\d{1,2})?$/D', $normalized) !== 1) {
            throw SifException::validation('Invalid monetary amount');
        }

        $negative = str_starts_with($normalized, '-');
        if ($negative) {
            $normalized = substr($normalized, 1);
        }

        [$whole, $decimal] = array_pad(explode('.', $normalized, 2), 2, '0');
        $decimal = substr(str_pad($decimal, 2, '0'), 0, 2);
        $cents = ((int) $whole * 100) + (int) $decimal;

        return $negative ? -$cents : $cents;
    }

    private function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
