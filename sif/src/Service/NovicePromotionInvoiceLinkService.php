<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

/**
 * Relates a committed JASOM invoice to a PREPARED novice enrollment operation.
 * The operation and secretary decision must already be staged by a trusted
 * registration/secretary workflow. An absent origin is NOT manufactured from
 * a payment callback; it is reported for later reconciliation.
 */
final class NovicePromotionInvoiceLinkService
{
    public function attach(\PDO $db, int $enrollmentId, string $uuidInvoice): array
    {
        if ($db->inTransaction()) {
            throw new \LogicException('Attach novice invoice only after invoice transaction commits.');
        }
        if ($enrollmentId < 1 || trim($uuidInvoice) === '') {
            throw SifException::validation('Enrollment and issued invoice are required.');
        }

        $db->beginTransaction();
        try {
            $relation = $this->one(
                $db,
                "SELECT ID FROM fact_rels
                 WHERE UUID_FACTURA = ? AND SOURCE_TYPE = 'INSCRIPCIO' AND SOURCE_ID = ? LIMIT 1",
                [$uuidInvoice, $enrollmentId]
            );
            if ($relation === null) {
                throw SifException::conflict('Issued invoice does not belong to the enrollment.');
            }

            $operations = $this->many(
                $db,
                "SELECT UUID_OPERATION, UUID_FACTURA, STATUS, NET_AMOUNT
                 FROM commercial_operation
                 WHERE SOURCE_TYPE = 'CURS' AND SOURCE_ID = ? AND PRODUCT_TYPE = 'CURS'
                   AND PRODUCT_CODE = 'JASOM' FOR UPDATE",
                [(string) $enrollmentId]
            );
            if ($operations === []) {
                $db->commit();
                return ['status' => 'NOT_STAGED', 'uuid_operation' => null, 'grant_eligible' => false];
            }
            if (count($operations) !== 1) {
                throw SifException::conflict('Enrollment has multiple SIF JASOM operations.');
            }

            $operation = $operations[0];
            if (!in_array($operation['STATUS'], ['READY_FOR_PAYMENT', 'PAYMENT_PENDING', 'PAID', 'INVOICED', 'COMPLETED'], true)) {
                throw SifException::conflict('JASOM was paid before the secretary decision opened payment.');
            }
            $alreadyLinked = trim((string) ($operation['UUID_FACTURA'] ?? ''));

            $validations = $this->many(
                $db,
                'SELECT STATUS FROM discount_validation
                 WHERE UUID_OPERATION = ? AND DISCOUNT_TYPE = ? FOR UPDATE',
                [(string) $operation['UUID_OPERATION'], NovicePromotionGrantService::VALIDATION_TYPE]
            );
            if (count($validations) !== 1
                || !in_array($validations[0]['STATUS'], ['VALIDATED', 'REJECTED'], true)
            ) {
                throw SifException::conflict('Secretary decision is not properly staged for JASOM.');
            }

            // An enrollment may be invoiced once and paid in installments,
            // or its legacy Redsys channel may issue one F1/F2 per payment.
            // DISTINCT is enforced by EXISTS: fact_rels may have many links
            // for an invoice, but its TOTAL must be counted only once.
            $invoices = $this->many(
                $db,
                "SELECT f.UUID_FACTURA, f.TOTAL, f.ESTAT_FACTURA, f.ESTAT_COBRAMENT
                 FROM factura f
                 WHERE f.TIPUS_FACTURA IN ('F1', 'F2')
                   AND EXISTS (
                       SELECT 1 FROM fact_rels rel
                       WHERE rel.UUID_FACTURA = f.UUID_FACTURA
                         AND rel.SOURCE_TYPE = 'INSCRIPCIO' AND rel.SOURCE_ID = ?
                   )
                 ORDER BY f.DATA_EMISSIO, f.UUID_FACTURA FOR UPDATE",
                [$enrollmentId]
            );

            $invoiceIds = [];
            $invoicedCents = 0;
            $everyInvoicePaid = $invoices !== [];
            foreach ($invoices as $invoice) {
                $invoiceIds[] = (string) $invoice['UUID_FACTURA'];
                if ($invoice['ESTAT_FACTURA'] !== 'ISSUED') {
                    throw SifException::conflict('JASOM has an invoice that is not in issued state.');
                }

                $invoiceCents = $this->cents((string) $invoice['TOTAL']);
                if ($invoiceCents <= 0) {
                    throw SifException::conflict('JASOM has a non-positive origin invoice.');
                }

                $invoicedCents += $invoiceCents;
                $everyInvoicePaid = $everyInvoicePaid && $invoice['ESTAT_COBRAMENT'] === 'PAID';
            }

            if (!in_array($uuidInvoice, $invoiceIds, true)) {
                throw SifException::conflict('Issued JASOM invoice is not an origin invoice for the enrollment.');
            }

            $expectedCents = $this->cents((string) $operation['NET_AMOUNT']);
            if ($expectedCents <= 0 || $invoicedCents > $expectedCents) {
                throw SifException::conflict('Origin invoices exceed the approved JASOM course total.');
            }

            $fullyPaid = $invoicedCents === $expectedCents && $everyInvoicePaid;
            $nextStatus = $fullyPaid ? 'PAID' : 'PAYMENT_PENDING';
            if ($operation['STATUS'] === 'COMPLETED' && $fullyPaid) {
                $nextStatus = 'COMPLETED';
            }

            $stmt = $db->prepare(
                'UPDATE commercial_operation
                 SET UUID_FACTURA = ?, STATUS = ?
                 WHERE UUID_OPERATION = ?'
            );
            $stmt->execute([$alreadyLinked !== '' ? $alreadyLinked : $uuidInvoice, $nextStatus, (string) $operation['UUID_OPERATION']]);

            $approved = $validations[0]['STATUS'] === 'VALIDATED';
            $db->commit();

            return [
                'status' => $approved ? ($fullyPaid ? 'ELIGIBLE_FOR_GRANT' : 'WAITING_FULL_PAYMENT') : 'DENIED',
                'uuid_operation' => (string) $operation['UUID_OPERATION'],
                'grant_eligible' => $approved && $fullyPaid,
                'linked_invoice_count' => count($invoices),
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    private function cents(string $value): int
    {
        if (!preg_match('/^(-?)(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($value), $m)) {
            throw SifException::validation('Invalid JASOM invoice amount.');
        }

        $cents = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '', 2, '0');
        return $m[1] === '-' ? -$cents : $cents;
    }

    private function one(\PDO $db, string $sql, array $parameters): ?array
    {
        return $this->many($db, $sql, $parameters)[0] ?? null;
    }

    private function many(\PDO $db, string $sql, array $parameters): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($parameters);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
