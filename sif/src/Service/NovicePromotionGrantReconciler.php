<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

/**
 * UC-111 post-payment orchestration over ALREADY PREPARED SIF records.
 *
 * The preparer (not implemented here) must create the canonical participant,
 * JASOM commercial operation and secretary-validated discount_validation.
 * This reconciler reads only committed SIF invoices and cannot infer a
 * secretary's approval from a browser flag or a Redsys callback.
 *
 * Safe to retry: the grant service and the UNIQUE holder constraint decide
 * whether a right may be issued, and no redeemable token/email is sent here.
 */
final class NovicePromotionGrantReconciler
{
    public function __construct(private NovicePromotionGrantService $grants)
    {
    }

    public function run(\PDO $db, int $limit = 100): array
    {
        if ($db->inTransaction()) {
            throw new \LogicException('Reconciler requires a connection outside an existing transaction.');
        }

        if ($limit < 1 || $limit > 500) {
            throw new \InvalidArgumentException('Reconciliation limit must be between 1 and 500.');
        }

        $stmt = $db->prepare(
            "SELECT op.UUID_OPERATION
             FROM commercial_operation op
             JOIN discount_validation validation
                ON validation.UUID_OPERATION = op.UUID_OPERATION
               AND validation.DISCOUNT_TYPE = 'NOVICE_TEACHER'
               AND validation.STATUS = 'VALIDATED'
               AND validation.FUTURE_ENTITLEMENT_REF IS NULL
             JOIN factura invoice
                ON invoice.UUID_FACTURA = op.UUID_FACTURA
               AND invoice.ESTAT_FACTURA = 'ISSUED'
               AND invoice.ESTAT_COBRAMENT = 'PAID'
             WHERE op.SOURCE_TYPE = 'CURS'
               AND op.PRODUCT_CODE = 'JASOM'
               AND op.STATUS IN ('PAID', 'INVOICED', 'COMPLETED')
             ORDER BY op.CREATED_AT, op.UUID_OPERATION
             LIMIT ?"
        );
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $operations = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $outcome = [
            'candidates' => count($operations),
            'issued' => 0,
            'reused' => 0,
            'conflicts' => 0,
            'errors' => 0,
            'failed_operation_refs' => [],
        ];

        foreach ($operations as $uuidOperation) {
            try {
                $result = $this->grants->issueForOperation($db, (string) $uuidOperation);
                $outcome[$result['idempotency_reused'] ? 'reused' : 'issued']++;
            } catch (\Prisma\Sif\Exception\SifException $exception) {
                // Do not silently grant or mark a candidate processed when
                // the payer, academic eligibility or economic link conflicts.
                $outcome['conflicts']++;
                $outcome['failed_operation_refs'][] = (string) $uuidOperation;
            } catch (\Throwable $exception) {
                // Preserve the other candidates for later work. The calling
                // script exits non-zero so the operator must inspect failures.
                $outcome['errors']++;
                $outcome['failed_operation_refs'][] = (string) $uuidOperation;
            }
        }

        return $outcome;
    }
}
