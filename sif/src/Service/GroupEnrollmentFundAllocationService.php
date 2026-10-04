<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;

final class GroupEnrollmentFundAllocationService
{
    public function __construct(private EnrollmentFundMovementRepository $movements)
    {
    }

    public function allocate(\PDO $db, string $dsOrder, array $snapshot, array $invoiceResult): array
    {
        $uuidFactura = trim((string) ($invoiceResult['uuid_factura'] ?? ''));
        $uuidPayment = trim((string) ($invoiceResult['uuid_payment'] ?? ''));
        if ($uuidFactura === '' || $uuidPayment === '') {
            throw SifException::validation('Group fund allocation requires invoice and payment');
        }

        $items = $snapshot['items'] ?? null;
        if (!is_array($items) || $items === []) {
            throw SifException::validation('Group fund allocation requires participant items');
        }

        $expectedAmount = $snapshot['payment']['amount'] ?? null;
        if ($expectedAmount === null) {
            throw SifException::validation('Group fund allocation requires payment amount');
        }
        $expectedCents = $this->cents($expectedAmount);
        if ($expectedCents <= 0) {
            throw SifException::validation('Invalid group payment amount for allocation');
        }

        $normalized = [];
        $seen = [];
        $sumCents = 0;

        foreach ($items as $index => $item) {
            $inscription = is_array($item) ? ($item['inscription'] ?? null) : null;
            if (!is_array($inscription)) {
                throw SifException::validation('Group allocation item requires inscription');
            }

            $idInsc = $inscription['ID'] ?? $inscription['id'] ?? null;
            if (!is_numeric($idInsc) || (int) $idInsc <= 0) {
                throw SifException::validation('Invalid group allocation inscription ID');
            }
            $idInsc = (int) $idInsc;
            if (isset($seen[$idInsc])) {
                throw SifException::conflict('Duplicate inscription in group fund allocation');
            }
            $seen[$idInsc] = true;

            $amount = $inscription['TOTAL']
                ?? $inscription['total']
                ?? $inscription['A_PAGAR']
                ?? $inscription['a_pagar']
                ?? null;
            if ($amount === null) {
                throw SifException::validation('Missing group allocation line amount');
            }

            $amountCents = $this->cents($amount);
            if ($amountCents <= 0) {
                throw SifException::validation('Group allocation line amount must be positive');
            }

            $sumCents += $amountCents;
            $normalized[] = [
                'order' => $index + 1,
                'id_insc' => $idInsc,
                'amount_cents' => $amountCents,
            ];
        }

        if ($sumCents !== $expectedCents) {
            throw SifException::conflict('Group enrollment allocations do not match payment amount');
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
                throw SifException::conflict('Payment is not eligible for group enrollment allocation');
            }

            $invoiceStmt = $db->prepare('SELECT TOTAL FROM factura WHERE UUID_FACTURA = ? FOR UPDATE');
            $invoiceStmt->execute([$uuidFactura]);
            $invoiceTotal = $invoiceStmt->fetchColumn();
            if ($invoiceTotal === false || $this->cents($invoiceTotal) !== $expectedCents) {
                throw SifException::conflict('Invoice total does not match group enrollment allocation');
            }

            $results = [];
            foreach ($normalized as $item) {
                $line = $this->movements->findInvoiceLineForInscription(
                    $db,
                    $uuidFactura,
                    $item['id_insc']
                );

                if ($this->cents($line['TOTAL']) !== $item['amount_cents']) {
                    throw SifException::conflict('Invoice line total does not match group enrollment allocation');
                }

                $results[] = $this->movements->insertOrReuseExternalAllocation(
                    $db,
                    [
                        'idempotency_key' => sprintf(
                            'FUND|GRUP|ORDER:%s|INSC:%d',
                            $dsOrder,
                            $item['id_insc']
                        ),
                        'order' => $item['order'],
                        'uuid_payment' => $uuidPayment,
                        'uuid_factura' => $uuidFactura,
                        'invoice_line_id' => (int) $line['ID'],
                        'id_insc' => $item['id_insc'],
                        'amount' => $this->amount($item['amount_cents']),
                        'currency' => 'EUR',
                        'uuid_operation' => $snapshot['operation']['uuid_operation'] ?? null,
                        'correlation_id' => 'REDSYS|' . $dsOrder,
                        'notes' => 'UC-016 group external payment allocation',
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
            throw SifException::validation('Invalid decimal amount in group allocation');
        }
        [$euros, $decimal] = array_pad(explode('.', $value, 2), 2, '');
        return (int) $euros * 100 + (int) str_pad($decimal, 2, '0');
    }

    private function amount(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
