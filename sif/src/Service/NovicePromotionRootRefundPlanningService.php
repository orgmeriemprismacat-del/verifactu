<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\NovicePromotionLineagePolicy;
use Prisma\Sif\Domain\NovicePromotionLineageProjector;
use Prisma\Sif\Exception\SifException;

/**
 * UC-111 / DEC-23:
 * Build a LOCKED, read-only plan for refunding the original JASOM without
 * counting historical promotional value twice.
 *
 * This service deliberately DOES NOT:
 * - refund JASOM,
 * - cancel any promotional balance/application,
 * - create an invoice/rectificative, CHARGE, REFUND or credit_balance,
 * - notify or claim money from the student.
 *
 * It locks the complete UC-111 lineage, projects persisted rows to the pure
 * graph and returns the review_required plan from NovicePromotionLineagePolicy.
 * Any pending review/reservation/unknown branch makes the plan fail closed.
 */
final class NovicePromotionRootRefundPlanningService
{
    public function __construct(
        private NovicePromotionLineageProjector $projector = new NovicePromotionLineageProjector(),
        private NovicePromotionLineagePolicy $policy = new NovicePromotionLineagePolicy()
    ) {
    }

    public function plan(
        \PDO $db,
        string $rootUuidEntitlement
    ): array {
        if ($db->inTransaction()) {
            throw new \LogicException('Root refund planning requires an independent transaction.');
        }
        if (trim($rootUuidEntitlement) === '') {
            throw SifException::validation('Novice root entitlement is required.');
        }

        $db->beginTransaction();
        try {
            $root = $this->one(
                $db,
                'SELECT e.UUID_ENTITLEMENT, e.STATUS, e.HOLDER_PARTY_KEY,
                        e.EXPIRES_AT, g.ORIGINAL_CASH_AMOUNT, g.AVAILABLE_AMOUNT,
                        g.ORIGIN_UUID_OPERATION, v.STATUS AS VALIDATION_STATUS
                 FROM commercial_entitlement e
                 JOIN novice_promotion_grant g ON g.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
                 JOIN discount_validation v ON v.UUID_VALIDATION = g.UUID_VALIDATION
                 WHERE e.UUID_ENTITLEMENT = ? FOR UPDATE',
                [$rootUuidEntitlement]
            );
            if ($root === null
                || $root['STATUS'] !== 'ACTIVE'
                || $root['VALIDATION_STATUS'] !== 'VALIDATED'
            ) {
                throw SifException::conflict('Only an active validated novice root can enter JASOM refund planning.');
            }

            // Before preparing a clawback, the original JASOM must still be
            // fully settled. A prior/refund-in-flight changes the starting
            // point and requires reconciliation rather than another plan.
            $this->assertOriginalJasomStillPaid(
                $db,
                (string) $root['ORIGIN_UUID_OPERATION']
            );

            $originalApplications = $this->many(
                $db,
                'SELECT UUID_APPLICATION, UUID_ENTITLEMENT, AMOUNT, STATUS,
                        REASON_CODE, UUID_DESTINATION_OPERATION,
                        UUID_DESTINATION_FACTURA
                 FROM novice_promotion_application
                 WHERE UUID_ENTITLEMENT = ?
                 ORDER BY RESERVED_AT, UUID_APPLICATION FOR UPDATE',
                [$rootUuidEntitlement]
            );

            $derivedBalances = $this->many(
                $db,
                'SELECT UUID_DERIVED_BALANCE, ROOT_UUID_ENTITLEMENT,
                        PARENT_UUID_DERIVED_BALANCE,
                        SOURCE_UUID_APPLICATION, SOURCE_UUID_DERIVED_APPLICATION,
                        SOURCE_UUID_TRANSFER, UUID_DESTINATION_OPERATION,
                        UUID_RECTIFICATIVE_FACTURA, HOLDER_PARTY_KEY,
                        PROMOTIONAL_ORIGIN_AMOUNT, AVAILABLE_PROMOTIONAL_AMOUNT,
                        STATUS, ISSUED_AT, EXPIRES_AT, CANCELLED_AT,
                        CANCELLATION_REASON, POLICY_SNAPSHOT_JSON
                 FROM novice_promotion_derived_balance
                 WHERE ROOT_UUID_ENTITLEMENT = ?
                 ORDER BY CREATED_AT, UUID_DERIVED_BALANCE FOR UPDATE',
                [$rootUuidEntitlement]
            );

            $derivedApplications = $this->many(
                $db,
                'SELECT UUID_DERIVED_APPLICATION, UUID_DERIVED_BALANCE,
                        ROOT_UUID_ENTITLEMENT, UUID_DESTINATION_OPERATION,
                        UUID_DESTINATION_FACTURA, AMOUNT, STATUS,
                        RESERVED_AT, APPLIED_AT, CLOSED_AT,
                        RESERVATION_EXPIRES_AT, RELEASED_AT, REASON_CODE
                 FROM novice_promotion_derived_application
                 WHERE ROOT_UUID_ENTITLEMENT = ?
                 ORDER BY RESERVED_AT, UUID_DERIVED_APPLICATION FOR UPDATE',
                [$rootUuidEntitlement]
            );

            $transfers = $this->many(
                $db,
                'SELECT UUID_TRANSFER, ROOT_UUID_ENTITLEMENT,
                        UUID_ORIGINAL_APPLICATION, UUID_DERIVED_APPLICATION,
                        PREVIOUS_UUID_TRANSFER, FROM_UUID_OPERATION,
                        TO_UUID_OPERATION, UUID_RECTIFICATIVE_FACTURA,
                        UUID_DESTINATION_FACTURA, AMOUNT, STATUS,
                        CONFIRMED_AT, CLOSED_AT, CLOSE_REASON
                 FROM novice_promotion_application_transfer
                 WHERE ROOT_UUID_ENTITLEMENT = ?
                 ORDER BY CREATED_AT, UUID_TRANSFER FOR UPDATE',
                [$rootUuidEntitlement]
            );

            try {
                $graph = $this->projector->project(
                    [
                        'UUID_ENTITLEMENT' => (string) $root['UUID_ENTITLEMENT'],
                        'ORIGINAL_CASH_AMOUNT' => (string) $root['ORIGINAL_CASH_AMOUNT'],
                        'AVAILABLE_AMOUNT' => (string) $root['AVAILABLE_AMOUNT'],
                        'FORFEITED_AMOUNT' => '0.00',
                        'STATUS' => (string) $root['STATUS'],
                    ],
                    $originalApplications,
                    $derivedBalances,
                    $derivedApplications,
                    $transfers
                );
                $plan = $this->policy->planOriginalRefund(
                    $graph['root_right_id'],
                    $graph['rights'],
                    $graph['applications']
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict(
                    'UC-111 lineage is incomplete, pending or inconsistent; JASOM refund cannot be planned automatically.'
                );
            }

            $db->commit();
            return [
                'root_uuid_entitlement' => $rootUuidEntitlement,
                'holder_party_key' => (string) $root['HOLDER_PARTY_KEY'],
                'cancel_available' => $plan['cancel_available'],
                'recover_active_applications' => $plan['recover_active_applications'],
                'total_cancel_available' => $plan['total_cancel_available'],
                'total_recover_active' => $plan['total_recover_active'],
                'review_required' => true,
                'executed' => false,
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    private function assertOriginalJasomStillPaid(\PDO $db, string $uuidOperation): void
    {
        $origin = $this->one(
            $db,
            'SELECT SOURCE_TYPE, SOURCE_ID, PRODUCT_CODE, STATUS, NET_AMOUNT
             FROM commercial_operation WHERE UUID_OPERATION = ? FOR UPDATE',
            [$uuidOperation]
        );
        if ($origin === null
            || $origin['SOURCE_TYPE'] !== 'CURS'
            || $origin['PRODUCT_CODE'] !== 'JASOM'
            || !in_array((string) $origin['STATUS'], ['PAID', 'INVOICED', 'COMPLETED'], true)
        ) {
            throw SifException::conflict('Original JASOM is not a fully paid refundable starting point.');
        }

        $sourceId = trim((string) ($origin['SOURCE_ID'] ?? ''));
        if ($sourceId === '' || !ctype_digit($sourceId) || (int) $sourceId < 1) {
            throw SifException::conflict('Original JASOM enrollment reference is missing.');
        }

        $invoices = $this->many(
            $db,
            "SELECT f.UUID_FACTURA, f.TOTAL, f.ESTAT_FACTURA, f.ESTAT_COBRAMENT
             FROM factura f
             WHERE f.TIPUS_FACTURA IN ('F1','F2')
               AND EXISTS (
                   SELECT 1 FROM fact_rels r
                   WHERE r.UUID_FACTURA = f.UUID_FACTURA
                     AND r.SOURCE_TYPE = 'INSCRIPCIO' AND r.SOURCE_ID = ?
               )
             ORDER BY f.DATA_EMISSIO, f.UUID_FACTURA FOR UPDATE",
            [(int) $sourceId]
        );

        $invoiced = 0;
        foreach ($invoices as $invoice) {
            if ($invoice['ESTAT_FACTURA'] !== 'ISSUED'
                || $invoice['ESTAT_COBRAMENT'] !== 'PAID'
            ) {
                throw SifException::conflict('JASOM contains an unpaid/nonissued invoice before refund planning.');
            }
            $total = $this->cents((string) $invoice['TOTAL']);
            if ($total <= 0) {
                throw SifException::conflict('JASOM invoice amount is invalid.');
            }

            $cash = $this->one(
                $db,
                "SELECT COALESCE(SUM(CASE
                    WHEN pt.TIPUS_MOVIMENT = 'CHARGE' THEN pa.IMPORT_ASSIGNAT
                    WHEN pt.TIPUS_MOVIMENT = 'REFUND' THEN -pa.IMPORT_ASSIGNAT
                    ELSE 0 END), 0) AS NET_CASH
                 FROM payment_allocation pa
                 JOIN payment_transaction pt ON pt.UUID_PAYMENT = pa.UUID_PAYMENT
                 WHERE pa.UUID_FACTURA = ? AND pt.ESTAT = 'CONFIRMED'",
                [(string) $invoice['UUID_FACTURA']]
            );
            if ($this->cents((string) ($cash['NET_CASH'] ?? '0.00')) !== $total) {
                throw SifException::conflict('JASOM already has a refund or unsettled payment; reconcile before planning.');
            }
            $invoiced += $total;
        }

        if ($invoiced <= 0
            || $invoiced !== $this->cents((string) $origin['NET_AMOUNT'])
        ) {
            throw SifException::conflict('JASOM total does not match its fully paid invoice set.');
        }
    }

    private function cents(string $amount): int
    {
        if (preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($amount), $match) !== 1) {
            throw SifException::validation('Invalid promotional/fiscal amount in refund planning.');
        }
        return (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');
    }

    private function one(\PDO $db, string $sql, array $params): ?array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function many(\PDO $db, string $sql, array $params): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
