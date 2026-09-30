<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;

final class PackEnrollmentFundAllocationService
{
    public function __construct(private EnrollmentFundMovementRepository $movements)
    {
    }

    public function allocate(
        \PDO $db,
        string $dsOrder,
        array $snapshot,
        array $invoiceResult
    ): array {
        $uuidFactura = trim((string) ($invoiceResult['uuid_factura'] ?? ''));
        $uuidPayment = trim((string) ($invoiceResult['uuid_payment'] ?? ''));
        if ($uuidFactura === '' || $uuidPayment === '') {
            throw SifException::validation('Pack fund allocation requires invoice and payment');
        }

        $items = $snapshot['items'] ?? null;
        if (!is_array($items) || count($items) < 2) {
            throw SifException::validation('Pack fund allocation requires at least two items');
        }

        $expectedAmount = $snapshot['payment']['amount'] ?? null;
        if ($expectedAmount === null) {
            throw SifException::validation('Pack fund allocation requires payment amount');
        }
        $expectedCents = $this->cents($expectedAmount);
        if ($expectedCents <= 0) {
            throw SifException::validation('Invalid pack payment amount for allocation');
        }

        $normalizedItems = [];
        $seenOrdinals = [];
        $seenInscriptions = [];
        $sumCents = 0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                throw SifException::validation('Invalid pack allocation item');
            }

            $ordinal = $item['ordinal'] ?? null;
            if (!is_int($ordinal) && !(is_string($ordinal) && ctype_digit($ordinal))) {
                throw SifException::validation('Pack allocation item ordinal is required');
            }
            $ordinal = (int) $ordinal;
            if ($ordinal <= 0 || isset($seenOrdinals[$ordinal])) {
                throw SifException::validation('Pack allocation ordinal must be unique and positive');
            }
            $seenOrdinals[$ordinal] = true;

            $inscription = $item['inscription'] ?? null;
            if (!is_array($inscription)) {
                throw SifException::validation('Pack allocation item requires inscription');
            }

            $idInsc = $inscription['ID'] ?? null;
            if (!is_numeric($idInsc) || (int) $idInsc <= 0) {
                throw SifException::validation('Invalid pack allocation inscription ID');
            }
            $idInsc = (int) $idInsc;
            if (isset($seenInscriptions[$idInsc])) {
                throw SifException::conflict('Duplicate inscription in pack fund allocation');
            }
            $seenInscriptions[$idInsc] = true;

            $amount = $inscription['TOTAL']
                ?? $inscription['total']
                ?? $inscription['A_PAGAR']
                ?? $inscription['a_pagar']
                ?? null;
            if ($amount === null) {
                throw SifException::validation('Missing pack allocation line amount');
            }

            $amountCents = $this->cents($amount);
            if ($amountCents <= 0) {
                throw SifException::validation('Pack allocation line amount must be positive');
            }

            $sumCents += $amountCents;
            $normalizedItems[] = [
                'ordinal' => $ordinal,
                'id_insc' => $idInsc,
                'amount_cents' => $amountCents,
            ];
        }

        if ($sumCents !== $expectedCents) {
            throw SifException::conflict('Pack enrollment allocations do not match payment amount');
        }

        usort(
            $normalizedItems,
            static fn (array $left, array $right): int => $left['ordinal'] <=> $right['ordinal']
        );
        foreach ($normalizedItems as $index => $item) {
            if ($item['ordinal'] !== $index + 1) {
                throw SifException::validation('Pack allocation ordinals must be contiguous from 1');
            }
        }

        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }

        try {
            $payment = $this->movements->lockPayment($db, $uuidPayment);
            if ((string) $payment['TIPUS_MOVIMENT'] !== 'CHARGE'
                || (string) $payment['ESTAT'] !== 'CONFIRMED'
                || $this->cents($payment['IMPORT']) !== $expectedCents
            ) {
                throw SifException::conflict('Payment is not eligible for pack enrollment allocation');
            }

            $invoiceStmt = $db->prepare(
                'SELECT TOTAL FROM factura WHERE UUID_FACTURA = ? FOR UPDATE'
            );
            $invoiceStmt->execute([$uuidFactura]);
            $invoiceTotal = $invoiceStmt->fetchColumn();
            if ($invoiceTotal === false || $this->cents($invoiceTotal) !== $expectedCents) {
                throw SifException::conflict('Invoice total does not match pack enrollment allocation');
            }

            $results = [];
            foreach ($normalizedItems as $item) {
                $line = $this->movements->findInvoiceLineForInscription(
                    $db,
                    $uuidFactura,
                    $item['id_insc']
                );

                if ($this->cents($line['TOTAL']) !== $item['amount_cents']) {
                    throw SifException::conflict(
                        'Invoice line total does not match pack enrollment allocation'
                    );
                }

                $results[] = $this->movements->insertOrReuseExternalAllocation(
                    $db,
                    [
                        'idempotency_key' => sprintf(
                            'FUND|PACK|ORDER:%s|INSC:%d',
                            $dsOrder,
                            $item['id_insc']
                        ),
                        'order' => $item['ordinal'],
                        'uuid_payment' => $uuidPayment,
                        'uuid_factura' => $uuidFactura,
                        'invoice_line_id' => (int) $line['ID'],
                        'id_insc' => $item['id_insc'],
                        'amount' => $this->amount($item['amount_cents']),
                        'currency' => 'EUR',
                        'uuid_operation' => $snapshot['operation']['uuid_operation'] ?? null,
                        'correlation_id' => 'REDSYS|' . $dsOrder,
                        'notes' => 'UC-015 pack external payment allocation',
                    ]
                );
            }

            if ($ownsTransaction) {
                $db->commit();
            }

            return [
                'count' => count($results),
                'amount' => $this->amount($sumCents),
                'movements' => $results,
            ];
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    private function cents(mixed $value): int
    {
        $value = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $value)) {
            throw SifException::validation('Invalid decimal amount in pack allocation');
        }

        [$euros, $decimal] = array_pad(explode('.', $value, 2), 2, '');

        return (int) $euros * 100 + (int) str_pad($decimal, 2, '0');
    }

    private function amount(int $cents): string
    {
        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
