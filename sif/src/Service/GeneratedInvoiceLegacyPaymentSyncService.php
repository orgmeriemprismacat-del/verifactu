<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class GeneratedInvoiceLegacyPaymentSyncService
{
    public function sync(
        \PDO $sifDb,
        \PDO $legacyDb,
        string $uuidFactura,
        string $numVisible,
        string $movementDate,
        string $method
    ): array {
        $uuidFactura = trim($uuidFactura);
        $numVisible = trim($numVisible);
        if ($uuidFactura === '' || $numVisible === '') {
            throw SifException::validation('Missing invoice identity for legacy payment sync');
        }

        $confirmedCents = max(0, $this->signedCents(
            $this->confirmedAmount($sifDb, $uuidFactura),
            'confirmed invoice payment total'
        ));

        $legacyDb->beginTransaction();

        try {
            $invoice = $this->legacyInvoice($legacyDb, $numVisible);
            $facturaRelacionada = (int) ($invoice['factura_relacionada'] ?? 0);
            if ($facturaRelacionada < 1) {
                throw SifException::conflict('Legacy invoice has no related invoice identity');
            }

            $members = $this->legacyMembers($legacyDb, $facturaRelacionada);
            if ($members === []) {
                throw SifException::conflict('Legacy invoice has no active inscriptions to synchronize');
            }

            $contractCents = 0;
            foreach ($members as $member) {
                $contractCents += max(0, $this->signedCents(
                    $member['A_PAGAR'] ?? 0,
                    'legacy inscription contract total'
                ));
            }
            if ($contractCents <= 0) {
                throw SifException::conflict('Legacy invoice has invalid contract total');
            }

            $projectedCents = min($confirmedCents, $contractCents);
            $remaining = $projectedCents;
            $projectedMembers = [];

            foreach ($members as $member) {
                $id = (int) ($member['ID'] ?? 0);
                $memberTotal = max(0, $this->signedCents(
                    $member['A_PAGAR'] ?? 0,
                    'legacy inscription contract total'
                ));
                if ($id < 1 || $memberTotal <= 0) {
                    throw SifException::conflict('Legacy inscription has invalid payment identity');
                }

                $memberPayment = min($memberTotal, $remaining);
                $remaining -= $memberPayment;
                $paid = $memberPayment >= $memberTotal;
                $partial = $memberPayment > 0 && !$paid;

                $update = $legacyDb->prepare(
                    "UPDATE inscripcions
                     SET PAGAMENT = ?,
                         `DATA PAG` = CASE WHEN ? = 1 THEN ? ELSE NULL END,
                         FRACCIONAT = CASE WHEN ? = 1 THEN 1 ELSE FRACCIONAT END
                     WHERE ID = ? AND FACTURA_RELACIONADA = ?"
                );
                $update->execute([
                    $this->amount($memberPayment),
                    $paid ? 1 : 0,
                    $paid ? $movementDate : null,
                    $partial ? 1 : 0,
                    $id,
                    $facturaRelacionada,
                ]);

                if ($update->rowCount() > 1) {
                    throw SifException::conflict('Legacy payment sync updated more than one inscription row');
                }

                $projectedMembers[] = [
                    'id' => $id,
                    'payment' => $this->amount($memberPayment),
                    'status' => $paid ? 'PAID' : ($partial ? 'PARTIALLY_PAID' : 'UNPAID'),
                ];
            }

            $updateInvoice = $legacyDb->prepare(
                'UPDATE factures
                 SET data_pagament = ?, FORMA_PAGAMENT = ?
                 WHERE num = ?'
            );
            $updateInvoice->execute([
                $movementDate,
                strtoupper(trim($method)),
                $numVisible,
            ]);

            if ($updateInvoice->rowCount() > 1) {
                throw SifException::conflict('Legacy payment sync updated more than one invoice row');
            }

            $legacyDb->commit();

            return [
                'num_visible' => $numVisible,
                'factura_relacionada' => $facturaRelacionada,
                'confirmed_amount' => $this->amount($confirmedCents),
                'projected_amount' => $this->amount($projectedCents),
                'status' => $projectedCents >= $contractCents ? 'PAID' : 'PARTIALLY_PAID',
                'members' => $projectedMembers,
            ];
        } catch (\Throwable $exception) {
            if ($legacyDb->inTransaction()) {
                $legacyDb->rollBack();
            }

            throw $exception;
        }
    }

    private function confirmedAmount(\PDO $sifDb, string $uuidFactura): mixed
    {
        $stmt = $sifDb->prepare(
            "SELECT COALESCE(SUM(CASE
                WHEN pt.TIPUS_MOVIMENT IN ('CHARGE','COMPENSATION') THEN pa.IMPORT_ASSIGNAT
                WHEN pt.TIPUS_MOVIMENT = 'REFUND' THEN -pa.IMPORT_ASSIGNAT
                ELSE 0 END), 0)
             FROM payment_allocation pa
             INNER JOIN payment_transaction pt ON pt.UUID_PAYMENT = pa.UUID_PAYMENT
             WHERE pa.UUID_FACTURA = ? AND pt.ESTAT = 'CONFIRMED'"
        );
        $stmt->execute([$uuidFactura]);

        return $stmt->fetchColumn();
    }

    private function legacyInvoice(\PDO $legacyDb, string $numVisible): array
    {
        $stmt = $legacyDb->prepare(
            'SELECT factura_relacionada FROM factures WHERE num = ? LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$numVisible]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw SifException::conflict('Legacy invoice not found during payment synchronization');
        }

        return $row;
    }

    private function legacyMembers(\PDO $legacyDb, int $facturaRelacionada): array
    {
        $stmt = $legacyDb->prepare(
            "SELECT ID, A_PAGAR
             FROM inscripcions
             WHERE FACTURA_RELACIONADA = ?
               AND A_PAGAR > 0
               AND (`INSC CURS` = '0' OR `INSC CURS` = '1' OR `INSC CURS` = 'M')
             ORDER BY ID
             FOR UPDATE"
        );
        $stmt->execute([$facturaRelacionada]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
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
