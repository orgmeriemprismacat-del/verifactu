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

    public function findByEnrollmentId(
        \PDO $db,
        int $idInsc,
        bool $forUpdate = false
    ): ?array {
        if ($idInsc <= 0) {
            throw SifException::validation('Invalid debt claim enrollment id');
        }

        $sql = "SELECT DISTINCT f.UUID_FACTURA
                FROM factura_linia fl
                JOIN factura f ON f.UUID_FACTURA = fl.UUID_FACTURA
                WHERE fl.SOURCE_TYPE = 'INSCRIPCIO'
                  AND fl.SOURCE_ID = ?
                  AND f.ESTAT_FACTURA = 'ISSUED'
                ORDER BY f.DATA_EMISSIO DESC, f.ID DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$idInsc]);
        $uuids = array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $stmt->fetchAll(\PDO::FETCH_COLUMN)
        )));

        if ($uuids === []) {
            return null;
        }

        $snapshots = [];
        $outstanding = [];
        foreach ($uuids as $uuid) {
            $snapshot = $this->findByUuid($db, $uuid, $forUpdate);
            if ($snapshot === null) {
                continue;
            }
            $snapshots[] = $snapshot;
            if ($snapshot['is_outstanding']) {
                $outstanding[] = $snapshot;
            }
        }

        if (count($outstanding) === 1) {
            return $outstanding[0];
        }
        if (count($outstanding) > 1) {
            throw SifException::conflict(
                'Enrollment maps to multiple outstanding SIF invoices; explicit invoice is required'
            );
        }
        if (count($snapshots) === 1) {
            return $snapshots[0];
        }

        throw SifException::conflict(
            'Enrollment maps to multiple SIF invoices without a unique outstanding invoice'
        );
    }

    public function findConfirmedPaymentAllocation(
        \PDO $db,
        string $uuidPayment,
        string $uuidFactura
    ): ?array {
        $uuidPayment = trim($uuidPayment);
        $uuidFactura = trim($uuidFactura);
        if ($uuidPayment === '' || $uuidFactura === '') {
            throw SifException::validation('Missing payment allocation identity');
        }

        $stmt = $db->prepare(
            "SELECT pt.UUID_PAYMENT, pt.TIPUS_MOVIMENT, pt.IMPORT, pt.DATA_MOVIMENT,
                    COALESCE(SUM(pa.IMPORT_ASSIGNAT), 0) AS IMPORT_ASSIGNAT
             FROM payment_transaction pt
             JOIN payment_allocation pa ON pa.UUID_PAYMENT = pt.UUID_PAYMENT
             WHERE pt.UUID_PAYMENT = ?
               AND pa.UUID_FACTURA = ?
               AND pt.ESTAT = 'CONFIRMED'
             GROUP BY pt.UUID_PAYMENT, pt.TIPUS_MOVIMENT, pt.IMPORT, pt.DATA_MOVIMENT"
        );
        $stmt->execute([$uuidPayment, $uuidFactura]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? [
            'uuid_payment' => (string) $row['UUID_PAYMENT'],
            'movement_type' => (string) $row['TIPUS_MOVIMENT'],
            'amount' => (string) $row['IMPORT'],
            'allocated' => (string) $row['IMPORT_ASSIGNAT'],
            'movement_date' => (string) $row['DATA_MOVIMENT'],
        ] : null;
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
