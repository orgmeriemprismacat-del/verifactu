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
            if ($alreadyLinked !== '' && $alreadyLinked !== $uuidInvoice) {
                throw SifException::conflict('JASOM operation is linked to another invoice.');
            }

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

            $invoice = $this->one(
                $db,
                'SELECT UUID_FACTURA, TOTAL, ESTAT_FACTURA, ESTAT_COBRAMENT
                 FROM factura WHERE UUID_FACTURA = ? FOR UPDATE',
                [$uuidInvoice]
            );
            if ($invoice === null || $invoice['ESTAT_FACTURA'] !== 'ISSUED') {
                throw SifException::conflict('Origin JASOM invoice has not been issued.');
            }
            if ((string) $invoice['TOTAL'] !== (string) $operation['NET_AMOUNT']) {
                throw SifException::conflict('JASOM invoice amount differs from approved origin operation.');
            }

            $fullyPaid = $invoice['ESTAT_COBRAMENT'] === 'PAID';
            $nextStatus = $fullyPaid ? 'PAID' : 'PAYMENT_PENDING';
            if ($operation['STATUS'] === 'COMPLETED' && $fullyPaid) {
                $nextStatus = 'COMPLETED';
            }

            $stmt = $db->prepare(
                'UPDATE commercial_operation
                 SET UUID_FACTURA = ?, STATUS = ?
                 WHERE UUID_OPERATION = ?'
            );
            $stmt->execute([$uuidInvoice, $nextStatus, (string) $operation['UUID_OPERATION']]);

            $approved = $validations[0]['STATUS'] === 'VALIDATED';
            $db->commit();

            return [
                'status' => $approved ? ($fullyPaid ? 'ELIGIBLE_FOR_GRANT' : 'WAITING_FULL_PAYMENT') : 'DENIED',
                'uuid_operation' => (string) $operation['UUID_OPERATION'],
                'grant_eligible' => $approved && $fullyPaid,
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
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
