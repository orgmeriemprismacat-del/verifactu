<?php

declare(strict_types=1);

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class DebtSnapshotRepository
{
    public function findByUuid(\PDO $db, string $uuidFactura, bool $forUpdate = false): ?array
    {
        $uuidFactura = trim($uuidFactura);
        if ($uuidFactura === '') {
            throw SifException::validation('Missing debt snapshot invoice UUID');
        }

        $sql = 'SELECT UUID_FACTURA, NUM_VISIBLE, TOTAL, ESTAT_COBRAMENT, ESTAT_FACTURA,
                       BILLING_NOM_RAO, BILLING_NIF_CIF, BILLING_EMAIL, SOURCE_CHANNEL
                FROM factura WHERE UUID_FACTURA = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$uuidFactura]);
        $invoice = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($invoice)) {
            return null;
        }

        return $this->snapshot($db, $invoice);
    }

    public function findByNumVisible(\PDO $db, string $numVisible, bool $forUpdate = false): ?array
    {
        $numVisible = trim($numVisible);
        if ($numVisible === '') {
            throw SifException::validation('Missing debt snapshot invoice number');
        }

        $sql = 'SELECT UUID_FACTURA, NUM_VISIBLE, TOTAL, ESTAT_COBRAMENT, ESTAT_FACTURA,
                       BILLING_NOM_RAO, BILLING_NIF_CIF, BILLING_EMAIL, SOURCE_CHANNEL
                FROM factura WHERE NUM_VISIBLE = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$numVisible]);
        $invoice = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($invoice)) {
            return null;
        }

        return $this->snapshot($db, $invoice);
    }

    private function snapshot(\PDO $db, array $invoice): array
    {
        $uuidFactura = (string) $invoice['UUID_FACTURA'];
        $charges = $this->sumAllocations($db, $uuidFactura, ['CHARGE', 'COMPENSATION']);
        $refunds = $this->sumAllocations($db, $uuidFactura, ['REFUND']);

        $totalCents = $this->cents((string) $invoice['TOTAL']);
        $chargeCents = $this->cents($charges);
        $refundCents = $this->cents($refunds);
        $outstandingCents = max(0, $totalCents - $chargeCents + $refundCents);

        return [
            'uuid_factura' => $uuidFactura,
            'num_visible' => (string) $invoice['NUM_VISIBLE'],
            'total' => $this->amount($totalCents),
            'charged' => $this->amount($chargeCents),
            'refunded' => $this->amount($refundCents),
            'outstanding' => $this->amount($outstandingCents),
            'is_outstanding' => $outstandingCents > 0,
            'payment_status' => (string) $invoice['ESTAT_COBRAMENT'],
            'invoice_status' => (string) $invoice['ESTAT_FACTURA'],
            'billing_name' => trim((string) $invoice['BILLING_NOM_RAO']),
            'billing_tax_id' => trim((string) $invoice['BILLING_NIF_CIF']),
            'billing_email' => strtolower(trim((string) ($invoice['BILLING_EMAIL'] ?? ''))),
            'source_channel' => (string) $invoice['SOURCE_CHANNEL'],
        ];
    }

    private function sumAllocations(\PDO $db, string $uuidFactura, array $movementTypes): string
    {
        $marks = implode(', ', array_fill(0, count($movementTypes), '?'));
        $stmt = $db->prepare(
            "SELECT COALESCE(SUM(pa.IMPORT_ASSIGNAT), 0)
             FROM payment_allocation pa
             JOIN payment_transaction pt ON pt.UUID_PAYMENT = pa.UUID_PAYMENT
             WHERE pa.UUID_FACTURA = ?
               AND pt.ESTAT = 'CONFIRMED'
               AND pt.TIPUS_MOVIMENT IN ({$marks})"
        );
        $stmt->execute(array_merge([$uuidFactura], $movementTypes));

        return $this->amount($this->cents((string) $stmt->fetchColumn()));
    }

    private function cents(string $value): int
    {
        $raw = trim(str_replace(',', '.', $value));
        if (!preg_match('/^-?\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw SifException::validation('Invalid monetary value in debt snapshot');
        }

        $negative = str_starts_with($raw, '-');
        if ($negative) {
            $raw = substr($raw, 1);
        }
        [$euros, $decimals] = array_pad(explode('.', $raw, 2), 2, '');
        $cents = (int) $euros * 100 + (int) str_pad($decimals, 2, '0');

        return $negative ? -$cents : $cents;
    }

    private function amount(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);

        return ($negative ? '-' : '')
            . intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
