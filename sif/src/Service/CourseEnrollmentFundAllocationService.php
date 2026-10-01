<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;

final class CourseEnrollmentFundAllocationService
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
        $dsOrder = trim($dsOrder);
        if ($dsOrder === '') {
            throw SifException::validation('Course fund allocation requires DS_ORDER');
        }

        $uuidFactura = trim((string) ($invoiceResult['uuid_factura'] ?? ''));
        $uuidPayment = trim((string) ($invoiceResult['uuid_payment'] ?? ''));
        if ($uuidFactura === '' || $uuidPayment === '') {
            throw SifException::validation('Course fund allocation requires invoice and payment');
        }

        $inscription = $snapshot['inscription'] ?? null;
        if (!is_array($inscription)) {
            throw SifException::validation('Course fund allocation requires inscription snapshot');
        }

        $idInsc = $inscription['ID'] ?? $inscription['id'] ?? null;
        if (!is_numeric($idInsc) || (int) $idInsc <= 0) {
            throw SifException::validation('Invalid course fund allocation inscription ID');
        }
        $idInsc = (int) $idInsc;

        $expectedAmount = $snapshot['payment']['amount'] ?? null;
        if ($expectedAmount === null) {
            throw SifException::validation('Course fund allocation requires payment amount');
        }
        $expectedCents = $this->cents($expectedAmount);
        if ($expectedCents <= 0) {
            throw SifException::validation('Invalid course payment amount for allocation');
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
                throw SifException::conflict('Payment is not eligible for course enrollment allocation');
            }

            $invoiceStmt = $db->prepare(
                'SELECT TOTAL FROM factura WHERE UUID_FACTURA = ? FOR UPDATE'
            );
            $invoiceStmt->execute([$uuidFactura]);
            $invoiceTotal = $invoiceStmt->fetchColumn();
            if ($invoiceTotal === false || $this->cents($invoiceTotal) !== $expectedCents) {
                throw SifException::conflict(
                    'Invoice total does not match course enrollment allocation'
                );
            }

            $line = $this->movements->findInvoiceLineForInscription(
                $db,
                $uuidFactura,
                $idInsc
            );
            if ($this->cents($line['TOTAL']) !== $expectedCents) {
                throw SifException::conflict(
                    'Invoice line total does not match course enrollment allocation'
                );
            }

            $movement = $this->movements->insertOrReuseExternalAllocation(
                $db,
                [
                    'idempotency_key' => sprintf(
                        'FUND|CURS|ORDER:%s|INSC:%d',
                        $dsOrder,
                        $idInsc
                    ),
                    'order' => 1,
                    'uuid_payment' => $uuidPayment,
                    'uuid_factura' => $uuidFactura,
                    'invoice_line_id' => (int) $line['ID'],
                    'id_insc' => $idInsc,
                    'amount' => $this->amount($expectedCents),
                    'currency' => 'EUR',
                    'uuid_operation' => $snapshot['operation']['uuid_operation'] ?? null,
                    'correlation_id' => 'REDSYS|' . $dsOrder,
                    'notes' => 'UC-014 course external payment allocation',
                ]
            );

            if ($ownsTransaction) {
                $db->commit();
            }

            return [
                'count' => 1,
                'amount' => $this->amount($expectedCents),
                'movements' => [$movement],
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
            throw SifException::validation('Invalid decimal amount in course allocation');
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
