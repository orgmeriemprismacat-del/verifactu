<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\NovicePromotionRootRefundPlanFingerprintPolicy;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

/**
 * UC-111 / DEC-23: persist and FREEZE a no-side-effect root-refund plan.
 *
 * Opening a review:
 * 1) locks the root and the complete lineage;
 * 2) requires JASOM to still be fully paid (the bank refund has NOT happened);
 * 3) persists a canonical plan hash/json;
 * 4) changes commercial_entitlement.STATUS ACTIVE -> REFUND_REVIEW.
 *
 * Existing UC-111 spending/transfer services require root ACTIVE, therefore
 * REFUND_REVIEW is a reversible hold that prevents plan drift from new use.
 *
 * Reject/cancel releases the hold back to ACTIVE. This class NEVER performs
 * the original JASOM bank refund, creates recovery charges, invoices debt or
 * cancels derived balances. Execution remains a separate approved workflow.
 *
 * requested/reviewer IDs MUST come from authenticated internal backoffice.
 */
final class NovicePromotionRootRefundReviewService
{
    public function __construct(
        private NovicePromotionRootRefundPlanService $plans
            = new NovicePromotionRootRefundPlanService(),
        private NovicePromotionRootRefundPlanFingerprintPolicy $fingerprints
            = new NovicePromotionRootRefundPlanFingerprintPolicy(),
        private UuidGenerator $uuids = new UuidGenerator()
    ) {
    }

    public function openReview(
        \PDO $db,
        string $rootUuid,
        string $requestedBy,
        string $requestEvidenceRef,
        string $idempotencyKey,
        ?\DateTimeImmutable $now = null
    ): array {
        if ($db->inTransaction()) {
            throw new \LogicException('Root refund review must own its SIF transaction.');
        }
        $rootUuid = trim($rootUuid);
        $requestedBy = trim($requestedBy);
        $requestEvidenceRef = trim($requestEvidenceRef);
        $idempotencyKey = trim($idempotencyKey);
        if ($rootUuid === '' || $requestedBy === '' || $requestEvidenceRef === ''
            || $idempotencyKey === ''
            || strlen($requestedBy) > 100
            || strlen($requestEvidenceRef) > 140
            || strlen($idempotencyKey) > 140
        ) {
            throw SifException::validation('Invalid root refund review request.');
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $timestamp = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $db->beginTransaction();
        try {
            $root = $this->lockRoot($db, $rootUuid);

            $previous = $this->one(
                $db,
                'SELECT * FROM novice_promotion_root_refund_review
                 WHERE IDEMPOTENCY_KEY = ? FOR UPDATE',
                [$idempotencyKey]
            );
            if ($previous !== null) {
                if ((string) $previous['ROOT_UUID_ENTITLEMENT'] !== $rootUuid
                    || (string) $previous['REQUESTED_BY'] !== $requestedBy
                    || (string) $previous['REQUEST_EVIDENCE_REF'] !== $requestEvidenceRef
                ) {
                    throw SifException::conflict('Root refund idempotency key was reused with a different request.');
                }
                $db->commit();
                return $this->reviewResult($previous, true);
            }

            if ($root['ENTITLEMENT_STATUS'] !== 'ACTIVE') {
                throw SifException::conflict('Novice promotion is already frozen, cancelled or unavailable.');
            }

            if ($this->one(
                $db,
                "SELECT UUID_REVIEW FROM novice_promotion_root_refund_review
                 WHERE ROOT_UUID_ENTITLEMENT = ? AND STATUS = 'PENDING_APPROVAL'
                 LIMIT 1 FOR UPDATE",
                [$rootUuid]
            ) !== null) {
                throw SifException::conflict('A root refund review is already holding this novice promotion.');
            }

            $this->assertOriginalJasomStillPaid(
                $db,
                (string) $root['ORIGIN_UUID_OPERATION']
            );

            $plan = $this->plans->planLocked($db, $rootUuid);
            try {
                $fingerprint = $this->fingerprints->fingerprint($plan);
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('Root refund plan cannot be canonicalized safely.');
            }
            $canonicalPlan = $fingerprint['canonical'];
            $planJson = $fingerprint['json'];
            $planHash = $fingerprint['hash'];
            $uuidReview = $this->uuids->generate();

            $insert = $db->prepare(
                "INSERT INTO novice_promotion_root_refund_review
                 (UUID_REVIEW, ROOT_UUID_ENTITLEMENT, HOLDER_PARTY_KEY,
                  ORIGIN_UUID_OPERATION, PLAN_HASH, PLAN_JSON, STATUS,
                  REQUESTED_BY, REQUEST_EVIDENCE_REF, REQUESTED_AT,
                  IDEMPOTENCY_KEY)
                 VALUES (?, ?, ?, ?, ?, ?, 'PENDING_APPROVAL', ?, ?, ?, ?)"
            );
            $insert->execute([
                $uuidReview,
                $rootUuid,
                (string) $root['HOLDER_PARTY_KEY'],
                (string) $root['ORIGIN_UUID_OPERATION'],
                $planHash,
                $planJson,
                $requestedBy,
                $requestEvidenceRef,
                $timestamp,
                $idempotencyKey,
            ]);

            $freeze = $db->prepare(
                "UPDATE commercial_entitlement
                 SET STATUS = 'REFUND_REVIEW'
                 WHERE UUID_ENTITLEMENT = ? AND STATUS = 'ACTIVE'"
            );
            $freeze->execute([$rootUuid]);
            if ($freeze->rowCount() !== 1) {
                throw SifException::conflict('Novice promotion changed while opening refund review.');
            }

            $this->audit(
                $db,
                $rootUuid,
                (string) $root['ORIGIN_UUID_OPERATION'],
                $uuidReview,
                'ROOT_REFUND_HOLD',
                'ROOT_REFUND_REVIEW_OPENED',
                $requestedBy,
                $timestamp,
                [
                    'plan_hash' => $planHash,
                    'total_cancel_available' => $canonicalPlan['total_cancel_available'],
                    'total_recover_active' => $canonicalPlan['total_recover_active'],
                    'root_status' => 'REFUND_REVIEW',
                    'execution_performed' => false,
                ]
            );

            $db->commit();
            return [
                'uuid_review' => $uuidReview,
                'root_uuid_entitlement' => $rootUuid,
                'status' => 'PENDING_APPROVAL',
                'plan_hash' => $planHash,
                'total_cancel_available' => $canonicalPlan['total_cancel_available'],
                'total_recover_active' => $canonicalPlan['total_recover_active'],
                'root_status' => 'REFUND_REVIEW',
                'idempotency_reused' => false,
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    public function rejectPendingReview(
        \PDO $db,
        string $uuidReview,
        string $reviewedBy,
        string $reasonCode,
        ?\DateTimeImmutable $now = null
    ): array {
        return $this->closeWithoutExecution(
            $db, $uuidReview, $reviewedBy, $reasonCode, 'REJECTED', $now
        );
    }

    public function cancelPendingReview(
        \PDO $db,
        string $uuidReview,
        string $reviewedBy,
        string $reasonCode,
        ?\DateTimeImmutable $now = null
    ): array {
        return $this->closeWithoutExecution(
            $db, $uuidReview, $reviewedBy, $reasonCode, 'CANCELLED', $now
        );
    }

    private function closeWithoutExecution(
        \PDO $db,
        string $uuidReview,
        string $reviewedBy,
        string $reasonCode,
        string $finalStatus,
        ?\DateTimeImmutable $now
    ): array {
        if ($db->inTransaction()) {
            throw new \LogicException('Root refund review closure must own its SIF transaction.');
        }
        $uuidReview = trim($uuidReview);
        $reviewedBy = trim($reviewedBy);
        if ($uuidReview === '' || $reviewedBy === ''
            || strlen($reviewedBy) > 100
            || !in_array($finalStatus, ['REJECTED', 'CANCELLED'], true)
            || !in_array($reasonCode, [
                'REFUND_NOT_APPROVED',
                'REQUEST_WITHDRAWN',
                'ORIGIN_REFUND_CANCELLED',
                'MANUAL_LINEAGE_REVIEW_REQUIRED',
            ], true)
        ) {
            throw SifException::validation('Invalid root refund review closure.');
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
            if ($review === null) {
                throw SifException::conflict('Root refund review disappeared during closure.');
            }

            if ($review['STATUS'] === $finalStatus) {
                if ((string) $review['DECIDED_BY'] !== $reviewedBy
                    || (string) $review['DECISION_REASON'] !== $reasonCode
                ) {
                    throw SifException::conflict('Root refund review already has a different final decision.');
                }
                $db->commit();
                return $this->reviewResult($review, true);
            }

            if ($review['STATUS'] !== 'PENDING_APPROVAL'
                || $root['ENTITLEMENT_STATUS'] !== 'REFUND_REVIEW'
            ) {
                throw SifException::conflict('Only an active refund hold can be released without execution.');
            }

            // Never unfreeze after any confirmed refund has altered the JASOM
            // settlement. Once money has started moving back, reopening the
            // promotion could allow value to be spent after its origin was
            // returned. Such a case must stay frozen for reconciliation or
            // approved execution.
            $this->assertOriginalJasomStillPaid(
                $db,
                (string) $review['ORIGIN_UUID_OPERATION']
            );

            $stmt = $db->prepare(
                'UPDATE novice_promotion_root_refund_review
                 SET STATUS = ?, DECIDED_BY = ?, DECISION_REASON = ?, DECIDED_AT = ?
                 WHERE UUID_REVIEW = ? AND STATUS = ?'
            );
            $stmt->execute([
                $finalStatus,
                $reviewedBy,
                $reasonCode,
                $timestamp,
                $uuidReview,
                'PENDING_APPROVAL',
            ]);
            if ($stmt->rowCount() !== 1) {
                throw SifException::conflict('Root refund review changed concurrently.');
            }

            $unfreeze = $db->prepare(
                "UPDATE commercial_entitlement
                 SET STATUS = 'ACTIVE'
                 WHERE UUID_ENTITLEMENT = ? AND STATUS = 'REFUND_REVIEW'"
            );
            $unfreeze->execute([(string) $review['ROOT_UUID_ENTITLEMENT']]);
            if ($unfreeze->rowCount() !== 1) {
                throw SifException::conflict('Novice root hold could not be safely released.');
            }

            $this->audit(
                $db,
                (string) $review['ROOT_UUID_ENTITLEMENT'],
                (string) $review['ORIGIN_UUID_OPERATION'],
                $uuidReview,
                'ROOT_REFUND_UNHOLD',
                'ROOT_REFUND_REVIEW_' . $finalStatus,
                $reviewedBy,
                $timestamp,
                ['reason_code' => $reasonCode, 'root_status' => 'ACTIVE']
            );

            $db->commit();
            return [
                'uuid_review' => $uuidReview,
                'root_uuid_entitlement' => (string) $review['ROOT_UUID_ENTITLEMENT'],
                'status' => $finalStatus,
                'root_status' => 'ACTIVE',
                'idempotency_reused' => false,
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    private function lockRoot(\PDO $db, string $rootUuid): array
    {
        $row = $this->one(
            $db,
            'SELECT e.UUID_ENTITLEMENT,
                    e.STATUS AS ENTITLEMENT_STATUS,
                    e.HOLDER_PARTY_KEY,
                    g.ORIGIN_UUID_OPERATION,
                    v.STATUS AS VALIDATION_STATUS
             FROM commercial_entitlement e
             JOIN novice_promotion_grant g
               ON g.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
             JOIN discount_validation v
               ON v.UUID_VALIDATION = g.UUID_VALIDATION
             WHERE e.UUID_ENTITLEMENT = ? FOR UPDATE',
            [$rootUuid]
        );
        if ($row === null || $row['VALIDATION_STATUS'] !== 'VALIDATED') {
            throw SifException::conflict('Novice root no longer exists or is not validated.');
        }
        return $row;
    }

    private function assertOriginalJasomStillPaid(
        \PDO $db,
        string $uuidOperation
    ): void {
        $origin = $this->one(
            $db,
            'SELECT SOURCE_TYPE, SOURCE_ID, PRODUCT_CODE, STATUS, NET_AMOUNT
             FROM commercial_operation
             WHERE UUID_OPERATION = ? FOR UPDATE',
            [$uuidOperation]
        );
        if ($origin === null
            || $origin['SOURCE_TYPE'] !== 'CURS'
            || $origin['PRODUCT_CODE'] !== 'JASOM'
            || !in_array((string) $origin['STATUS'], ['PAID', 'INVOICED', 'COMPLETED'], true)
        ) {
            throw SifException::conflict('Original JASOM must still be fully paid before opening refund review.');
        }

        $sourceId = trim((string) ($origin['SOURCE_ID'] ?? ''));
        if ($sourceId === '' || !ctype_digit($sourceId) || (int) $sourceId < 1) {
            throw SifException::conflict('Original JASOM enrollment reference is invalid.');
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

        $total = 0;
        foreach ($invoices as $invoice) {
            if ($invoice['ESTAT_FACTURA'] !== 'ISSUED'
                || $invoice['ESTAT_COBRAMENT'] !== 'PAID'
            ) {
                throw SifException::conflict('Original JASOM contains an unpaid or nonissued invoice.');
            }
            $invoiceCents = $this->cents((string) $invoice['TOTAL']);
            if ($invoiceCents <= 0) {
                throw SifException::conflict('Original JASOM invoice amount is invalid.');
            }
            $settlement = $this->one(
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
            if ($this->cents((string) ($settlement['NET_CASH'] ?? '0.00'))
                !== $invoiceCents
            ) {
                throw SifException::conflict('Original JASOM already has a refund or incomplete settlement.');
            }
            $total += $invoiceCents;
        }

        if ($total <= 0 || $total !== $this->cents((string) $origin['NET_AMOUNT'])) {
            throw SifException::conflict('Original JASOM total does not match fully paid invoices.');
        }
    }

    private function reviewResult(array $review, bool $replayed): array
    {
        $plan = json_decode((string) $review['PLAN_JSON'], true);
        return [
            'uuid_review' => (string) $review['UUID_REVIEW'],
            'root_uuid_entitlement' => (string) $review['ROOT_UUID_ENTITLEMENT'],
            'status' => (string) $review['STATUS'],
            'plan_hash' => (string) $review['PLAN_HASH'],
            'total_cancel_available'
                => is_array($plan) ? (string) ($plan['total_cancel_available'] ?? '') : '',
            'total_recover_active'
                => is_array($plan) ? (string) ($plan['total_recover_active'] ?? '') : '',
            'idempotency_reused' => $replayed,
        ];
    }

    private function audit(
        \PDO $db,
        string $rootUuid,
        string $operationUuid,
        string $correlationId,
        string $action,
        string $reason,
        string $actorId,
        string $timestamp,
        array $changes
    ): void {
        $stmt = $db->prepare(
            'INSERT INTO commercial_entitlement_event
             (UUID_EVENT, UUID_ENTITLEMENT, ACTION, RESULT, UUID_OPERATION,
              ACTOR_TYPE, ACTOR_ID, CORRELATION_ID, CAUSATION_ID,
              REASON_CODE, CHANGESET_JSON, OCCURRED_AT)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $this->uuids->generate(),
            $rootUuid,
            $action,
            'SUCCESS',
            $operationUuid,
            'SECRETARIAT',
            $actorId,
            $correlationId,
            $correlationId,
            $reason,
            json_encode($changes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            $timestamp,
        ]);
    }

    private function cents(string $amount): int
    {
        if (preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($amount), $m) !== 1) {
            throw SifException::validation('Invalid root refund monetary amount.');
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
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
