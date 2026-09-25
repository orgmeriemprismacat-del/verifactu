<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\NovicePromotionDestinationAdjustmentPolicy;
use Prisma\Sif\Domain\NovicePromotionRectificationEvidencePolicy;
use Prisma\Sif\Exception\SifException;

/**
 * UC-111: atomically convert ONE approved, original-destination cancellation
 * into a DISTINCT spendable PROMOTIONAL derived right.
 *
 * Approval MUST come from a separately authenticated, finalized backoffice
 * decision via NovicePromotionAdjustmentApprovalSourceInterface. The staged
 * row, a factura_rectificacio link, free-form actor strings and request JSON
 * NEVER establish approval. No live approval-source implementation, controller
 * or scheduled caller is included in this branch.
 *
 * This service does NOT issue invoices, refund external cash, change an
 * original JASOM expiry, send a code, or authorize spending a derived right.
 * It closes historical attribution and activates the derived right in ONE
 * transaction. Later consumption / root refund need a lineage-aware adapter.
 */
final class NovicePromotionDerivedBalanceActivationService
{
    public function __construct(
        private NovicePromotionAdjustmentApprovalSourceInterface $approvals,
        private NovicePromotionRectificationEvidencePolicy $fiscalEvidence = new NovicePromotionRectificationEvidencePolicy(),
        private NovicePromotionDestinationAdjustmentPolicy $adjustments = new NovicePromotionDestinationAdjustmentPolicy()
    ) {
    }

    public function activateApprovedOriginalCancellation(
        \PDO $db,
        string $uuidDerivedReview,
        ?\DateTimeImmutable $now = null
    ): array {
        if ($db->inTransaction()) {
            throw new \LogicException('UC-111 approval requires a separate SIF transaction.');
        }
        if (trim($uuidDerivedReview) === '') {
            throw SifException::validation('Cancellation review reference is required.');
        }

        // The adapter must read the authenticated, APPROVED and FINAL decision
        // from its own authoritative store, not accept a caller-made array.
        $approval = $this->approvals->approvedCancellation($uuidDerivedReview);
        if (!is_array($approval)
            || !$this->hasDecisionFields($approval)
            || $approval['review_uuid'] !== $uuidDerivedReview
            || $approval['decision_type'] !== 'NOVICE_DESTINATION_CANCELLATION'
            || $approval['decision'] !== 'APPROVED'
        ) {
            throw SifException::conflict('No independently approved cancellation decision is available.');
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $now = $now->setTimezone(new \DateTimeZone('UTC'));
        $timestamp = $now->format('Y-m-d H:i:s');
        $approvedAt = (string) $approval['approved_at_utc'];
        if (!preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $approvedAt)
            || $approvedAt > $timestamp
        ) {
            throw SifException::conflict('Approval has no valid finalized timestamp.');
        }

        $db->beginTransaction();
        try {
            $lookup = $this->one(
                $db,
                'SELECT ROOT_UUID_ENTITLEMENT
                 FROM novice_promotion_derived_balance WHERE UUID_DERIVED_BALANCE = ?',
                [$uuidDerivedReview]
            );
            if ($lookup === null) {
                throw SifException::conflict('Cancellation review proposal was not found.');
            }
            $root = $this->one(
                $db,
                'SELECT e.UUID_ENTITLEMENT, e.HOLDER_PARTY_KEY, e.STATUS,
                        g.ORIGIN_UUID_OPERATION, v.STATUS AS VALIDATION_STATUS
                 FROM commercial_entitlement e
                 JOIN novice_promotion_grant g ON g.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
                 JOIN discount_validation v ON v.UUID_VALIDATION = g.UUID_VALIDATION
                 WHERE e.UUID_ENTITLEMENT = ? FOR UPDATE',
                [(string) $lookup['ROOT_UUID_ENTITLEMENT']]
            );
            if ($root === null) {
                throw SifException::conflict('Original novice right no longer exists.');
            }

            $review = $this->one(
                $db,
                'SELECT * FROM novice_promotion_derived_balance
                 WHERE UUID_DERIVED_BALANCE = ? FOR UPDATE',
                [$uuidDerivedReview]
            );
            if ($review === null
                || (string) $review['ROOT_UUID_ENTITLEMENT'] !== (string) $root['UUID_ENTITLEMENT']
            ) {
                throw SifException::conflict('Cancellation review origin changed during approval.');
            }

            $snapshot = json_decode((string) $review['POLICY_SNAPSHOT_JSON'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($snapshot)) {
                throw SifException::conflict('Cancellation review evidence is not readable.');
            }

            // A delayed duplicate approval NEVER reissues the balance, even
            // after the root has subsequently been refunded or the derived
            // right has expired/cancelled.
            if ($review['STATUS'] !== 'PENDING_FISCAL_REVIEW') {
                $savedDecision = $snapshot['review_decision'] ?? null;
                if (in_array((string) $review['STATUS'], ['ACTIVE', 'CANCELLED', 'EXPIRED'], true)
                    && is_array($savedDecision)
                    && ($savedDecision['decision_id'] ?? null) === $approval['decision_id']
                    && ($savedDecision['status'] ?? null) === 'APPROVED'
                ) {
                    $db->commit();
                    return [
                        'uuid_derived_balance' => $uuidDerivedReview,
                        'status' => (string) $review['STATUS'],
                        'available_promotional_amount' => (string) $review['AVAILABLE_PROMOTIONAL_AMOUNT'],
                        'idempotency_reused' => true,
                    ];
                }
                throw SifException::conflict('Cancellation review has already received a different final decision.');
            }

            if ($root['STATUS'] !== 'ACTIVE'
                || $root['VALIDATION_STATUS'] !== 'VALIDATED'
                || (string) $review['HOLDER_PARTY_KEY'] !== (string) $root['HOLDER_PARTY_KEY']
                || $review['PARENT_UUID_DERIVED_BALANCE'] !== null
                || $review['SOURCE_UUID_DERIVED_APPLICATION'] !== null
                || trim((string) ($review['SOURCE_UUID_APPLICATION'] ?? '')) === ''
                || $review['ISSUED_AT'] !== null || $review['EXPIRES_AT'] !== null
                || $this->cents((string) $review['AVAILABLE_PROMOTIONAL_AMOUNT']) !== 0
            ) {
                throw SifException::conflict('Only an unissued original-course review with a valid JASOM root can be approved.');
            }

            $application = $this->one(
                $db,
                'SELECT * FROM novice_promotion_application
                 WHERE UUID_APPLICATION = ? FOR UPDATE',
                [(string) $review['SOURCE_UUID_APPLICATION']]
            );
            if ($application === null
                || $application['STATUS'] !== 'APPLIED'
                || (string) $application['UUID_ENTITLEMENT'] !== (string) $root['UUID_ENTITLEMENT']
                || (string) $application['UUID_DESTINATION_OPERATION'] !== (string) $review['UUID_DESTINATION_OPERATION']
                || trim((string) ($application['UUID_DESTINATION_FACTURA'] ?? '')) === ''
            ) {
                throw SifException::conflict('Original promotional consumption is no longer eligible for conversion.');
            }
            if ($this->one(
                $db,
                'SELECT UUID_TRANSFER FROM novice_promotion_application_transfer
                 WHERE UUID_ORIGINAL_APPLICATION = ? LIMIT 1 FOR UPDATE',
                [(string) $application['UUID_APPLICATION']]
            ) !== null) {
                throw SifException::conflict('Transferred promotional consumption needs its own current-destination review.');
            }

            $destination = $this->one(
                $db,
                'SELECT UUID_OPERATION, UUID_FACTURA, SOURCE_ID, SOURCE_TYPE,
                        PRODUCT_TYPE, CURRENCY
                 FROM commercial_operation WHERE UUID_OPERATION = ? FOR UPDATE',
                [(string) $application['UUID_DESTINATION_OPERATION']]
            );
            if ($destination === null
                || (string) $destination['UUID_FACTURA'] !== (string) $application['UUID_DESTINATION_FACTURA']
                || $destination['SOURCE_TYPE'] !== 'CURS'
                || $destination['PRODUCT_TYPE'] !== 'CURS'
                || $destination['CURRENCY'] !== 'EUR'
            ) {
                throw SifException::conflict('Cancelled destination is inconsistent with the applied promotion.');
            }
            $participants = $this->many(
                $db,
                "SELECT PARTY_KEY FROM commercial_operation_party
                 WHERE UUID_OPERATION = ? AND PARTY_ROLE = 'PARTICIPANT' FOR UPDATE",
                [(string) $destination['UUID_OPERATION']]
            );
            if (count($participants) !== 1
                || (string) $participants[0]['PARTY_KEY'] !== (string) $root['HOLDER_PARTY_KEY']
            ) {
                throw SifException::conflict('Cancellation no longer belongs to the novice holder.');
            }

            $originalInvoice = $this->one(
                $db,
                'SELECT UUID_FACTURA, TIPUS_FACTURA, ESTAT_FACTURA, DATA_EMISSIO
                 FROM factura WHERE UUID_FACTURA = ? FOR UPDATE',
                [(string) $application['UUID_DESTINATION_FACTURA']]
            );
            $rectificative = $this->one(
                $db,
                'SELECT UUID_FACTURA, TIPUS_FACTURA, ESTAT_FACTURA, DATA_EMISSIO
                 FROM factura WHERE UUID_FACTURA = ? FOR UPDATE',
                [(string) $review['UUID_RECTIFICATIVE_FACTURA']]
            );
            $rectificationLink = $this->one(
                $db,
                'SELECT UUID_FACTURA_RECTIFICADA, UUID_FACTURA_RECTIFICATIVA,
                        MOTIU, MODE_RECTIFICACIO
                 FROM factura_rectificacio
                 WHERE UUID_FACTURA_RECTIFICATIVA = ? AND UUID_FACTURA_RECTIFICADA = ?
                 FOR UPDATE',
                [(string) $review['UUID_RECTIFICATIVE_FACTURA'], (string) $application['UUID_DESTINATION_FACTURA']]
            );
            try {
                $this->fiscalEvidence->assertCancellationReference(
                    $originalInvoice ?? [], $rectificative ?? [], $rectificationLink ?? []
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('Approved destination has no matching issued fiscal rectificative.');
            }

            $sourceId = trim((string) ($destination['SOURCE_ID'] ?? ''));
            if ($sourceId === '' || !ctype_digit($sourceId)
                || $this->one(
                    $db,
                    "SELECT ID FROM fact_rels WHERE UUID_FACTURA = ?
                     AND SOURCE_TYPE = 'INSCRIPCIO' AND SOURCE_ID = ? LIMIT 1",
                    [(string) $application['UUID_DESTINATION_FACTURA'], (int) $sourceId]
                ) === null
            ) {
                throw SifException::conflict('Original invoice no longer has a verified enrollment relation.');
            }

            $this->assertOriginalJasomStillPaid($db, (string) $root['ORIGIN_UUID_OPERATION']);

            foreach ([
                'review_uuid' => $uuidDerivedReview,
                'uuid_original_application' => (string) $application['UUID_APPLICATION'],
                'uuid_rectificative' => (string) $review['UUID_RECTIFICATIVE_FACTURA'],
                'approved_promotional_amount' => (string) $review['PROMOTIONAL_ORIGIN_AMOUNT'],
                'approved_cash_amount' => (string) ($snapshot['proposed_cash_amount'] ?? ''),
                'evidence_ref' => (string) ($snapshot['policy_evidence_ref'] ?? ''),
            ] as $key => $expected) {
                if ((string) ($approval[$key] ?? '') !== $expected) {
                    throw SifException::conflict('The external approval does not match this immutable cancellation proposal.');
                }
            }
            if ($approvedAt < (string) $review['CREATED_AT']) {
                throw SifException::conflict('Approval predates the cancellation proposal.');
            }

            try {
                $plan = $this->adjustments->planCancellation(
                    (string) $application['AMOUNT'],
                    (string) ($snapshot['original_cash_reconciled'] ?? ''),
                    (string) $approval['approved_promotional_amount'],
                    (string) $approval['approved_cash_amount'],
                    $now
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('Approval is inconsistent with the historical promotion or cash.');
            }
            if (!$plan['creates_promotional_derived_right']
                || (string) $plan['promotional_derived_amount'] !== (string) $review['PROMOTIONAL_ORIGIN_AMOUNT']
            ) {
                throw SifException::conflict('Approved cancellation would create an invalid derived promotional right.');
            }

            $issuedUtc = (string) $plan['derived_issued_at_utc'];
            $expiryUtc = (string) $plan['derived_expires_at_utc'];
            if ($issuedUtc === '' || $expiryUtc === ''
                || $issuedUtc >= $expiryUtc
            ) {
                throw SifException::conflict('Derived balance has invalid independent expiry.');
            }

            $snapshot['review_decision'] = [
                'status' => 'APPROVED',
                'decision_id' => (string) $approval['decision_id'],
                'reviewed_by' => (string) $approval['reviewer_id'],
                'evidence_ref' => (string) $approval['evidence_ref'],
                'approved_at_utc' => $approvedAt,
                'derived_issued_at_utc' => $issuedUtc,
                'derived_expires_at_utc' => $expiryUtc,
                'promotional_forfeited_amount' => (string) $plan['promotional_forfeited'],
                'cash_amount_routed_separately' => (string) $plan['cash_refund_or_credit_eligible'],
            ];

            $stmt = $db->prepare(
                "UPDATE novice_promotion_application
                 SET STATUS = 'REVERSED', REVERSED_AT = ?, REASON_CODE = 'CONVERTED_TO_DERIVED'
                 WHERE UUID_APPLICATION = ? AND STATUS = 'APPLIED'"
            );
            $stmt->execute([$timestamp, (string) $application['UUID_APPLICATION']]);
            if ($stmt->rowCount() !== 1) {
                throw SifException::conflict('Historical promotional application changed during activation.');
            }

            $stmt = $db->prepare(
                "UPDATE novice_promotion_derived_balance
                 SET STATUS = 'ACTIVE', AVAILABLE_PROMOTIONAL_AMOUNT = ?,
                     ISSUED_AT = ?, EXPIRES_AT = ?, POLICY_SNAPSHOT_JSON = ?
                 WHERE UUID_DERIVED_BALANCE = ? AND STATUS = 'PENDING_FISCAL_REVIEW'
                   AND AVAILABLE_PROMOTIONAL_AMOUNT = 0"
            );
            $stmt->execute([
                (string) $plan['promotional_derived_amount'], $issuedUtc, $expiryUtc,
                json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                $uuidDerivedReview,
            ]);
            if ($stmt->rowCount() !== 1) {
                throw SifException::conflict('Cancellation approval changed concurrently.');
            }

            $db->commit();
            return [
                'uuid_derived_balance' => $uuidDerivedReview,
                'root_uuid_entitlement' => (string) $root['UUID_ENTITLEMENT'],
                'status' => 'ACTIVE',
                'available_promotional_amount' => (string) $plan['promotional_derived_amount'],
                'issued_at_utc' => $issuedUtc,
                'expires_at_utc' => $expiryUtc,
                'idempotency_reused' => false,
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    private function hasDecisionFields(array $approval): bool
    {
        foreach ([
            'review_uuid', 'decision_type', 'decision_id', 'decision',
            'reviewer_id', 'evidence_ref', 'approved_at_utc',
            'uuid_original_application', 'uuid_rectificative',
            'approved_promotional_amount', 'approved_cash_amount',
        ] as $field) {
            if (!array_key_exists($field, $approval)
                || !is_string($approval[$field])
                || trim($approval[$field]) === ''
            ) {
                return false;
            }
        }
        return true;
    }

    private function assertOriginalJasomStillPaid(\PDO $db, string $uuidOperation): void
    {
        $origin = $this->one(
            $db,
            'SELECT SOURCE_TYPE, SOURCE_ID, PRODUCT_CODE, STATUS, NET_AMOUNT
             FROM commercial_operation WHERE UUID_OPERATION = ? FOR UPDATE',
            [$uuidOperation]
        );
        if ($origin === null || $origin['SOURCE_TYPE'] !== 'CURS'
            || $origin['PRODUCT_CODE'] !== 'JASOM'
            || !in_array((string) $origin['STATUS'], ['PAID', 'INVOICED', 'COMPLETED'], true)
        ) {
            throw SifException::conflict('JASOM original operation is not a fully paid active origin.');
        }
        $sourceId = trim((string) ($origin['SOURCE_ID'] ?? ''));
        if ($sourceId === '' || !ctype_digit($sourceId) || (int) $sourceId < 1) {
            throw SifException::conflict('JASOM original enrollment is missing.');
        }
        $invoices = $this->many(
            $db,
            "SELECT f.UUID_FACTURA, f.TOTAL, f.ESTAT_FACTURA, f.ESTAT_COBRAMENT
             FROM factura f
             WHERE f.TIPUS_FACTURA IN ('F1','F2')
               AND EXISTS (
                   SELECT 1 FROM fact_rels r WHERE r.UUID_FACTURA = f.UUID_FACTURA
                     AND r.SOURCE_TYPE = 'INSCRIPCIO' AND r.SOURCE_ID = ?
               )
             ORDER BY f.DATA_EMISSIO, f.UUID_FACTURA FOR UPDATE",
            [(int) $sourceId]
        );
        $netInvoiced = 0;
        foreach ($invoices as $invoice) {
            if ($invoice['ESTAT_FACTURA'] !== 'ISSUED'
                || $invoice['ESTAT_COBRAMENT'] !== 'PAID'
            ) {
                throw SifException::conflict('A JASOM installment is no longer fully paid.');
            }
            $invoiceTotal = $this->cents((string) $invoice['TOTAL']);
            if ($invoiceTotal <= 0) {
                throw SifException::conflict('JASOM invoice has invalid original amount.');
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
            if ($this->cents((string) ($settlement['NET_CASH'] ?? '0.00')) !== $invoiceTotal) {
                throw SifException::conflict('JASOM has a confirmed refund or missing cash settlement.');
            }
            $netInvoiced += $invoiceTotal;
        }
        if ($netInvoiced <= 0 || $netInvoiced !== $this->cents((string) $origin['NET_AMOUNT'])) {
            throw SifException::conflict('JASOM total does not match its actual paid invoices.');
        }
    }

    private function cents(string $amount): int
    {
        if (!preg_match('/^(-?)(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($amount), $match)) {
            throw SifException::validation('Invalid promotional money amount.');
        }
        $cents = (int) $match[2] * 100 + (int) str_pad($match[3] ?? '', 2, '0');
        return $match[1] === '-' ? -$cents : $cents;
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
