<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class CourseLegacyPaymentSyncService
{
    public function sync(
        \PDO $sifDb,
        \PDO $legacyDb,
        int $idpag,
        int $idInsc,
        string $uuidFactura,
        string $numVisible
    ): array {
        if ($idpag < 1 || $idInsc < 1) {
            throw SifException::validation('Invalid legacy course sync identity');
        }

        $stmt = $sifDb->prepare(
            "SELECT COALESCE(SUM(CASE
                WHEN TIPUS_MOVIMENT IN ('CHARGE','COMPENSATION') THEN IMPORT
                WHEN TIPUS_MOVIMENT = 'REFUND' THEN -IMPORT
                ELSE 0 END), 0)
             FROM payment_transaction
             WHERE IDPAG = ? AND ESTAT = 'CONFIRMED'"
        );
        $stmt->execute([$idpag]);
        $confirmedRaw = $stmt->fetchColumn();
        $confirmedCents = max(0, $this->signedCents($confirmedRaw, 'confirmed payment total'));
        $confirmed = $this->amount($confirmedCents);

        $legacy = $legacyDb->prepare(
            'SELECT A_PAGAR, PAGAMENT, FRACCIO, `INSC CURS`
             FROM inscripcions WHERE ID = ? AND IDPAG = ? LIMIT 1'
        );
        $legacy->execute([$idInsc, $idpag]);
        $row = $legacy->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw SifException::conflict('Legacy inscription not found during SIF payment sync');
        }

        $contractTotalCents = $this->signedCents($row['A_PAGAR'] ?? 0, 'legacy contract total');
        if ($contractTotalCents <= 0) {
            throw SifException::conflict('Legacy inscription has invalid contract total');
        }

        $projectedCents = min($contractTotalCents, $confirmedCents);
        $projected = $this->amount($projectedCents);
        $paid = $projectedCents >= $contractTotalCents;
        $timestamp = date('Y-m-d H:i:s');
        $noteToken = 'SIF_PAYMENT ' . $uuidFactura;

        $update = $legacyDb->prepare(
            "UPDATE inscripcions
             SET PAGAMENT = ?,
                 `DATA PAG` = CASE WHEN ? = 1 THEN COALESCE(`DATA PAG`, ?) ELSE `DATA PAG` END,
                 `INSC CURS` = CASE WHEN ? = 1 AND `INSC CURS` = 'M' THEN '1' ELSE `INSC CURS` END,
                 OBSERVACIONS = CASE
                    WHEN LOCATE(?, COALESCE(OBSERVACIONS, '')) > 0 THEN OBSERVACIONS
                    ELSE CONCAT(COALESCE(OBSERVACIONS, ''), '\n', ?, ' ', ?, ' ', ?)
                 END
             WHERE ID = ? AND IDPAG = ?"
        );
        $update->execute([
            $projected,
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
            throw SifException::conflict('Legacy course sync updated more than one inscription');
        }

        return [
            'idpag' => $idpag,
            'id_insc' => $idInsc,
            'confirmed_amount' => $confirmed,
            'projected_payment' => $projected,
            'status' => $paid ? 'PAID' : 'PARTIALLY_PAID',
        ];
    }

    private function signedCents(mixed $value, string $label): int
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^-?\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw SifException::validation("Invalid {$label}");
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
