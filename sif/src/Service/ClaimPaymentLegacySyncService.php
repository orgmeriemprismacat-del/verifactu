<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class ClaimPaymentLegacySyncService
{
    public function assertBaselineSynchronized(
        \PDO $sifDb,
        \PDO $legacyDb,
        int $idInsc,
        int $idpag,
        string $uuidFactura
    ): array {
        $legacy = $this->legacyRow($legacyDb, $idInsc, $idpag);
        $legacyCents = $this->centsOrZero($legacy['PAGAMENT'] ?? null, 'legacy payment');
        $sifCents = $this->invoiceNetCents($sifDb, $uuidFactura);

        if ($legacyCents !== $sifCents) {
            throw SifException::conflict(
                'Legacy payment baseline does not match SIF invoice ledger'
            );
        }

        return [
            'legacy_payment' => $this->amount($legacyCents),
            'sif_payment' => $this->amount($sifCents),
            'synchronized' => true,
        ];
    }

    public function syncAfterSifSuccess(
        \PDO $sifDb,
        \PDO $legacyDb,
        int $idInsc,
        int $idpag,
        string $uuidFactura,
        string $numVisible,
        string $expectedReceiptAmount
    ): array {
        $legacy = $this->legacyRow($legacyDb, $idInsc, $idpag);
        $legacyCents = $this->centsOrZero($legacy['PAGAMENT'] ?? null, 'legacy payment');
        $contractCents = $this->cents($legacy['A_PAGAR'] ?? 0, 'legacy contract total');
        $sifCents = $this->invoiceNetCents($sifDb, $uuidFactura);
        $receiptCents = $this->positiveCents(
            $expectedReceiptAmount,
            'claim payment receipt amount'
        );

        if ($contractCents <= 0) {
            throw SifException::conflict('Legacy inscription has invalid contract total');
        }
        if ($sifCents > $contractCents) {
            throw SifException::conflict('SIF invoice ledger exceeds legacy contract total');
        }
        if ($legacyCents > $sifCents) {
            throw SifException::conflict('Legacy payment is ahead of SIF invoice ledger');
        }

        $delta = $sifCents - $legacyCents;
        if ($delta !== 0 && $delta !== $receiptCents) {
            throw SifException::conflict(
                'Legacy payment delta does not match the reconciled external receipt'
            );
        }

        $paid = $sifCents >= $contractCents;
        $timestamp = date('Y-m-d H:i:s');
        $noteToken = 'SIF_CLAIM_PAYMENT ' . $uuidFactura;

        $update = $legacyDb->prepare(
            "UPDATE inscripcions
             SET PAGAMENT = ?,
                 `DATA PAG` = CASE
                     WHEN ? = 1 THEN COALESCE(NULLIF(`DATA PAG`, ''), ?)
                     ELSE `DATA PAG`
                 END,
                 `INSC CURS` = CASE
                     WHEN ? = 1 AND `INSC CURS` = 'M' THEN '1'
                     ELSE `INSC CURS`
                 END,
                 OBSERVACIONS = CASE
                    WHEN LOCATE(?, COALESCE(OBSERVACIONS, '')) > 0 THEN OBSERVACIONS
                    ELSE CONCAT(
                        COALESCE(OBSERVACIONS, ''),
                        '\n',
                        ?,
                        ' ',
                        ?,
                        ' ',
                        ?
                    )
                 END
             WHERE ID = ? AND IDPAG = ?"
        );
        $update->execute([
            $this->amount($sifCents),
            $paid ? 1 : 0,
            $timestamp,
            $paid ? 1 : 0,
            $noteToken,
            $noteToken,
            $numVisible,
            $paid ? 'PAID' : 'PARTIALLY_PAID',
            $idInsc,
            $idpag,
        ]);

        if ($update->rowCount() > 1) {
            throw SifException::conflict(
                'Claim payment legacy sync updated more than one inscription'
            );
        }

        return [
            'idpag' => $idpag,
            'id_insc' => $idInsc,
            'previous_legacy_payment' => $this->amount($legacyCents),
            'projected_payment' => $this->amount($sifCents),
            'receipt_amount' => $this->amount($receiptCents),
            'status' => $paid ? 'PAID' : 'PARTIALLY_PAID',
            'already_synchronized' => $delta === 0,
        ];
    }

    private function legacyRow(\PDO $legacyDb, int $idInsc, int $idpag): array
    {
        if ($idInsc <= 0 || $idpag <= 0) {
            throw SifException::validation('Invalid claim payment legacy identity');
        }

        $stmt = $legacyDb->prepare(
            'SELECT A_PAGAR, PAGAMENT, `INSC CURS`
             FROM inscripcions
             WHERE ID = ? AND IDPAG = ?
             LIMIT 1'
        );
        $stmt->execute([$idInsc, $idpag]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw SifException::conflict(
                'Legacy inscription not found during claim payment sync'
            );
        }

        return $row;
    }

    private function invoiceNetCents(\PDO $sifDb, string $uuidFactura): int
    {
        $stmt = $sifDb->prepare(
            "SELECT COALESCE(SUM(
                CASE
                    WHEN pt.TIPUS_MOVIMENT IN ('CHARGE', 'COMPENSATION')
                        THEN pa.IMPORT_ASSIGNAT
                    WHEN pt.TIPUS_MOVIMENT = 'REFUND'
                        THEN -pa.IMPORT_ASSIGNAT
                    ELSE 0
                END
            ), 0)
             FROM payment_allocation AS pa
             INNER JOIN payment_transaction AS pt
                ON pt.UUID_PAYMENT = pa.UUID_PAYMENT
             WHERE pa.UUID_FACTURA = ?
               AND pt.ESTAT = 'CONFIRMED'"
        );
        $stmt->execute([$uuidFactura]);

        return max(0, $this->cents($stmt->fetchColumn(), 'SIF invoice payment total'));
    }

    private function centsOrZero(mixed $value, string $label): int
    {
        if ($value === null || trim((string) $value) === '') {
            return 0;
        }

        return $this->cents($value, $label);
    }

    private function positiveCents(mixed $value, string $label): int
    {
        $cents = $this->cents($value, $label);
        if ($cents <= 0) {
            throw SifException::validation('Invalid ' . $label);
        }

        return $cents;
    }

    private function cents(mixed $value, string $label): int
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^-?\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw SifException::validation('Invalid ' . $label);
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
        if ($cents < 0) {
            return '-' . $this->amount(-$cents);
        }

        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
