<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;

final class ExistingInvoiceEnrollmentFundAllocationService
{
    public function __construct(
        private EnrollmentFundMovementRepository $movements
    ) {
    }

    public function allocate(
        \PDO $db,
        string $uuidPayment,
        string $uuidFactura,
        string $correlationId
    ): array {
        $uuidPayment = trim($uuidPayment);
        $uuidFactura = trim($uuidFactura);
        $correlationId = trim($correlationId);

        if ($uuidPayment === '' || $uuidFactura === '' || $correlationId === '') {
            throw SifException::validation(
                'Existing invoice fund allocation requires payment, invoice and correlation'
            );
        }
        if (!$db->inTransaction()) {
            throw new \LogicException(
                'Existing invoice fund allocation requires an active transaction'
            );
        }

        $payment = $this->movements->lockPayment($db, $uuidPayment);
        if ((string) $payment['TIPUS_MOVIMENT'] !== 'CHARGE'
            || (string) $payment['ESTAT'] !== 'CONFIRMED'
        ) {
            throw SifException::conflict(
                'Existing invoice fund allocation requires a confirmed charge'
            );
        }

        $allocationStmt = $db->prepare(
            'SELECT IMPORT_ASSIGNAT
             FROM payment_allocation
             WHERE UUID_PAYMENT = ?
               AND UUID_FACTURA = ?
             FOR UPDATE'
        );
        $allocationStmt->execute([$uuidPayment, $uuidFactura]);
        $allocationRows = $allocationStmt->fetchAll(\PDO::FETCH_COLUMN);
        if (count($allocationRows) !== 1) {
            throw SifException::conflict(
                'Expected exactly one invoice allocation for UC-002 payment'
            );
        }

        $remaining = $this->cents($allocationRows[0]);
        if ($remaining <= 0) {
            throw SifException::conflict('UC-002 payment allocation is not positive');
        }

        $lineStmt = $db->prepare(
            "SELECT ID, ORDRE, SOURCE_ID, TOTAL
             FROM factura_linia
             WHERE UUID_FACTURA = ?
               AND SOURCE_TYPE = 'INSCRIPCIO'
               AND SOURCE_ID IS NOT NULL
             ORDER BY ORDRE, ID
             FOR UPDATE"
        );
        $lineStmt->execute([$uuidFactura]);
        $lines = $lineStmt->fetchAll(\PDO::FETCH_ASSOC);
        if ($lines === []) {
            throw SifException::conflict(
                'Existing invoice has no inscription lines for UC-002 allocation'
            );
        }

        $created = [];
        $order = 0;
        foreach ($lines as $line) {
            if ($remaining <= 0) {
                break;
            }

            $lineId = (int) ($line['ID'] ?? 0);
            $idInsc = (int) ($line['SOURCE_ID'] ?? 0);
            $lineTotal = $this->cents($line['TOTAL'] ?? null);
            if ($lineId <= 0 || $idInsc <= 0 || $lineTotal < 0) {
                throw SifException::conflict(
                    'Invalid invoice line for UC-002 enrollment allocation'
                );
            }

            $usedStmt = $db->prepare(
                "SELECT COALESCE(SUM(IMPORT), 0)
                 FROM enrollment_fund_movement
                 WHERE UUID_FACTURA = ?
                   AND ID_FACTURA_LINIA = ?
                   AND MOVEMENT_TYPE = 'EXTERNAL_ALLOCATION'
                   AND UUID_PAYMENT <> ?"
            );
            $usedStmt->execute([$uuidFactura, $lineId, $uuidPayment]);
            $alreadyAllocated = $this->cents($usedStmt->fetchColumn());
            $capacity = max(0, $lineTotal - $alreadyAllocated);
            if ($capacity === 0) {
                continue;
            }

            $amount = min($capacity, $remaining);
            $order++;

            $created[] = $this->movements->insertOrReuseExternalAllocation(
                $db,
                [
                    'idempotency_key' => sprintf(
                        'FUND|UC002|PAYMENT:%s|LINE:%d',
                        $uuidPayment,
                        $lineId
                    ),
                    'order' => $order,
                    'uuid_payment' => $uuidPayment,
                    'uuid_factura' => $uuidFactura,
                    'invoice_line_id' => $lineId,
                    'id_insc' => $idInsc,
                    'amount' => $this->amount($amount),
                    'currency' => 'EUR',
                    'correlation_id' => $correlationId,
                    'notes' => 'UC-002 existing invoice payment allocation',
                ]
            );

            $remaining -= $amount;
        }

        if ($remaining !== 0) {
            throw SifException::conflict(
                'UC-002 payment could not be fully attributed to invoice inscriptions'
            );
        }

        return [
            'count' => count($created),
            'amount' => $this->amount(array_sum(array_map(
                fn (array $movement): int => $this->cents($movement['amount']),
                $created
            ))),
            'movements' => $created,
        ];
    }

    private function cents(mixed $value): int
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw SifException::validation('Invalid UC-002 enrollment fund amount');
        }

        [$euros, $decimals] = array_pad(explode('.', $raw, 2), 2, '');

        return (int) $euros * 100 + (int) str_pad($decimals, 2, '0');
    }

    private function amount(int $cents): string
    {
        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
