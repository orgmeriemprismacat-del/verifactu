<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;

final class JointInvoiceEnrollmentFundAllocationService
{
    public function __construct(private EnrollmentFundMovementRepository $movements)
    {
    }

    /**
     * @param array<int|string, mixed> $participantAllocations ID_INSC => amount
     */
    public function allocate(
        \PDO $db,
        string $uuidPayment,
        string $uuidFactura,
        array $participantAllocations,
        string $correlationId
    ): array {
        $uuidPayment = trim($uuidPayment);
        $uuidFactura = trim($uuidFactura);
        $correlationId = trim($correlationId);

        if ($uuidPayment === '' || $uuidFactura === '' || $correlationId === '') {
            throw SifException::validation(
                'Joint invoice allocation requires payment, invoice and correlation ID'
            );
        }

        $normalized = $this->normalizeAllocations($participantAllocations);

        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }

        try {
            $payment = $this->movements->lockPayment($db, $uuidPayment);
            if ((string) $payment['TIPUS_MOVIMENT'] !== 'CHARGE'
                || (string) $payment['ESTAT'] !== 'CONFIRMED'
            ) {
                throw SifException::conflict(
                    'Joint invoice allocation requires a confirmed charge'
                );
            }

            $paymentCents = $this->cents($payment['IMPORT']);
            $requestedCents = array_sum($normalized);
            if ($requestedCents !== $paymentCents) {
                throw SifException::conflict(
                    'Participant allocations must equal the immutable payment amount'
                );
            }

            $allocatedToInvoice = $this->paymentAllocationToInvoice(
                $db,
                $uuidPayment,
                $uuidFactura
            );
            if ($allocatedToInvoice !== $paymentCents) {
                throw SifException::conflict(
                    'Payment allocation does not match the target joint invoice'
                );
            }

            $movements = [];
            $order = 1;
            foreach ($normalized as $idInsc => $amountCents) {
                $line = $this->movements->findInvoiceLineForInscription(
                    $db,
                    $uuidFactura,
                    (int) $idInsc
                );
                $lineTotalCents = $this->cents($line['TOTAL']);

                $idempotencyKey = sprintf(
                    'FUND|UC021|PAYMENT:%s|INSC:%d',
                    $uuidPayment,
                    (int) $idInsc
                );

                $existing = $this->movements->findByIdempotencyKey(
                    $db,
                    $idempotencyKey,
                    true
                );
                $existingCents = $existing === null
                    ? 0
                    : $this->cents($existing['IMPORT']);

                $alreadyAllocated = $this->allocatedToInvoiceLine(
                    $db,
                    (int) $line['ID']
                );
                $allocatedByOtherMovements = $alreadyAllocated - $existingCents;

                if ($allocatedByOtherMovements < 0
                    || $allocatedByOtherMovements + $amountCents > $lineTotalCents
                ) {
                    throw SifException::conflict(
                        'Participant allocation exceeds the invoice-line outstanding amount'
                    );
                }

                $movements[] = $this->movements->insertOrReuseExternalAllocation(
                    $db,
                    [
                        'idempotency_key' => $idempotencyKey,
                        'order' => $order++,
                        'uuid_payment' => $uuidPayment,
                        'uuid_factura' => $uuidFactura,
                        'invoice_line_id' => (int) $line['ID'],
                        'id_insc' => (int) $idInsc,
                        'amount' => $this->amount($amountCents),
                        'currency' => 'EUR',
                        'correlation_id' => $correlationId,
                        'notes' => 'UC-021 joint invoice participant allocation',
                    ]
                );
            }

            if ($ownsTransaction) {
                $db->commit();
            }

            return [
                'count' => count($movements),
                'amount' => $this->amount($requestedCents),
                'movements' => $movements,
            ];
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    private function normalizeAllocations(array $allocations): array
    {
        if ($allocations === []) {
            throw SifException::validation(
                'Joint invoice payment requires participant allocations'
            );
        }

        $normalized = [];
        foreach ($allocations as $rawId => $rawAmount) {
            $id = is_int($rawId) ? $rawId : (ctype_digit((string) $rawId) ? (int) $rawId : 0);
            if ($id < 1) {
                throw SifException::validation('Invalid participant allocation ID_INSC');
            }

            $cents = $this->cents($rawAmount);
            if ($cents <= 0) {
                throw SifException::validation('Participant allocation amount must be positive');
            }

            if (isset($normalized[$id])) {
                throw SifException::validation('Duplicate participant allocation ID_INSC');
            }
            $normalized[$id] = $cents;
        }

        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    private function paymentAllocationToInvoice(
        \PDO $db,
        string $uuidPayment,
        string $uuidFactura
    ): int {
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(IMPORT_ASSIGNAT), 0)
             FROM payment_allocation
             WHERE UUID_PAYMENT = ? AND UUID_FACTURA = ?
             FOR UPDATE'
        );
        $stmt->execute([$uuidPayment, $uuidFactura]);

        return $this->cents($stmt->fetchColumn());
    }

    private function allocatedToInvoiceLine(\PDO $db, int $lineId): int
    {
        $stmt = $db->prepare(
            "SELECT COALESCE(SUM(IMPORT), 0)
             FROM enrollment_fund_movement
             WHERE ID_FACTURA_LINIA = ?
               AND MOVEMENT_TYPE = 'EXTERNAL_ALLOCATION'
             FOR UPDATE"
        );
        $stmt->execute([$lineId]);

        return $this->cents($stmt->fetchColumn());
    }

    private function cents(mixed $value): int
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw SifException::validation('Invalid decimal amount in joint invoice allocation');
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
