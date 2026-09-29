<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\NovicePromotionApprovedRootRefundPolicy;
use Prisma\Sif\Domain\NovicePromotionOriginRefundEvidencePolicy;
use Prisma\Sif\Domain\NovicePromotionRootRefundPlanFingerprintPolicy;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

/**
 * UC-111 / DEC-23:
 * Execute ONLY the PROMOTIONAL/COMMERCIAL consequences AFTER an independently
 * approved frozen JASOM review AND the real JASOM refund is externally
 * confirmed and reconciled in payment_transaction/payment_allocation.
 *
 * Atomic effects:
 * - root grant AVAILABLE_AMOUNT -> 0;
 * - issued derived balances -> CANCELLED and available -> 0;
 * - root commercial_entitlement REFUND_REVIEW -> CANCELLED;
 * - one PENDING_RECOVERY work item per CURRENT active promotional application;
 * - refund review -> EXECUTED + approval evidence;
 * - commercial_entitlement_event audit.
 *
 * Explicitly NOT performed by this service:
 * - initiating the bank refund of JASOM (it must already be confirmed);
 * - CHARGE/debt collection;
 * - new factura/rectificative;
 * - automatic recovery settlement.
 */
final class NovicePromotionRootRefundExecutionService
{
    public function __construct(
        private NovicePromotionAdjustmentApprovalSourceInterface $approvals,
        private NovicePromotionOriginRefundEvidenceSourceInterface $originRefunds,
        private NovicePromotionRootRefundPlanService $plans
            = new NovicePromotionRootRefundPlanService(),
        private NovicePromotionRootRefundPlanFingerprintPolicy $fingerprints
            = new NovicePromotionRootRefundPlanFingerprintPolicy(),
        private NovicePromotionApprovedRootRefundPolicy $decisions
            = new NovicePromotionApprovedRootRefundPolicy(),
        private NovicePromotionOriginRefundEvidencePolicy $refundEvidence
            = new NovicePromotionOriginRefundEvidencePolicy(),
        private UuidGenerator $uuids = new UuidGenerator()
    ) {
    }

    public function executeApprovedCommercialConsequences(
        \PDO $db,
        string $uuidReview,
        ?\DateTimeImmutable $now = null
    ): array {
        if ($db->inTransaction()) {
            throw new \LogicException('Root refund execution must own its SIF transaction.');
        }
        $uuidReview = trim($uuidReview);
        if ($uuidReview === '') {
            throw SifException::validation('Root refund review identifier is required.');
        }

        $approval = $this->approvals->approvedRootRefund($uuidReview);
        if (!is_array($approval)
            || (string) ($approval['review_uuid'] ?? '') !== $uuidReview
            || (string) ($approval['decision_type'] ?? '') !== 'NOVICE_ROOT_JASOM_REFUND'
            || (string) ($approval['decision'] ?? '') !== 'APPROVED'
        ) {
            throw SifException::conflict('No independently finalized root-JASOM refund approval is available.');
        }

        $originRefund = $this->originRefunds->confirmedOriginRefund($uuidReview);
        if (!is_array($originRefund)
            || (string) ($originRefund['review_uuid'] ?? '') !== $uuidReview
        ) {
            throw SifException::conflict('The original JASOM refund is not independently confirmed yet.');
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $timestamp = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $db->beginTransaction();
        try {
            $lookup = $this->one(
                $db,
                'SELECT ROOT_UUID_ENTITLEMENT
                 FROM novice_promotion_root_refund_review
                 WHERE UUID_REVIEW = ?',
                [$uuidReview]
            );
            if ($lookup === null) {
                throw SifException::conflict('Root refund review does not exist.');
            }

            $root = $this->lockRoot(
                $db,
                (string) $lookup['ROOT_UUID_ENTITLEMENT']
            );
            $review = $this->one(
                $db,
                'SELECT * FROM novice_promotion_root_refund_review
                 WHERE UUID_REVIEW = ? FOR UPDATE',
                [$uuidReview]
            );
            if ($review === null
                || (string) $review['ROOT_UUID_ENTITLEMENT']
                    !== (string) $root['UUID_ENTITLEMENT']
            ) {
                throw SifException::conflict('Root refund review changed during execution.');
            }

            if ($review['STATUS'] === 'EXECUTED') {
                if ((string) $review['APPROVAL_DECISION_ID']
                    !== (string) ($approval['decision_id'] ?? '')
                ) {
                    throw SifException::conflict('Root refund review was executed with another decision.');
                }
                $db->commit();
                return [
                    'uuid_review' => $uuidReview,
                    'root_uuid_entitlement' => (string) $review['ROOT_UUID_ENTITLEMENT'],
                    'status' => 'EXECUTED',
                    'origin_bank_refund_confirmed' => true,
                    'origin_bank_refund_performed_by_this_service' => false,
                    'idempotency_reused' => true,
                ];
            }

            if ($review['STATUS'] !== 'PENDING_APPROVAL'
                || $root['ENTITLEMENT_STATUS'] !== 'REFUND_REVIEW'
                || $root['VALIDATION_STATUS'] !== 'VALIDATED'
                || (string) $review['HOLDER_PARTY_KEY']
                    !== (string) $root['HOLDER_PARTY_KEY']
                || (string) $review['ORIGIN_UUID_OPERATION']
                    !== (string) $root['ORIGIN_UUID_OPERATION']
            ) {
                throw SifException::conflict('Root refund review is not a live frozen approval candidate.');
            }

            try {
                $this->decisions->assertMatches($approval, $review, $timestamp);
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('Root refund approval differs from the frozen review.');
            }

            try {
                $this->refundEvidence->assertMatches(
                    $originRefund,
                    $review,
                    $this->money($this->cents((string) $root['ORIGINAL_CASH_AMOUNT'])),
                    $timestamp
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('Confirmed JASOM refund evidence differs from the frozen review.');
            }

            // The promotion remains frozen while the external payment system
            // performs the actual refund. Only AFTER confirmed CHARGE and
            // REFUND allocations net to zero do we cancel promotional value.
            $this->assertOriginalJasomFullyRefunded(
                $db,
                (string) $root['ORIGIN_UUID_OPERATION'],
                $this->cents((string) $root['ORIGINAL_CASH_AMOUNT'])
            );

            $currentPlan = $this->plans->planLocked(
                $db,
                (string) $root['UUID_ENTITLEMENT']
            );
            try {
                $fingerprint = $this->fingerprints->fingerprint($currentPlan);
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('Current root refund plan cannot be fingerprinted safely.');
            }
            if (!hash_equals((string) $review['PLAN_HASH'], (string) $fingerprint['hash'])
                || !hash_equals((string) $review['PLAN_JSON'], (string) $fingerprint['json'])
            ) {
                throw SifException::conflict('Promotion lineage changed after freeze; refund review must be rebuilt.');
            }

            $recover = $fingerprint['canonical']['recover_active_applications'];
            $createdRecoveries = 0;
            foreach ($recover as $item) {
                [$kind, $sourceUuid, $operationUuid, $actualAmount]
                    = $this->resolveCurrentApplication(
                        $db,
                        (string) $item['application_id']
                    );
                if ($actualAmount !== (string) $item['amount']) {
                    throw SifException::conflict('Current recovery source amount differs from frozen refund plan.');
                }

                $stmt = $db->prepare(
                    "INSERT INTO novice_promotion_root_refund_recovery
                     (UUID_RECOVERY, UUID_REVIEW, ROOT_UUID_ENTITLEMENT,
                      LOGICAL_APPLICATION_ID, SOURCE_KIND, SOURCE_UUID,
                      UUID_DESTINATION_OPERATION, AMOUNT, STATUS)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PENDING_RECOVERY')"
                );
                $stmt->execute([
                    $this->uuids->generate(),
                    $uuidReview,
                    (string) $root['UUID_ENTITLEMENT'],
                    (string) $item['application_id'],
                    $kind,
                    $sourceUuid,
                    $operationUuid,
                    (string) $item['amount'],
                ]);
                $createdRecoveries++;
            }

            $zeroRoot = $db->prepare(
                'UPDATE novice_promotion_grant
                 SET AVAILABLE_AMOUNT = 0.00
                 WHERE UUID_ENTITLEMENT = ?'
            );
            $zeroRoot->execute([(string) $root['UUID_ENTITLEMENT']]);
            if ($zeroRoot->rowCount() > 1) {
                throw SifException::conflict('Unexpected novice root balance update cardinality.');
            }

            // Cancel ALL issued derived rights, not only those with remaining
            // amount. This prevents a later release from restoring value after
            // JASOM has been approved for return.
            $cancelDerived = $db->prepare(
                "UPDATE novice_promotion_derived_balance
                 SET AVAILABLE_PROMOTIONAL_AMOUNT = 0.00,
                     STATUS = 'CANCELLED',
                     CANCELLED_AT = ?,
                     CANCELLATION_REASON = 'JASOM_ROOT_REFUND'
                 WHERE ROOT_UUID_ENTITLEMENT = ?
                   AND STATUS IN ('ACTIVE','EXPIRED')"
            );
            $cancelDerived->execute([
                $timestamp,
                (string) $root['UUID_ENTITLEMENT'],
            ]);

            $cancelRoot = $db->prepare(
                "UPDATE commercial_entitlement
                 SET STATUS = 'CANCELLED', CANCELLED_AT = ?
                 WHERE UUID_ENTITLEMENT = ? AND STATUS = 'REFUND_REVIEW'"
            );
            $cancelRoot->execute([
                $timestamp,
                (string) $root['UUID_ENTITLEMENT'],
            ]);
            if ($cancelRoot->rowCount() !== 1) {
                throw SifException::conflict('Frozen novice root changed before cancellation.');
            }

            $finish = $db->prepare(
                "UPDATE novice_promotion_root_refund_review
                 SET STATUS = 'EXECUTED',
                     DECIDED_BY = ?,
                     DECISION_REASON = 'APPROVED_ROOT_REFUND',
                     DECIDED_AT = ?,
                     EXECUTED_AT = ?,
                     APPROVAL_DECISION_ID = ?,
                     APPROVAL_EVIDENCE_REF = ?,
                     ORIGIN_REFUND_EVIDENCE_ID = ?,
                     ORIGIN_REFUND_CONFIRMED_AT = ?,
                     ORIGIN_REFUNDED_AMOUNT = ?
                 WHERE UUID_REVIEW = ? AND STATUS = 'PENDING_APPROVAL'"
            );
            $finish->execute([
                (string) $approval['reviewer_id'],
                (string) $approval['approved_at_utc'],
                $timestamp,
                (string) $approval['decision_id'],
                (string) $approval['evidence_ref'],
                (string) $originRefund['refund_evidence_id'],
                (string) $originRefund['confirmed_at_utc'],
                (string) $originRefund['refunded_amount'],
                $uuidReview,
            ]);
            if ($finish->rowCount() !== 1) {
                throw SifException::conflict('Root refund review changed during final commercial cancellation.');
            }

            $audit = $db->prepare(
                'INSERT INTO commercial_entitlement_event
                 (UUID_EVENT, UUID_ENTITLEMENT, ACTION, RESULT, UUID_OPERATION,
                  ACTOR_TYPE, ACTOR_ID, CORRELATION_ID, CAUSATION_ID,
                  REASON_CODE, CHANGESET_JSON, OCCURRED_AT)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $audit->execute([
                $this->uuids->generate(),
                (string) $root['UUID_ENTITLEMENT'],
                'ROOT_REFUND_EXECUTE',
                'SUCCESS',
                (string) $root['ORIGIN_UUID_OPERATION'],
                'SECRETARIAT',
                (string) $approval['reviewer_id'],
                $uuidReview,
                (string) $approval['decision_id'],
                'PROMOTION_CANCELLED_AFTER_JASOM_REFUND',
                json_encode([
                    'plan_hash' => (string) $review['PLAN_HASH'],
                    'cancelled_available_amount'
                        => (string) $fingerprint['canonical']['total_cancel_available'],
                    'pending_recovery_amount'
                        => (string) $fingerprint['canonical']['total_recover_active'],
                    'recovery_items_created' => $createdRecoveries,
                    'origin_bank_refund_confirmed' => true,
                    'origin_refund_evidence_id' => (string) $originRefund['refund_evidence_id'],
                    'origin_bank_refund_performed_by_this_service' => false,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                $timestamp,
            ]);

            $db->commit();
            return [
                'uuid_review' => $uuidReview,
                'root_uuid_entitlement' => (string) $root['UUID_ENTITLEMENT'],
                'status' => 'EXECUTED',
                'root_status' => 'CANCELLED',
                'cancelled_available_amount'
                    => (string) $fingerprint['canonical']['total_cancel_available'],
                'pending_recovery_amount'
                    => (string) $fingerprint['canonical']['total_recover_active'],
                'recovery_items_created' => $createdRecoveries,
                'origin_bank_refund_confirmed' => true,
                'origin_refund_evidence_id' => (string) $originRefund['refund_evidence_id'],
                'origin_bank_refund_performed_by_this_service' => false,
                'automatic_recovery_charge_performed' => false,
                'idempotency_reused' => false,
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    private function resolveCurrentApplication(
        \PDO $db,
        string $logicalId
    ): array {
        if (str_starts_with($logicalId, 'app:')) {
            $uuid = substr($logicalId, 4);
            $row = $this->one(
                $db,
                "SELECT UUID_DESTINATION_OPERATION, AMOUNT
                 FROM novice_promotion_application
                 WHERE UUID_APPLICATION = ? AND STATUS = 'APPLIED'
                 FOR UPDATE",
                [$uuid]
            );
            $kind = 'ORIGINAL_APPLICATION';
        } elseif (str_starts_with($logicalId, 'dapp:')) {
            $uuid = substr($logicalId, 5);
            $row = $this->one(
                $db,
                "SELECT UUID_DESTINATION_OPERATION, AMOUNT
                 FROM novice_promotion_derived_application
                 WHERE UUID_DERIVED_APPLICATION = ? AND STATUS = 'APPLIED'
                 FOR UPDATE",
                [$uuid]
            );
            $kind = 'DERIVED_APPLICATION';
        } elseif (str_starts_with($logicalId, 'transfer:')) {
            $uuid = substr($logicalId, 9);
            $row = $this->one(
                $db,
                "SELECT TO_UUID_OPERATION AS UUID_DESTINATION_OPERATION, AMOUNT
                 FROM novice_promotion_application_transfer
                 WHERE UUID_TRANSFER = ? AND STATUS = 'CONFIRMED'
                 FOR UPDATE",
                [$uuid]
            );
            $kind = 'TRANSFER';
        } else {
            throw SifException::conflict('Refund plan contains an unsupported logical application type.');
        }

        if ($uuid === '' || $row === null
            || trim((string) ($row['UUID_DESTINATION_OPERATION'] ?? '')) === ''
        ) {
            throw SifException::conflict('Refund recovery source is no longer the current active exposure.');
        }

        return [
            $kind,
            $uuid,
            (string) $row['UUID_DESTINATION_OPERATION'],
            $this->money($this->cents((string) $row['AMOUNT'])),
        ];
    }

    private function lockRoot(\PDO $db, string $rootUuid): array
    {
        $row = $this->one(
            $db,
            'SELECT e.UUID_ENTITLEMENT,
                    e.STATUS AS ENTITLEMENT_STATUS,
                    e.HOLDER_PARTY_KEY,
                    g.ORIGIN_UUID_OPERATION,
                    g.ORIGINAL_CASH_AMOUNT,
                    v.STATUS AS VALIDATION_STATUS
             FROM commercial_entitlement e
             JOIN novice_promotion_grant g
               ON g.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
             JOIN discount_validation v
               ON v.UUID_VALIDATION = g.UUID_VALIDATION
             WHERE e.UUID_ENTITLEMENT = ? FOR UPDATE',
            [$rootUuid]
        );
        if ($row === null) {
            throw SifException::conflict('Novice root does not exist.');
        }
        return $row;
    }

    private function assertOriginalJasomFullyRefunded(
        \PDO $db,
        string $uuidOperation,
        int $expectedOriginalCents
    ): void {
        $origin = $this->one(
            $db,
            'SELECT SOURCE_TYPE, SOURCE_ID, PRODUCT_CODE, NET_AMOUNT
             FROM commercial_operation WHERE UUID_OPERATION = ? FOR UPDATE',
            [$uuidOperation]
        );
        if ($origin === null
            || $origin['SOURCE_TYPE'] !== 'CURS'
            || $origin['PRODUCT_CODE'] !== 'JASOM'
            || $this->cents((string) $origin['NET_AMOUNT']) !== $expectedOriginalCents
        ) {
            throw SifException::conflict('Original JASOM no longer matches the frozen refund origin.');
        }

        $sourceId = trim((string) ($origin['SOURCE_ID'] ?? ''));
        if ($sourceId === '' || !ctype_digit($sourceId) || (int) $sourceId < 1) {
            throw SifException::conflict('Original JASOM enrollment reference is invalid.');
        }

        $invoices = $this->many(
            $db,
            "SELECT f.UUID_FACTURA, f.TOTAL, f.ESTAT_FACTURA
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

        $total = 0;
        foreach ($invoices as $invoice) {
            if ($invoice['ESTAT_FACTURA'] !== 'ISSUED') {
                throw SifException::conflict('Original JASOM invoice is no longer issued.');
            }
            $invoiceCents = $this->cents((string) $invoice['TOTAL']);
            if ($invoiceCents <= 0) {
                throw SifException::conflict('Original JASOM invoice amount is invalid.');
            }

            $settlement = $this->one(
                $db,
                "SELECT
                    COALESCE(SUM(CASE
                        WHEN pt.TIPUS_MOVIMENT = 'CHARGE' THEN pa.IMPORT_ASSIGNAT
                        ELSE 0 END), 0) AS CHARGE_TOTAL,
                    COALESCE(SUM(CASE
                        WHEN pt.TIPUS_MOVIMENT = 'REFUND' THEN pa.IMPORT_ASSIGNAT
                        ELSE 0 END), 0) AS REFUND_TOTAL
                 FROM payment_allocation pa
                 JOIN payment_transaction pt ON pt.UUID_PAYMENT = pa.UUID_PAYMENT
                 WHERE pa.UUID_FACTURA = ? AND pt.ESTAT = 'CONFIRMED'",
                [(string) $invoice['UUID_FACTURA']]
            );
            $charges = $this->cents((string) ($settlement['CHARGE_TOTAL'] ?? '0.00'));
            $refunds = $this->cents((string) ($settlement['REFUND_TOTAL'] ?? '0.00'));
            if ($charges !== $invoiceCents || $refunds !== $invoiceCents) {
                throw SifException::conflict('Original JASOM refund is not fully reconciled invoice by invoice.');
            }
            $total += $invoiceCents;
        }

        if ($total <= 0 || $total !== $expectedOriginalCents) {
            throw SifException::conflict('Original JASOM refunded invoices do not match the frozen amount.');
        }
    }

    private function cents(string $amount): int
    {
        if (preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($amount), $m) !== 1) {
            throw SifException::validation('Invalid root-refund recovery amount.');
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function one(\PDO $db, string $sql, array $args): ?array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($args);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function many(\PDO $db, string $sql, array $args): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
