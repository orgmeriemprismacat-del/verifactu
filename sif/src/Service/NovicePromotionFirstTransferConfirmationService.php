<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\NovicePromotionApprovedTransferPolicy;
use Prisma\Sif\Domain\NovicePromotionRectificationEvidencePolicy;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

/**
 * UC-111: finalize ONE original course-change attribution after the NEW
 * invoice/fiscal price and authorized transfer decision are already final.
 *
 * A transfer is NOT a new promotion grant or additional consumption:
 * original application becomes historical REVERSED/TRANSFERRED_TO_COURSE
 * and the CONFIRMED transfer identifies where that same amount is NOW used.
 * Later successive transfers and destination cancellations must follow the
 * current transfer, rather than its historical original course.
 *
 * The authenticated/authorized approval evidence comes ONLY via the trusted
 * backoffice source; this branch has NO production approval adapter, public
 * controller, final-price writer or real invoice emitter for this workflow.
 */
final class NovicePromotionFirstTransferConfirmationService
{
    public function __construct(
        private NovicePromotionAdjustmentApprovalSourceInterface $approvals,
        private NovicePromotionRectificationEvidencePolicy $fiscal = new NovicePromotionRectificationEvidencePolicy(),
        private NovicePromotionApprovedTransferPolicy $decisions = new NovicePromotionApprovedTransferPolicy(),
        private UuidGenerator $uuids = new UuidGenerator()
    ) {
    }

    public function confirmApprovedFirstTransfer(
        \PDO $db,
        string $uuidTransfer,
        ?\DateTimeImmutable $now = null
    ): array {
        if ($db->inTransaction()) {
            throw new \LogicException('Course-change confirmation requires an independent SIF transaction.');
        }
        if (trim($uuidTransfer) === '') {
            throw SifException::validation('Course transfer review identifier is required.');
        }

        $approval = $this->approvals->approvedFirstTransfer($uuidTransfer);
        if (!is_array($approval)
            || (string) ($approval['review_uuid'] ?? '') !== $uuidTransfer
            || (string) ($approval['decision_type'] ?? '') !== 'NOVICE_DESTINATION_TRANSFER'
            || (string) ($approval['decision'] ?? '') !== 'APPROVED'
        ) {
            throw SifException::conflict('No independently finalized course-change approval is available.');
        }
        foreach ([
            'decision_id', 'reviewer_id', 'evidence_ref', 'approved_at_utc',
            'uuid_original_application', 'uuid_rectificative',
            'uuid_new_operation', 'approved_promotional_amount',
        ] as $field) {
            if (!isset($approval[$field]) || !is_string($approval[$field])
                || trim($approval[$field]) === ''
            ) {
                throw SifException::conflict('Authorized course-change approval lacks binding evidence.');
            }
        }
        if (strlen($approval['decision_id']) > 100
            || strlen($approval['reviewer_id']) > 100
            || strlen($approval['evidence_ref']) > 140
        ) {
            throw SifException::conflict('Course-change approval identifier exceeds its allowed length.');
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $timestamp = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $approvedAt = $approval['approved_at_utc'];
        if (preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $approvedAt) !== 1
            || $approvedAt > $timestamp
        ) {
            throw SifException::conflict('Course-change approval has an invalid finalized timestamp.');
        }

        $db->beginTransaction();
        try {
            $lookup = $this->one(
                $db,
                'SELECT ROOT_UUID_ENTITLEMENT FROM novice_promotion_application_transfer
                 WHERE UUID_TRANSFER = ?',
                [$uuidTransfer]
            );
            if ($lookup === null) {
                throw SifException::conflict('Course-change review does not exist.');
            }

            $right = $this->one(
                $db,
                'SELECT e.UUID_ENTITLEMENT, e.HOLDER_PARTY_KEY, e.STATUS,
                        g.ORIGIN_UUID_OPERATION, v.STATUS AS VALIDATION_STATUS
                 FROM commercial_entitlement e
                 JOIN novice_promotion_grant g ON g.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
                 JOIN discount_validation v ON v.UUID_VALIDATION = g.UUID_VALIDATION
                 WHERE e.UUID_ENTITLEMENT = ? FOR UPDATE',
                [(string) $lookup['ROOT_UUID_ENTITLEMENT']]
            );
            if ($right === null) {
                throw SifException::conflict('Original novice right does not exist.');
            }

            $transfer = $this->one(
                $db,
                'SELECT * FROM novice_promotion_application_transfer
                 WHERE UUID_TRANSFER = ? FOR UPDATE',
                [$uuidTransfer]
            );
            if ($transfer === null
                || (string) $transfer['ROOT_UUID_ENTITLEMENT'] !== (string) $right['UUID_ENTITLEMENT']
            ) {
                throw SifException::conflict('Course-change root changed during confirmation.');
            }

            if ($transfer['STATUS'] === 'CONFIRMED') {
                if ((string) $transfer['APPROVAL_DECISION_ID'] !== $approval['decision_id']) {
                    throw SifException::conflict('Another approval already confirmed this course change.');
                }
                $db->commit();
                return [
                    'uuid_transfer' => $uuidTransfer,
                    'status' => 'CONFIRMED',
                    'uuid_destination_factura' => (string) $transfer['UUID_DESTINATION_FACTURA'],
                    'idempotency_reused' => true,
                ];
            }

            if ($transfer['STATUS'] !== 'PENDING_FISCAL_REVIEW'
                || $right['STATUS'] !== 'ACTIVE'
                || $right['VALIDATION_STATUS'] !== 'VALIDATED'
                || $transfer['PREVIOUS_UUID_TRANSFER'] !== null
                || $transfer['UUID_DERIVED_APPLICATION'] !== null
                || trim((string) ($transfer['UUID_ORIGINAL_APPLICATION'] ?? '')) === ''
                || (string) $transfer['UUID_ORIGINAL_APPLICATION'] !== $approval['uuid_original_application']
                || (string) $transfer['UUID_RECTIFICATIVE_FACTURA'] !== $approval['uuid_rectificative']
                || (string) $transfer['TO_UUID_OPERATION'] !== $approval['uuid_new_operation']
                || (string) $transfer['AMOUNT'] !== $approval['approved_promotional_amount']
                || (string) $transfer['POLICY_EVIDENCE_REF'] !== $approval['evidence_ref']
                || $approvedAt < (string) $transfer['CREATED_AT']
            ) {
                throw SifException::conflict('Approved first transfer differs from its pending course-change review.');
            }

            try {
                $this->decisions->assertMatches($approval, $uuidTransfer, $transfer, $timestamp);
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('Final course-change approval differs from its immutable review.');
            }

            $original = $this->one(
                $db,
                'SELECT * FROM novice_promotion_application
                 WHERE UUID_APPLICATION = ? FOR UPDATE',
                [(string) $transfer['UUID_ORIGINAL_APPLICATION']]
            );
            if ($original === null || $original['STATUS'] !== 'APPLIED'
                || (string) $original['UUID_ENTITLEMENT'] !== (string) $right['UUID_ENTITLEMENT']
                || (string) $original['UUID_DESTINATION_OPERATION'] !== (string) $transfer['FROM_UUID_OPERATION']
                || (string) $original['AMOUNT'] !== (string) $transfer['AMOUNT']
                || trim((string) ($original['UUID_DESTINATION_FACTURA'] ?? '')) === ''
            ) {
                throw SifException::conflict('Previously applied original promotion is not available for transfer.');
            }

            if ($this->one(
                $db,
                'SELECT UUID_DERIVED_BALANCE FROM novice_promotion_derived_balance
                 WHERE SOURCE_UUID_APPLICATION = ? FOR UPDATE',
                [(string) $original['UUID_APPLICATION']]
            ) !== null) {
                throw SifException::conflict('A cancellation review exists for the historical course.');
            }

            $oldOperation = $this->one(
                $db,
                'SELECT UUID_OPERATION, UUID_FACTURA, SOURCE_TYPE
                 FROM commercial_operation WHERE UUID_OPERATION = ? FOR UPDATE',
                [(string) $transfer['FROM_UUID_OPERATION']]
            );
            $destination = $this->one(
                $db,
                'SELECT UUID_OPERATION, UUID_FACTURA, SOURCE_ID, SOURCE_TYPE,
                        PRODUCT_TYPE, CURRENCY, STATUS,
                        NET_AMOUNT, PRICE_SNAPSHOT_JSON, GROSS_AMOUNT, DISCOUNT_AMOUNT
                 FROM commercial_operation WHERE UUID_OPERATION = ? FOR UPDATE',
                [(string) $transfer['TO_UUID_OPERATION']]
            );
            if ($oldOperation === null || $destination === null
                || (string) $oldOperation['UUID_FACTURA'] !== (string) $original['UUID_DESTINATION_FACTURA']
                || $oldOperation['SOURCE_TYPE'] !== 'CURS'
                || $destination['SOURCE_TYPE'] !== 'CURS'
                || $destination['PRODUCT_TYPE'] !== 'CURS'
                || $destination['CURRENCY'] !== 'EUR'
                || !in_array((string) $destination['STATUS'], ['PAID', 'COMPLETED'], true)
                || trim((string) ($destination['UUID_FACTURA'] ?? '')) === ''
                || (string) $destination['UUID_OPERATION'] === (string) $oldOperation['UUID_OPERATION']
            ) {
                throw SifException::conflict('Replacement course has no committed, final paid operation.');
            }
            $people = $this->many(
                $db,
                "SELECT PARTY_KEY FROM commercial_operation_party
                 WHERE UUID_OPERATION = ? AND PARTY_ROLE = 'PARTICIPANT' FOR UPDATE",
                [(string) $destination['UUID_OPERATION']]
            );
            if (count($people) !== 1
                || (string) $people[0]['PARTY_KEY'] !== (string) $right['HOLDER_PARTY_KEY']
            ) {
                throw SifException::conflict('Course change has altered the promotion holder.');
            }

            // The original course's already issued rectificative MUST still
            // point to its original discounted invoice, not an arbitrary R1.
            $oldInvoice = $this->one(
                $db,
                'SELECT UUID_FACTURA, TIPUS_FACTURA, ESTAT_FACTURA, DATA_EMISSIO
                 FROM factura WHERE UUID_FACTURA = ? FOR UPDATE',
                [(string) $original['UUID_DESTINATION_FACTURA']]
            );
            $rectificative = $this->one(
                $db,
                'SELECT UUID_FACTURA, TIPUS_FACTURA, ESTAT_FACTURA, DATA_EMISSIO
                 FROM factura WHERE UUID_FACTURA = ? FOR UPDATE',
                [(string) $transfer['UUID_RECTIFICATIVE_FACTURA']]
            );
            $fiscalLink = $this->one(
                $db,
                'SELECT UUID_FACTURA_RECTIFICADA, UUID_FACTURA_RECTIFICATIVA,
                        MOTIU, MODE_RECTIFICACIO
                 FROM factura_rectificacio
                 WHERE UUID_FACTURA_RECTIFICATIVA = ? AND UUID_FACTURA_RECTIFICADA = ?
                 FOR UPDATE',
                [(string) $transfer['UUID_RECTIFICATIVE_FACTURA'],
                 (string) $original['UUID_DESTINATION_FACTURA']]
            );
            try {
                $this->fiscal->assertCancellationReference(
                    $oldInvoice ?? [], $rectificative ?? [], $fiscalLink ?? []
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('Course-change fiscal rectificative no longer matches the origin.');
            }

            $snapshot = json_decode((string) $destination['PRICE_SNAPSHOT_JSON'], true, 512, JSON_THROW_ON_ERROR);
            $pricing = is_array($snapshot) ? ($snapshot['novice_promotion_transfer'] ?? null) : null;
            $amount = $this->cents((string) $transfer['AMOUNT']);
            $finalNet = $this->cents((string) $destination['NET_AMOUNT']);
            if (!is_array($pricing)
                || ($pricing['uuid_transfer'] ?? null) !== $uuidTransfer
                || ($pricing['uuid_original_application'] ?? null) !== (string) $original['UUID_APPLICATION']
                || !isset($pricing['amount'], $pricing['ordinary_net_before_promotion'])
                || $this->cents((string) $pricing['amount']) !== $amount
                || $this->cents((string) $pricing['ordinary_net_before_promotion']) < $amount
                || $this->cents((string) $pricing['ordinary_net_before_promotion']) - $amount !== $finalNet
                || $this->cents((string) $destination['GROSS_AMOUNT'])
                    - $this->cents((string) $destination['DISCOUNT_AMOUNT']) !== $finalNet
            ) {
                throw SifException::conflict('Final replacement price does not document this exact prior promotional value.');
            }
            $ordinaryNet = $this->cents((string) $pricing['ordinary_net_before_promotion']);

            $finalInvoice = $this->one(
                $db,
                'SELECT UUID_FACTURA, TIPUS_FACTURA, ESTAT_FACTURA,
                        ESTAT_COBRAMENT, TOTAL
                 FROM factura WHERE UUID_FACTURA = ? FOR UPDATE',
                [(string) $destination['UUID_FACTURA']]
            );
            if ($finalInvoice === null
                || !in_array((string) $finalInvoice['TIPUS_FACTURA'], ['F1', 'F2'], true)
                || $finalInvoice['ESTAT_FACTURA'] !== 'ISSUED'
                || $finalInvoice['ESTAT_COBRAMENT'] !== 'PAID'
                || $finalNet < 0
                || $this->cents((string) $finalInvoice['TOTAL']) !== $finalNet
            ) {
                throw SifException::conflict('Replacement invoice is not issued and fully settled at the final price.');
            }
            $sourceId = trim((string) ($destination['SOURCE_ID'] ?? ''));
            if ($sourceId === '' || !ctype_digit($sourceId) || (int) $sourceId < 1
                || $this->one(
                    $db,
                    "SELECT ID FROM fact_rels WHERE UUID_FACTURA = ?
                     AND SOURCE_TYPE = 'INSCRIPCIO' AND SOURCE_ID = ? LIMIT 1",
                    [(string) $finalInvoice['UUID_FACTURA'], (int) $sourceId]
                ) === null
            ) {
                throw SifException::conflict('Final replacement invoice is not related to its enrollment.');
            }

            // Residual 0 means no bank transaction. No fabricated promotion
            // payment can exist in payment_transaction or credit_balance.
            $settlement = $this->one(
                $db,
                "SELECT COALESCE(SUM(CASE
                    WHEN pt.TIPUS_MOVIMENT = 'CHARGE' THEN pa.IMPORT_ASSIGNAT
                    WHEN pt.TIPUS_MOVIMENT = 'REFUND' THEN -pa.IMPORT_ASSIGNAT
                    ELSE 0 END), 0) AS NET_CASH
                 FROM payment_allocation pa
                 JOIN payment_transaction pt ON pt.UUID_PAYMENT = pa.UUID_PAYMENT
                 WHERE pa.UUID_FACTURA = ? AND pt.ESTAT = 'CONFIRMED'",
                [(string) $finalInvoice['UUID_FACTURA']]
            );
            if ($this->cents((string) ($settlement['NET_CASH'] ?? '0.00')) !== $finalNet) {
                throw SifException::conflict('Replacement invoice and confirmed external cash do not reconcile.');
            }

            $this->assertOriginalJasomStillPaid($db, (string) $right['ORIGIN_UUID_OPERATION']);

            // Close only HISTORICAL attribution. The same promotional amount
            // is carried by the confirmed transfer record to the NEW course,
            // with no balance debit and no second novice application row.
            $closed = $db->prepare(
                "UPDATE novice_promotion_application
                 SET STATUS = 'REVERSED', REVERSED_AT = ?,
                     REASON_CODE = 'TRANSFERRED_TO_COURSE'
                 WHERE UUID_APPLICATION = ? AND STATUS = 'APPLIED'"
            );
            $closed->execute([$timestamp, (string) $original['UUID_APPLICATION']]);
            if ($closed->rowCount() !== 1) {
                throw SifException::conflict('Original promotional application changed during transfer.');
            }

            $update = $db->prepare(
                "UPDATE novice_promotion_application_transfer
                 SET STATUS = 'CONFIRMED', CONFIRMED_AT = ?,
                     UUID_DESTINATION_FACTURA = ?, FINAL_NET_AMOUNT = ?,
                     ORDINARY_NET_BEFORE_PROMOTION = ?, APPROVAL_DECISION_ID = ?,
                     APPROVED_BY = ?, APPROVED_AT = ?
                 WHERE UUID_TRANSFER = ? AND STATUS = 'PENDING_FISCAL_REVIEW'"
            );
            $update->execute([
                $timestamp,
                (string) $finalInvoice['UUID_FACTURA'],
                $this->money($finalNet),
                $this->money($ordinaryNet),
                (string) $approval['decision_id'],
                (string) $approval['reviewer_id'],
                $approvedAt,
                $uuidTransfer,
            ]);
            if ($update->rowCount() !== 1) {
                throw SifException::conflict('Course-change approval changed during confirmation.');
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
                (string) $right['UUID_ENTITLEMENT'],
                'TRANSFER',
                'SUCCESS',
                (string) $destination['UUID_OPERATION'],
                'SECRETARIAT',
                (string) $approval['reviewer_id'],
                $uuidTransfer,
                (string) $approval['decision_id'],
                'NOVICE_COURSE_TRANSFER_CONFIRMED',
                json_encode([
                    'uuid_transfer' => $uuidTransfer,
                    'uuid_original_application' => (string) $original['UUID_APPLICATION'],
                    'from_uuid_operation' => (string) $oldOperation['UUID_OPERATION'],
                    'to_uuid_operation' => (string) $destination['UUID_OPERATION'],
                    'uuid_rectificative' => (string) $transfer['UUID_RECTIFICATIVE_FACTURA'],
                    'uuid_final_invoice' => (string) $finalInvoice['UUID_FACTURA'],
                    'promotional_amount_moved_not_redebited' => (string) $transfer['AMOUNT'],
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                $timestamp,
            ]);

            $db->commit();
            return [
                'uuid_transfer' => $uuidTransfer,
                'status' => 'CONFIRMED',
                'uuid_destination_factura' => (string) $finalInvoice['UUID_FACTURA'],
                'promotional_amount_moved' => (string) $transfer['AMOUNT'],
                'idempotency_reused' => false,
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

    private function money(int $cents): string
    {
        if ($cents < 0) {
            throw SifException::validation('Negative promotional amount is not allowed.');
        }
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
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
