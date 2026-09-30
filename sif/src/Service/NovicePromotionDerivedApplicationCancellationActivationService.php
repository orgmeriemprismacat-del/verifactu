<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\NovicePromotionApprovedDerivedCancellationPolicy;
use Prisma\Sif\Domain\NovicePromotionDestinationAdjustmentPolicy;
use Prisma\Sif\Domain\NovicePromotionRectificationEvidencePolicy;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

/**
 * UC-111: activate a CHILD cancellation-derived promotional balance when the
 * CURRENT promotional exposure is an APPLIED derived-balance application.
 *
 * The source derived application becomes historical
 * CONVERTED_TO_DERIVED. Its parent balance is NOT re-credited: the child
 * balance is the successor of the value that was already consumed by the
 * source application. The child receives only the independently approved
 * promotional component and its own one-year validity.
 *
 * This service requires a FINAL trusted backoffice approval; it does not
 * authenticate HTTP callers, issue/rectify invoices, refund cash, create
 * payment_transaction/credit_balance, send a code, or restore JASOM value.
 */
final class NovicePromotionDerivedApplicationCancellationActivationService
{
    public function __construct(
        private NovicePromotionAdjustmentApprovalSourceInterface $approvals,
        private NovicePromotionApprovedDerivedCancellationPolicy $decisions
            = new NovicePromotionApprovedDerivedCancellationPolicy(),
        private NovicePromotionRectificationEvidencePolicy $fiscal
            = new NovicePromotionRectificationEvidencePolicy(),
        private NovicePromotionDestinationAdjustmentPolicy $adjustments
            = new NovicePromotionDestinationAdjustmentPolicy(),
        private UuidGenerator $uuids = new UuidGenerator()
    ) {
    }

    public function activateApprovedDerivedApplicationCancellation(
        \PDO $db,
        string $uuidChildReview,
        ?\DateTimeImmutable $now = null
    ): array {
        if ($db->inTransaction()) {
            throw new \LogicException(
                'Derived-application cancellation activation requires its own transaction.'
            );
        }
        if (trim($uuidChildReview) === '') {
            throw SifException::validation(
                'Derived cancellation review identifier is required.'
            );
        }

        $approval = $this->approvals
            ->approvedDerivedApplicationCancellation($uuidChildReview);
        if (!is_array($approval)
            || (string) ($approval['review_uuid'] ?? '') !== $uuidChildReview
            || (string) ($approval['decision_type'] ?? '')
                !== 'NOVICE_DERIVED_APPLICATION_CANCELLATION'
            || (string) ($approval['decision'] ?? '') !== 'APPROVED'
        ) {
            throw SifException::conflict(
                'No independently approved derived-course cancellation is available.'
            );
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $now = $now->setTimezone(new \DateTimeZone('UTC'));
        $timestamp = $now->format('Y-m-d H:i:s');

        $db->beginTransaction();
        try {
            $lookup = $this->one(
                $db,
                'SELECT ROOT_UUID_ENTITLEMENT, PARENT_UUID_DERIVED_BALANCE
                 FROM novice_promotion_derived_balance
                 WHERE UUID_DERIVED_BALANCE = ?',
                [$uuidChildReview]
            );
            if ($lookup === null) {
                throw SifException::conflict(
                    'Derived-course cancellation review was not found.'
                );
            }

            // Lock order: root -> parent balance -> child review -> source app.
            $root = $this->lockRoot(
                $db,
                (string) $lookup['ROOT_UUID_ENTITLEMENT']
            );

            $parentUuid = trim(
                (string) ($lookup['PARENT_UUID_DERIVED_BALANCE'] ?? '')
            );
            if ($parentUuid === '') {
                throw SifException::conflict(
                    'Derived-course cancellation is missing its parent balance.'
                );
            }

            $parent = $this->one(
                $db,
                'SELECT * FROM novice_promotion_derived_balance
                 WHERE UUID_DERIVED_BALANCE = ? FOR UPDATE',
                [$parentUuid]
            );
            if ($parent === null
                || (string) $parent['ROOT_UUID_ENTITLEMENT']
                    !== (string) $root['UUID_ENTITLEMENT']
                || (string) $parent['HOLDER_PARTY_KEY']
                    !== (string) $root['HOLDER_PARTY_KEY']
                || !in_array(
                    (string) $parent['STATUS'],
                    ['ACTIVE', 'EXPIRED'],
                    true
                )
            ) {
                throw SifException::conflict(
                    'Parent derived balance is not a valid lineage source.'
                );
            }

            $review = $this->one(
                $db,
                'SELECT * FROM novice_promotion_derived_balance
                 WHERE UUID_DERIVED_BALANCE = ? FOR UPDATE',
                [$uuidChildReview]
            );
            if ($review === null
                || (string) $review['ROOT_UUID_ENTITLEMENT']
                    !== (string) $root['UUID_ENTITLEMENT']
                || (string) $review['PARENT_UUID_DERIVED_BALANCE'] !== $parentUuid
            ) {
                throw SifException::conflict(
                    'Derived cancellation lineage changed during activation.'
                );
            }

            $snapshot = json_decode(
                (string) $review['POLICY_SNAPSHOT_JSON'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            if (!is_array($snapshot)) {
                throw SifException::conflict(
                    'Derived cancellation review evidence is unreadable.'
                );
            }

            // A delayed replay must never issue the child balance again.
            if ($review['STATUS'] !== 'PENDING_FISCAL_REVIEW') {
                $saved = $snapshot['review_decision'] ?? null;
                if (in_array(
                        (string) $review['STATUS'],
                        ['ACTIVE', 'CANCELLED', 'EXPIRED'],
                        true
                    )
                    && is_array($saved)
                    && ($saved['status'] ?? null) === 'APPROVED'
                    && ($saved['decision_id'] ?? null)
                        === ($approval['decision_id'] ?? null)
                ) {
                    $db->commit();
                    return [
                        'uuid_derived_balance' => $uuidChildReview,
                        'parent_uuid_derived_balance' => $parentUuid,
                        'status' => (string) $review['STATUS'],
                        'available_promotional_amount'
                            => (string) $review['AVAILABLE_PROMOTIONAL_AMOUNT'],
                        'idempotency_reused' => true,
                    ];
                }
                throw SifException::conflict(
                    'Derived cancellation review already has another final decision.'
                );
            }

            if ($root['STATUS'] !== 'ACTIVE'
                || $root['VALIDATION_STATUS'] !== 'VALIDATED'
                || (string) $review['HOLDER_PARTY_KEY']
                    !== (string) $root['HOLDER_PARTY_KEY']
                || $review['SOURCE_UUID_APPLICATION'] !== null
                || $review['SOURCE_UUID_TRANSFER'] !== null
                || trim(
                    (string) ($review['SOURCE_UUID_DERIVED_APPLICATION'] ?? '')
                ) === ''
                || $review['ISSUED_AT'] !== null
                || $review['EXPIRES_AT'] !== null
                || $this->cents(
                    (string) $review['AVAILABLE_PROMOTIONAL_AMOUNT']
                ) !== 0
            ) {
                throw SifException::conflict(
                    'Only an unissued derived-application review can be activated.'
                );
            }

            $sourceAppUuid = (string) $review['SOURCE_UUID_DERIVED_APPLICATION'];
            $source = $this->one(
                $db,
                'SELECT a.*, b.HOLDER_PARTY_KEY,
                        b.UUID_DERIVED_BALANCE AS PARENT_BALANCE_UUID
                 FROM novice_promotion_derived_application a
                 JOIN novice_promotion_derived_balance b
                   ON b.UUID_DERIVED_BALANCE = a.UUID_DERIVED_BALANCE
                 WHERE a.UUID_DERIVED_APPLICATION = ?
                 FOR UPDATE',
                [$sourceAppUuid]
            );
            if ($source === null
                || $source['STATUS'] !== 'APPLIED'
                || (string) $source['ROOT_UUID_ENTITLEMENT']
                    !== (string) $root['UUID_ENTITLEMENT']
                || (string) $source['PARENT_BALANCE_UUID'] !== $parentUuid
                || (string) $source['HOLDER_PARTY_KEY']
                    !== (string) $root['HOLDER_PARTY_KEY']
                || (string) $source['UUID_DESTINATION_OPERATION']
                    !== (string) $review['UUID_DESTINATION_OPERATION']
                || trim(
                    (string) ($source['UUID_DESTINATION_FACTURA'] ?? '')
                ) === ''
            ) {
                throw SifException::conflict(
                    'Source derived application is no longer the current exposure.'
                );
            }

            if ($this->one(
                $db,
                "SELECT UUID_TRANSFER
                 FROM novice_promotion_application_transfer
                 WHERE UUID_DERIVED_APPLICATION = ?
                   AND STATUS <> 'CANCELLED'
                 FOR UPDATE",
                [$sourceAppUuid]
            ) !== null) {
                throw SifException::conflict(
                    'Derived application already moved to a later course.'
                );
            }

            $destination = $this->one(
                $db,
                'SELECT UUID_OPERATION, SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE,
                        CURRENCY, UUID_FACTURA, NET_AMOUNT
                 FROM commercial_operation
                 WHERE UUID_OPERATION = ? FOR UPDATE',
                [(string) $source['UUID_DESTINATION_OPERATION']]
            );
            if ($destination === null
                || $destination['SOURCE_TYPE'] !== 'CURS'
                || $destination['PRODUCT_TYPE'] !== 'CURS'
                || $destination['CURRENCY'] !== 'EUR'
                || (string) $destination['UUID_FACTURA']
                    !== (string) $source['UUID_DESTINATION_FACTURA']
            ) {
                throw SifException::conflict(
                    'Derived source course no longer matches its final invoice.'
                );
            }

            $people = $this->many(
                $db,
                "SELECT PARTY_KEY
                 FROM commercial_operation_party
                 WHERE UUID_OPERATION = ?
                   AND PARTY_ROLE = 'PARTICIPANT'
                 FOR UPDATE",
                [(string) $destination['UUID_OPERATION']]
            );
            if (count($people) !== 1
                || (string) $people[0]['PARTY_KEY']
                    !== (string) $root['HOLDER_PARTY_KEY']
            ) {
                throw SifException::conflict(
                    'Derived cancellation no longer belongs to the novice holder.'
                );
            }

            $sourceInvoice = $this->one(
                $db,
                'SELECT UUID_FACTURA, TIPUS_FACTURA, ESTAT_FACTURA,
                        ESTAT_COBRAMENT, TOTAL, DATA_EMISSIO
                 FROM factura
                 WHERE UUID_FACTURA = ? FOR UPDATE',
                [(string) $source['UUID_DESTINATION_FACTURA']]
            );
            $rectificative = $this->one(
                $db,
                'SELECT UUID_FACTURA, TIPUS_FACTURA,
                        ESTAT_FACTURA, DATA_EMISSIO
                 FROM factura
                 WHERE UUID_FACTURA = ? FOR UPDATE',
                [(string) $review['UUID_RECTIFICATIVE_FACTURA']]
            );
            $link = $this->one(
                $db,
                'SELECT UUID_FACTURA_RECTIFICADA,
                        UUID_FACTURA_RECTIFICATIVA,
                        MOTIU, MODE_RECTIFICACIO
                 FROM factura_rectificacio
                 WHERE UUID_FACTURA_RECTIFICATIVA = ?
                   AND UUID_FACTURA_RECTIFICADA = ?
                 FOR UPDATE',
                [
                    (string) $review['UUID_RECTIFICATIVE_FACTURA'],
                    (string) $source['UUID_DESTINATION_FACTURA'],
                ]
            );
            try {
                $this->fiscal->assertCancellationReference(
                    $sourceInvoice ?? [],
                    $rectificative ?? [],
                    $link ?? []
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict(
                    'Derived source has no matching issued rectificative.'
                );
            }

            $sourceId = trim((string) ($destination['SOURCE_ID'] ?? ''));
            if ($sourceId === ''
                || !ctype_digit($sourceId)
                || (int) $sourceId < 1
                || $this->one(
                    $db,
                    "SELECT ID
                     FROM fact_rels
                     WHERE UUID_FACTURA = ?
                       AND SOURCE_TYPE = 'INSCRIPCIO'
                       AND SOURCE_ID = ?
                     LIMIT 1",
                    [
                        (string) $source['UUID_DESTINATION_FACTURA'],
                        (int) $sourceId,
                    ]
                ) === null
            ) {
                throw SifException::conflict(
                    'Derived source invoice is not linked to its enrollment.'
                );
            }

            try {
                $this->decisions->assertMatches(
                    $approval,
                    $uuidChildReview,
                    $review,
                    $snapshot,
                    $timestamp
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict(
                    'Final derived cancellation approval differs from its immutable review.'
                );
            }

            // Reconcile the CURRENT cash again. A refund between review and
            // approval invalidates the proposed split rather than creating a
            // child right from stale evidence.
            $promotion = $this->cents((string) $source['AMOUNT']);
            $ordinary = $this->cents(
                (string) $source['DESTINATION_ORDINARY_NET_AMOUNT']
            );
            $finalNet = $this->cents((string) $destination['NET_AMOUNT']);
            $invoiceTotal = $this->cents(
                (string) ($sourceInvoice['TOTAL'] ?? '')
            );
            $settlement = $this->one(
                $db,
                "SELECT COALESCE(SUM(CASE
                    WHEN pt.TIPUS_MOVIMENT = 'CHARGE'
                        THEN pa.IMPORT_ASSIGNAT
                    WHEN pt.TIPUS_MOVIMENT = 'REFUND'
                        THEN -pa.IMPORT_ASSIGNAT
                    ELSE 0 END), 0) AS NET_CASH
                 FROM payment_allocation pa
                 JOIN payment_transaction pt
                   ON pt.UUID_PAYMENT = pa.UUID_PAYMENT
                 WHERE pa.UUID_FACTURA = ?
                   AND pt.ESTAT = 'CONFIRMED'",
                [(string) $source['UUID_DESTINATION_FACTURA']]
            );
            $currentCash = $this->cents(
                (string) ($settlement['NET_CASH'] ?? '0.00')
            );
            if ($promotion <= 0
                || $finalNet < 0
                || $ordinary !== $promotion + $finalNet
                || $invoiceTotal !== $finalNet
                || $currentCash !== $finalNet
                || $this->money($currentCash)
                    !== (string) ($snapshot['original_cash_reconciled'] ?? '')
            ) {
                throw SifException::conflict(
                    'Derived source cash changed after review; cancellation must be recalculated.'
                );
            }

            $this->assertOriginalJasomStillPaid(
                $db,
                (string) $root['ORIGIN_UUID_OPERATION']
            );

            try {
                $plan = $this->adjustments->planCancellation(
                    (string) $source['AMOUNT'],
                    $this->money($currentCash),
                    (string) $approval['approved_promotional_amount'],
                    (string) $approval['approved_cash_amount'],
                    $now
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict(
                    'Approved derived cancellation exceeds documented provenance.'
                );
            }
            if (!$plan['creates_promotional_derived_right']
                || (string) $plan['promotional_derived_amount']
                    !== (string) $review['PROMOTIONAL_ORIGIN_AMOUNT']
            ) {
                throw SifException::conflict(
                    'Approved derived cancellation would create an invalid child balance.'
                );
            }

            $issuedUtc = (string) $plan['derived_issued_at_utc'];
            $expiryUtc = (string) $plan['derived_expires_at_utc'];
            if ($issuedUtc === ''
                || $expiryUtc === ''
                || $issuedUtc >= $expiryUtc
            ) {
                throw SifException::conflict(
                    'Child derived balance has an invalid independent expiry.'
                );
            }

            $snapshot['review_decision'] = [
                'status' => 'APPROVED',
                'decision_id' => (string) $approval['decision_id'],
                'reviewed_by' => (string) $approval['reviewer_id'],
                'evidence_ref' => (string) $approval['evidence_ref'],
                'approved_at_utc' => (string) $approval['approved_at_utc'],
                'derived_issued_at_utc' => $issuedUtc,
                'derived_expires_at_utc' => $expiryUtc,
                'promotional_forfeited_amount'
                    => (string) $plan['promotional_forfeited'],
                'cash_amount_routed_separately'
                    => (string) $plan['cash_refund_or_credit_eligible'],
            ];

            // The parent balance is deliberately NOT replenished.
            $close = $db->prepare(
                "UPDATE novice_promotion_derived_application
                 SET STATUS = 'CONVERTED_TO_DERIVED',
                     CLOSED_AT = ?,
                     REASON_CODE = 'CONVERTED_TO_DERIVED'
                 WHERE UUID_DERIVED_APPLICATION = ?
                   AND STATUS = 'APPLIED'"
            );
            $close->execute([$timestamp, $sourceAppUuid]);
            if ($close->rowCount() !== 1) {
                throw SifException::conflict(
                    'Source derived application changed during child activation.'
                );
            }

            $activate = $db->prepare(
                "UPDATE novice_promotion_derived_balance
                 SET STATUS = 'ACTIVE',
                     AVAILABLE_PROMOTIONAL_AMOUNT = ?,
                     ISSUED_AT = ?,
                     EXPIRES_AT = ?,
                     POLICY_SNAPSHOT_JSON = ?
                 WHERE UUID_DERIVED_BALANCE = ?
                   AND STATUS = 'PENDING_FISCAL_REVIEW'
                   AND AVAILABLE_PROMOTIONAL_AMOUNT = 0"
            );
            $activate->execute([
                (string) $plan['promotional_derived_amount'],
                $issuedUtc,
                $expiryUtc,
                json_encode(
                    $snapshot,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
                ),
                $uuidChildReview,
            ]);
            if ($activate->rowCount() !== 1) {
                throw SifException::conflict(
                    'Child derived cancellation activation changed concurrently.'
                );
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
                'DERIVED_ACTIVATE_CHILD',
                'SUCCESS',
                (string) $source['UUID_DESTINATION_OPERATION'],
                'SECRETARIAT',
                (string) $approval['reviewer_id'],
                $uuidChildReview,
                (string) $approval['decision_id'],
                'DERIVED_APPLICATION_CONVERTED_TO_CHILD_BALANCE',
                json_encode([
                    'uuid_child_derived_balance' => $uuidChildReview,
                    'parent_uuid_derived_balance' => $parentUuid,
                    'source_uuid_derived_application' => $sourceAppUuid,
                    'uuid_rectificative'
                        => (string) $review['UUID_RECTIFICATIVE_FACTURA'],
                    'promotional_amount'
                        => (string) $plan['promotional_derived_amount'],
                    'promotional_forfeited_amount'
                        => (string) $plan['promotional_forfeited'],
                    'cash_amount_routed_separately'
                        => (string) $plan['cash_refund_or_credit_eligible'],
                    'expires_at_utc' => $expiryUtc,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                $timestamp,
            ]);

            $db->commit();
            return [
                'uuid_derived_balance' => $uuidChildReview,
                'parent_uuid_derived_balance' => $parentUuid,
                'source_uuid_derived_application' => $sourceAppUuid,
                'status' => 'ACTIVE',
                'available_promotional_amount'
                    => (string) $plan['promotional_derived_amount'],
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

    private function lockRoot(\PDO $db, string $rootUuid): array
    {
        $root = $this->one(
            $db,
            'SELECT e.UUID_ENTITLEMENT, e.STATUS, e.HOLDER_PARTY_KEY,
                    g.ORIGIN_UUID_OPERATION, v.STATUS AS VALIDATION_STATUS
             FROM commercial_entitlement e
             JOIN novice_promotion_grant g
               ON g.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
             JOIN discount_validation v
               ON v.UUID_VALIDATION = g.UUID_VALIDATION
             WHERE e.UUID_ENTITLEMENT = ? FOR UPDATE',
            [$rootUuid]
        );
        if ($root === null
            || $root['STATUS'] !== 'ACTIVE'
            || $root['VALIDATION_STATUS'] !== 'VALIDATED'
        ) {
            throw SifException::conflict(
                'Original JASOM right is not active for child balance activation.'
            );
        }
        return $root;
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
            || !in_array(
                (string) $origin['STATUS'],
                ['PAID', 'INVOICED', 'COMPLETED'],
                true
            )
        ) {
            throw SifException::conflict(
                'Original JASOM is not an active fully paid origin.'
            );
        }

        $sourceId = trim((string) ($origin['SOURCE_ID'] ?? ''));
        if ($sourceId === ''
            || !ctype_digit($sourceId)
            || (int) $sourceId < 1
        ) {
            throw SifException::conflict(
                'Original JASOM enrollment reference is invalid.'
            );
        }

        $invoices = $this->many(
            $db,
            "SELECT f.UUID_FACTURA, f.TOTAL,
                    f.ESTAT_FACTURA, f.ESTAT_COBRAMENT
             FROM factura f
             WHERE f.TIPUS_FACTURA IN ('F1','F2')
               AND EXISTS (
                   SELECT 1
                   FROM fact_rels r
                   WHERE r.UUID_FACTURA = f.UUID_FACTURA
                     AND r.SOURCE_TYPE = 'INSCRIPCIO'
                     AND r.SOURCE_ID = ?
               )
             ORDER BY f.DATA_EMISSIO, f.UUID_FACTURA
             FOR UPDATE",
            [(int) $sourceId]
        );

        $invoiced = 0;
        foreach ($invoices as $invoice) {
            if ($invoice['ESTAT_FACTURA'] !== 'ISSUED'
                || $invoice['ESTAT_COBRAMENT'] !== 'PAID'
            ) {
                throw SifException::conflict(
                    'Original JASOM has an unpaid or nonissued invoice.'
                );
            }
            $total = $this->cents((string) $invoice['TOTAL']);
            if ($total <= 0) {
                throw SifException::conflict(
                    'Original JASOM invoice amount is invalid.'
                );
            }
            $cash = $this->one(
                $db,
                "SELECT COALESCE(SUM(CASE
                    WHEN pt.TIPUS_MOVIMENT = 'CHARGE'
                        THEN pa.IMPORT_ASSIGNAT
                    WHEN pt.TIPUS_MOVIMENT = 'REFUND'
                        THEN -pa.IMPORT_ASSIGNAT
                    ELSE 0 END), 0) AS NET_CASH
                 FROM payment_allocation pa
                 JOIN payment_transaction pt
                   ON pt.UUID_PAYMENT = pa.UUID_PAYMENT
                 WHERE pa.UUID_FACTURA = ?
                   AND pt.ESTAT = 'CONFIRMED'",
                [(string) $invoice['UUID_FACTURA']]
            );
            if ($this->cents(
                    (string) ($cash['NET_CASH'] ?? '0.00')
                ) !== $total
            ) {
                throw SifException::conflict(
                    'Original JASOM has a confirmed refund or incomplete settlement.'
                );
            }
            $invoiced += $total;
        }

        if ($invoiced <= 0
            || $invoiced !== $this->cents((string) $origin['NET_AMOUNT'])
        ) {
            throw SifException::conflict(
                'Original JASOM no longer matches fully paid invoices.'
            );
        }
    }

    private function cents(string $amount): int
    {
        if (!preg_match(
            '/^(-?)(\d{1,10})(?:\.(\d{1,2}))?$/D',
            trim($amount),
            $match
        )) {
            throw SifException::validation(
                'Invalid derived cancellation amount.'
            );
        }
        $cents = (int) $match[2] * 100
            + (int) str_pad($match[3] ?? '', 2, '0');
        return $match[1] === '-' ? -$cents : $cents;
    }

    private function money(int $cents): string
    {
        if ($cents < 0) {
            throw SifException::validation(
                'Negative derived cancellation cash is invalid.'
            );
        }
        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
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
