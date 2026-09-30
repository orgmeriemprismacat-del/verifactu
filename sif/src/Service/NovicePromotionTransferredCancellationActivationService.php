<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\NovicePromotionApprovedTransferredCancellationPolicy;
use Prisma\Sif\Domain\NovicePromotionDestinationAdjustmentPolicy;
use Prisma\Sif\Domain\NovicePromotionRectificationEvidencePolicy;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

/**
 * UC-111 / DEC-18/21/23:
 * Activate a derived cancellation balance for the CURRENT course reached by
 * ANY latest CONFIRMED transfer. The transfer becomes historical
 * CANCELLED/CONVERTED_TO_DERIVED; the new derived right carries only the
 * approved promotional component with its own one-year expiry.
 *
 * A finalized approval MUST be fetched from the trusted approval source.
 * This service never authenticates a browser, issues a rectificative, refunds
 * real cash, creates credit_balance/payment_transaction, or restores the
 * original JASOM promotion. If the transfer chain belongs to a derived right,
 * the child cancellation balance preserves that right as its declared parent.
 */
final class NovicePromotionTransferredCancellationActivationService
{
    public function __construct(
        private NovicePromotionAdjustmentApprovalSourceInterface $approvals,
        private NovicePromotionApprovedTransferredCancellationPolicy $decisions = new NovicePromotionApprovedTransferredCancellationPolicy(),
        private NovicePromotionRectificationEvidencePolicy $fiscal = new NovicePromotionRectificationEvidencePolicy(),
        private NovicePromotionDestinationAdjustmentPolicy $adjustments = new NovicePromotionDestinationAdjustmentPolicy(),
        private UuidGenerator $uuids = new UuidGenerator()
    ) {
    }

    public function activateApprovedTransferredCancellation(
        \PDO $db,
        string $uuidDerivedReview,
        ?\DateTimeImmutable $now = null
    ): array {
        if ($db->inTransaction()) {
            throw new \LogicException('Transferred cancellation activation requires its own SIF transaction.');
        }
        if (trim($uuidDerivedReview) === '') {
            throw SifException::validation('Transferred cancellation review identifier is required.');
        }

        $approval = $this->approvals->approvedTransferredCancellation($uuidDerivedReview);
        if (!is_array($approval)
            || (string) ($approval['review_uuid'] ?? '') !== $uuidDerivedReview
            || (string) ($approval['decision_type'] ?? '') !== 'NOVICE_TRANSFERRED_DESTINATION_CANCELLATION'
            || (string) ($approval['decision'] ?? '') !== 'APPROVED'
        ) {
            throw SifException::conflict('No independently approved transferred-course cancellation is available.');
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $now = $now->setTimezone(new \DateTimeZone('UTC'));
        $timestamp = $now->format('Y-m-d H:i:s');

        $db->beginTransaction();
        try {
            $lookup = $this->one(
                $db,
                'SELECT ROOT_UUID_ENTITLEMENT
                 FROM novice_promotion_derived_balance
                 WHERE UUID_DERIVED_BALANCE = ?',
                [$uuidDerivedReview]
            );
            if ($lookup === null) {
                throw SifException::conflict('Transferred cancellation review was not found.');
            }

            $root = $this->one(
                $db,
                'SELECT e.UUID_ENTITLEMENT, e.STATUS, e.HOLDER_PARTY_KEY,
                        g.ORIGIN_UUID_OPERATION, v.STATUS AS VALIDATION_STATUS
                 FROM commercial_entitlement e
                 JOIN novice_promotion_grant g ON g.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
                 JOIN discount_validation v ON v.UUID_VALIDATION = g.UUID_VALIDATION
                 WHERE e.UUID_ENTITLEMENT = ? FOR UPDATE',
                [(string) $lookup['ROOT_UUID_ENTITLEMENT']]
            );
            if ($root === null) {
                throw SifException::conflict('Original novice promotion no longer exists.');
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
                throw SifException::conflict('Transferred cancellation lineage changed during activation.');
            }

            $snapshot = json_decode(
                (string) $review['POLICY_SNAPSHOT_JSON'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            if (!is_array($snapshot)) {
                throw SifException::conflict('Transferred cancellation review evidence is unreadable.');
            }

            // Delayed replay after activation must not issue the balance again.
            if ($review['STATUS'] !== 'PENDING_FISCAL_REVIEW') {
                $saved = $snapshot['review_decision'] ?? null;
                if (in_array((string) $review['STATUS'], ['ACTIVE', 'CANCELLED', 'EXPIRED'], true)
                    && is_array($saved)
                    && ($saved['status'] ?? null) === 'APPROVED'
                    && ($saved['decision_id'] ?? null) === ($approval['decision_id'] ?? null)
                ) {
                    $db->commit();
                    return [
                        'uuid_derived_balance' => $uuidDerivedReview,
                        'status' => (string) $review['STATUS'],
                        'available_promotional_amount' => (string) $review['AVAILABLE_PROMOTIONAL_AMOUNT'],
                        'idempotency_reused' => true,
                    ];
                }
                throw SifException::conflict('Transferred cancellation already has a different final decision.');
            }

            if ($root['STATUS'] !== 'ACTIVE'
                || $root['VALIDATION_STATUS'] !== 'VALIDATED'
                || (string) $review['HOLDER_PARTY_KEY'] !== (string) $root['HOLDER_PARTY_KEY']
                || $review['SOURCE_UUID_APPLICATION'] !== null
                || $review['SOURCE_UUID_DERIVED_APPLICATION'] !== null
                || trim((string) ($review['SOURCE_UUID_TRANSFER'] ?? '')) === ''
                || $review['ISSUED_AT'] !== null
                || $review['EXPIRES_AT'] !== null
                || $this->cents((string) $review['AVAILABLE_PROMOTIONAL_AMOUNT']) !== 0
            ) {
                throw SifException::conflict('Only an unissued current-transfer cancellation review can be activated.');
            }

            $transfer = $this->one(
                $db,
                'SELECT * FROM novice_promotion_application_transfer
                 WHERE UUID_TRANSFER = ? FOR UPDATE',
                [(string) $review['SOURCE_UUID_TRANSFER']]
            );
            if ($transfer === null
                || $transfer['STATUS'] !== 'CONFIRMED'
                || (string) $transfer['ROOT_UUID_ENTITLEMENT'] !== (string) $root['UUID_ENTITLEMENT']
                || (string) $transfer['TO_UUID_OPERATION'] !== (string) $review['UUID_DESTINATION_OPERATION']
                || trim((string) ($transfer['UUID_DESTINATION_FACTURA'] ?? '')) === ''
                || (
                    trim((string) ($transfer['UUID_ORIGINAL_APPLICATION'] ?? '')) === ''
                    && trim((string) ($transfer['UUID_DERIVED_APPLICATION'] ?? '')) === ''
                    && trim((string) ($transfer['PREVIOUS_UUID_TRANSFER'] ?? '')) === ''
                )
            ) {
                throw SifException::conflict('Only a current confirmed transfer with traceable predecessor can create this derived right.');
            }

            $expectedParentDerivedBalance = $this->resolveTransferParentBalanceUuid(
                $db,
                $transfer
            );
            if ((string) ($review['PARENT_UUID_DERIVED_BALANCE'] ?? '')
                !== (string) ($expectedParentDerivedBalance ?? '')
            ) {
                throw SifException::conflict(
                    'Transferred cancellation review does not preserve the right being moved.'
                );
            }

            if ($this->one(
                $db,
                "SELECT UUID_TRANSFER FROM novice_promotion_application_transfer
                 WHERE PREVIOUS_UUID_TRANSFER = ? AND STATUS <> 'CANCELLED' FOR UPDATE",
                [(string) $transfer['UUID_TRANSFER']]
            ) !== null) {
                throw SifException::conflict('Transferred cancellation must target the latest course in the chain.');
            }

            $destination = $this->one(
                $db,
                'SELECT UUID_OPERATION, SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE,
                        CURRENCY, UUID_FACTURA
                 FROM commercial_operation WHERE UUID_OPERATION = ? FOR UPDATE',
                [(string) $transfer['TO_UUID_OPERATION']]
            );
            if ($destination === null
                || $destination['SOURCE_TYPE'] !== 'CURS'
                || $destination['PRODUCT_TYPE'] !== 'CURS'
                || $destination['CURRENCY'] !== 'EUR'
                || (string) $destination['UUID_FACTURA'] !== (string) $transfer['UUID_DESTINATION_FACTURA']
            ) {
                throw SifException::conflict('Transferred current destination no longer matches its final invoice.');
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
                throw SifException::conflict('Transferred cancellation no longer belongs to the novice holder.');
            }

            $sourceInvoice = $this->one(
                $db,
                'SELECT UUID_FACTURA, TIPUS_FACTURA, ESTAT_FACTURA,
                        ESTAT_COBRAMENT, TOTAL, DATA_EMISSIO
                 FROM factura WHERE UUID_FACTURA = ? FOR UPDATE',
                [(string) $transfer['UUID_DESTINATION_FACTURA']]
            );
            $rectificative = $this->one(
                $db,
                'SELECT UUID_FACTURA, TIPUS_FACTURA, ESTAT_FACTURA, DATA_EMISSIO
                 FROM factura WHERE UUID_FACTURA = ? FOR UPDATE',
                [(string) $review['UUID_RECTIFICATIVE_FACTURA']]
            );
            $link = $this->one(
                $db,
                'SELECT UUID_FACTURA_RECTIFICADA, UUID_FACTURA_RECTIFICATIVA,
                        MOTIU, MODE_RECTIFICACIO
                 FROM factura_rectificacio
                 WHERE UUID_FACTURA_RECTIFICATIVA = ?
                   AND UUID_FACTURA_RECTIFICADA = ? FOR UPDATE',
                [(string) $review['UUID_RECTIFICATIVE_FACTURA'],
                 (string) $transfer['UUID_DESTINATION_FACTURA']]
            );
            try {
                $this->fiscal->assertCancellationReference(
                    $sourceInvoice ?? [], $rectificative ?? [], $link ?? []
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('Transferred destination has no matching issued rectificative.');
            }

            $sourceId = trim((string) ($destination['SOURCE_ID'] ?? ''));
            if ($sourceId === '' || !ctype_digit($sourceId)
                || $this->one(
                    $db,
                    "SELECT ID FROM fact_rels WHERE UUID_FACTURA = ?
                     AND SOURCE_TYPE = 'INSCRIPCIO' AND SOURCE_ID = ? LIMIT 1",
                    [(string) $transfer['UUID_DESTINATION_FACTURA'], (int) $sourceId]
                ) === null
            ) {
                throw SifException::conflict('Transferred destination invoice is no longer tied to its enrollment.');
            }

            try {
                $this->decisions->assertMatches(
                    $approval,
                    $uuidDerivedReview,
                    $review,
                    $snapshot,
                    $timestamp
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('Final transferred cancellation approval differs from its immutable review.');
            }

            // Reconcile the transferred destination AGAIN at activation time.
            // A refund or payment mutation between review and approval must
            // invalidate the old proposed split instead of minting a derived
            // balance from stale cash evidence.
            $promotion = $this->cents((string) $transfer['AMOUNT']);
            $ordinaryNet = $this->cents((string) $transfer['ORDINARY_NET_BEFORE_PROMOTION']);
            $finalNet = $this->cents((string) $transfer['FINAL_NET_AMOUNT']);
            $invoiceTotal = $this->cents((string) ($sourceInvoice['TOTAL'] ?? ''));
            $settlement = $this->one(
                $db,
                "SELECT COALESCE(SUM(CASE
                    WHEN pt.TIPUS_MOVIMENT = 'CHARGE' THEN pa.IMPORT_ASSIGNAT
                    WHEN pt.TIPUS_MOVIMENT = 'REFUND' THEN -pa.IMPORT_ASSIGNAT
                    ELSE 0 END), 0) AS NET_CASH
                 FROM payment_allocation pa
                 JOIN payment_transaction pt ON pt.UUID_PAYMENT = pa.UUID_PAYMENT
                 WHERE pa.UUID_FACTURA = ? AND pt.ESTAT = 'CONFIRMED'",
                [(string) $transfer['UUID_DESTINATION_FACTURA']]
            );
            $currentCash = $this->cents((string) ($settlement['NET_CASH'] ?? '0.00'));
            if ($promotion <= 0
                || $finalNet < 0
                || $ordinaryNet !== $promotion + $finalNet
                || $invoiceTotal !== $finalNet
                || $currentCash !== $finalNet
                || $this->money($currentCash) !== (string) ($snapshot['original_cash_reconciled'] ?? '')
            ) {
                throw SifException::conflict('Transferred destination cash changed after review; cancellation must be recalculated.');
            }

            $this->assertOriginalJasomStillPaid(
                $db,
                (string) $root['ORIGIN_UUID_OPERATION']
            );

            try {
                $plan = $this->adjustments->planCancellation(
                    (string) $transfer['AMOUNT'],
                    (string) ($snapshot['original_cash_reconciled'] ?? ''),
                    (string) $approval['approved_promotional_amount'],
                    (string) $approval['approved_cash_amount'],
                    $now
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('Approved transferred cancellation exceeds documented provenance.');
            }
            if (!$plan['creates_promotional_derived_right']
                || (string) $plan['promotional_derived_amount']
                    !== (string) $review['PROMOTIONAL_ORIGIN_AMOUNT']
            ) {
                throw SifException::conflict('Transferred cancellation approval would create an invalid derived balance.');
            }

            $issuedUtc = (string) $plan['derived_issued_at_utc'];
            $expiryUtc = (string) $plan['derived_expires_at_utc'];
            if ($issuedUtc === '' || $expiryUtc === '' || $issuedUtc >= $expiryUtc) {
                throw SifException::conflict('Transferred cancellation has invalid independent expiry.');
            }

            $snapshot['review_decision'] = [
                'status' => 'APPROVED',
                'decision_id' => (string) $approval['decision_id'],
                'reviewed_by' => (string) $approval['reviewer_id'],
                'evidence_ref' => (string) $approval['evidence_ref'],
                'approved_at_utc' => (string) $approval['approved_at_utc'],
                'derived_issued_at_utc' => $issuedUtc,
                'derived_expires_at_utc' => $expiryUtc,
                'promotional_forfeited_amount' => (string) $plan['promotional_forfeited'],
                'cash_amount_routed_separately' => (string) $plan['cash_refund_or_credit_eligible'],
            ];

            $close = $db->prepare(
                "UPDATE novice_promotion_application_transfer
                 SET STATUS = 'CANCELLED', CLOSED_AT = ?,
                     CLOSE_REASON = 'CONVERTED_TO_DERIVED'
                 WHERE UUID_TRANSFER = ? AND STATUS = 'CONFIRMED'"
            );
            $close->execute([$timestamp, (string) $transfer['UUID_TRANSFER']]);
            if ($close->rowCount() !== 1) {
                throw SifException::conflict('Confirmed transfer changed during cancellation activation.');
            }

            $activate = $db->prepare(
                "UPDATE novice_promotion_derived_balance
                 SET STATUS = 'ACTIVE', AVAILABLE_PROMOTIONAL_AMOUNT = ?,
                     ISSUED_AT = ?, EXPIRES_AT = ?, POLICY_SNAPSHOT_JSON = ?
                 WHERE UUID_DERIVED_BALANCE = ?
                   AND STATUS = 'PENDING_FISCAL_REVIEW'
                   AND AVAILABLE_PROMOTIONAL_AMOUNT = 0"
            );
            $activate->execute([
                (string) $plan['promotional_derived_amount'],
                $issuedUtc,
                $expiryUtc,
                json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                $uuidDerivedReview,
            ]);
            if ($activate->rowCount() !== 1) {
                throw SifException::conflict('Transferred cancellation activation changed concurrently.');
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
                'DERIVED_ACTIVATE_TRANSFER',
                'SUCCESS',
                (string) $transfer['TO_UUID_OPERATION'],
                'SECRETARIAT',
                (string) $approval['reviewer_id'],
                $uuidDerivedReview,
                (string) $approval['decision_id'],
                'TRANSFERRED_COURSE_CONVERTED_TO_DERIVED',
                json_encode([
                    'uuid_derived_balance' => $uuidDerivedReview,
                    'source_uuid_transfer' => (string) $transfer['UUID_TRANSFER'],
                    'parent_uuid_derived_balance' => $expectedParentDerivedBalance,
                    'uuid_rectificative' => (string) $review['UUID_RECTIFICATIVE_FACTURA'],
                    'promotional_amount' => (string) $plan['promotional_derived_amount'],
                    'promotional_forfeited_amount' => (string) $plan['promotional_forfeited'],
                    'cash_amount_routed_separately' => (string) $plan['cash_refund_or_credit_eligible'],
                    'expires_at_utc' => $expiryUtc,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                $timestamp,
            ]);

            $db->commit();
            return [
                'uuid_derived_balance' => $uuidDerivedReview,
                'parent_uuid_derived_balance' => $expectedParentDerivedBalance,
                'source_uuid_transfer' => (string) $transfer['UUID_TRANSFER'],
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

    /**
     * Resolve the derived right carried by this transfer chain.
     * NULL means the chain carries the original JASOM promotional right.
     */
    private function resolveTransferParentBalanceUuid(
        \PDO $db,
        array $transfer
    ): ?string {
        $current = $transfer;
        $visited = [];

        while (true) {
            $transferId = trim((string) ($current['UUID_TRANSFER'] ?? ''));
            if ($transferId === '' || isset($visited[$transferId])) {
                throw SifException::conflict(
                    'Transfer lineage is cyclic or missing during cancellation activation.'
                );
            }
            $visited[$transferId] = true;

            $original = trim(
                (string) ($current['UUID_ORIGINAL_APPLICATION'] ?? '')
            );
            $derived = trim(
                (string) ($current['UUID_DERIVED_APPLICATION'] ?? '')
            );
            $previous = trim(
                (string) ($current['PREVIOUS_UUID_TRANSFER'] ?? '')
            );
            $nonEmpty = ($original !== '' ? 1 : 0)
                + ($derived !== '' ? 1 : 0)
                + ($previous !== '' ? 1 : 0);
            if ($nonEmpty !== 1) {
                throw SifException::conflict(
                    'Transfer has ambiguous promotional predecessor.'
                );
            }

            if ($original !== '') {
                return null;
            }

            if ($derived !== '') {
                $source = $this->one(
                    $db,
                    'SELECT UUID_DERIVED_BALANCE, ROOT_UUID_ENTITLEMENT
                     FROM novice_promotion_derived_application
                     WHERE UUID_DERIVED_APPLICATION = ?
                     FOR UPDATE',
                    [$derived]
                );
                if ($source === null
                    || (string) $source['ROOT_UUID_ENTITLEMENT']
                        !== (string) $current['ROOT_UUID_ENTITLEMENT']
                ) {
                    throw SifException::conflict(
                        'Transferred cancellation cannot resolve its derived parent.'
                    );
                }
                return (string) $source['UUID_DERIVED_BALANCE'];
            }

            $parentTransfer = $this->one(
                $db,
                'SELECT * FROM novice_promotion_application_transfer
                 WHERE UUID_TRANSFER = ? FOR UPDATE',
                [$previous]
            );
            if ($parentTransfer === null
                || (string) $parentTransfer['ROOT_UUID_ENTITLEMENT']
                    !== (string) $current['ROOT_UUID_ENTITLEMENT']
            ) {
                throw SifException::conflict(
                    'Transferred cancellation has a missing predecessor transfer.'
                );
            }
            $current = $parentTransfer;
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
            throw SifException::conflict('Original JASOM is not an active fully paid origin.');
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

        $invoiced = 0;
        foreach ($invoices as $invoice) {
            if ($invoice['ESTAT_FACTURA'] !== 'ISSUED'
                || $invoice['ESTAT_COBRAMENT'] !== 'PAID'
            ) {
                throw SifException::conflict('Original JASOM has an unpaid or nonissued invoice.');
            }
            $total = $this->cents((string) $invoice['TOTAL']);
            if ($total <= 0) {
                throw SifException::conflict('Original JASOM invoice amount is invalid.');
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
                throw SifException::conflict('Original JASOM has a confirmed refund or incomplete settlement.');
            }
            $invoiced += $total;
        }

        if ($invoiced <= 0
            || $invoiced !== $this->cents((string) $origin['NET_AMOUNT'])
        ) {
            throw SifException::conflict('Original JASOM no longer matches its fully paid invoices.');
        }
    }

    private function money(int $cents): string
    {
        if ($cents < 0) {
            throw SifException::validation('Negative transferred cancellation cash is invalid.');
        }
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function cents(string $amount): int
    {
        if (!preg_match('/^(-?)(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($amount), $match)) {
            throw SifException::validation('Invalid transferred cancellation amount.');
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
