<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\NovicePromotionApprovedSuccessiveTransferPolicy;
use Prisma\Sif\Domain\NovicePromotionRectificationEvidencePolicy;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

/**
 * UC-111 / DEC-18: confirm a transfer from the CURRENT promotional exposure.
 *
 * Source may be:
 * - an APPLIED derived-balance application, or
 * - a CONFIRMED predecessor transfer.
 *
 * Confirmation moves attribution only. It closes the predecessor and marks
 * this transfer CONFIRMED in one transaction; it NEVER debits/restores a
 * promotional balance, creates a new grant or fabricates bank money.
 */
final class NovicePromotionSuccessiveTransferConfirmationService
{
    public function __construct(
        private NovicePromotionAdjustmentApprovalSourceInterface $approvals,
        private NovicePromotionApprovedSuccessiveTransferPolicy $decisions = new NovicePromotionApprovedSuccessiveTransferPolicy(),
        private NovicePromotionRectificationEvidencePolicy $fiscal = new NovicePromotionRectificationEvidencePolicy(),
        private UuidGenerator $uuids = new UuidGenerator()
    ) {
    }

    public function confirmApprovedSuccessiveTransfer(
        \PDO $db,
        string $uuidTransfer,
        ?\DateTimeImmutable $now = null
    ): array {
        if ($db->inTransaction()) {
            throw new \LogicException('Successive transfer confirmation requires its own SIF transaction.');
        }
        if (trim($uuidTransfer) === '') {
            throw SifException::validation('Successive transfer identifier is required.');
        }

        $approval = $this->approvals->approvedSuccessiveTransfer($uuidTransfer);
        if (!is_array($approval)
            || (string) ($approval['review_uuid'] ?? '') !== $uuidTransfer
            || (string) ($approval['decision_type'] ?? '') !== 'NOVICE_SUCCESSIVE_DESTINATION_TRANSFER'
            || (string) ($approval['decision'] ?? '') !== 'APPROVED'
        ) {
            throw SifException::conflict('No independently finalized successive transfer approval is available.');
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $timestamp = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $db->beginTransaction();
        try {
            $lookup = $this->one(
                $db,
                'SELECT ROOT_UUID_ENTITLEMENT
                 FROM novice_promotion_application_transfer
                 WHERE UUID_TRANSFER = ?',
                [$uuidTransfer]
            );
            if ($lookup === null) {
                throw SifException::conflict('Successive transfer review does not exist.');
            }

            $root = $this->lockRoot(
                $db,
                (string) $lookup['ROOT_UUID_ENTITLEMENT']
            );

            $transfer = $this->one(
                $db,
                'SELECT * FROM novice_promotion_application_transfer
                 WHERE UUID_TRANSFER = ? FOR UPDATE',
                [$uuidTransfer]
            );
            if ($transfer === null
                || (string) $transfer['ROOT_UUID_ENTITLEMENT']
                    !== (string) $root['UUID_ENTITLEMENT']
            ) {
                throw SifException::conflict('Successive transfer root changed during confirmation.');
            }

            if ($transfer['STATUS'] === 'CONFIRMED') {
                if ((string) $transfer['APPROVAL_DECISION_ID']
                    !== (string) ($approval['decision_id'] ?? '')
                ) {
                    throw SifException::conflict('Another decision already confirmed this successive transfer.');
                }
                $db->commit();
                return [
                    'uuid_transfer' => $uuidTransfer,
                    'status' => 'CONFIRMED',
                    'uuid_destination_factura'
                        => (string) $transfer['UUID_DESTINATION_FACTURA'],
                    'idempotency_reused' => true,
                ];
            }

            if ($transfer['STATUS'] !== 'PENDING_FISCAL_REVIEW'
                || $root['STATUS'] !== 'ACTIVE'
                || $root['VALIDATION_STATUS'] !== 'VALIDATED'
                || $transfer['UUID_ORIGINAL_APPLICATION'] !== null
            ) {
                throw SifException::conflict('Only a pending non-original transfer can be confirmed here.');
            }

            [$sourceKind, $sourceId, $source] = $this->lockCurrentSource(
                $db,
                $transfer,
                (string) $root['UUID_ENTITLEMENT']
            );

            try {
                $this->decisions->assertMatches(
                    $approval,
                    $uuidTransfer,
                    $transfer,
                    $sourceKind,
                    $sourceId,
                    $timestamp
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('Final successive transfer approval differs from its immutable review.');
            }

            if ((string) $source['operation_uuid'] !== (string) $transfer['FROM_UUID_OPERATION']
                || (string) $source['amount'] !== (string) $transfer['AMOUNT']
                || trim((string) $source['invoice_uuid']) === ''
            ) {
                throw SifException::conflict('Current promotional exposure changed after transfer review.');
            }

            $sourceOperation = $this->one(
                $db,
                'SELECT UUID_OPERATION, SOURCE_TYPE, PRODUCT_TYPE, CURRENCY,
                        SOURCE_ID, UUID_FACTURA
                 FROM commercial_operation WHERE UUID_OPERATION = ? FOR UPDATE',
                [$source['operation_uuid']]
            );
            if ($sourceOperation === null
                || $sourceOperation['SOURCE_TYPE'] !== 'CURS'
                || $sourceOperation['PRODUCT_TYPE'] !== 'CURS'
                || $sourceOperation['CURRENCY'] !== 'EUR'
                || (string) $sourceOperation['UUID_FACTURA'] !== $source['invoice_uuid']
            ) {
                throw SifException::conflict('Current source course no longer matches its final invoice.');
            }

            $sourceInvoice = $this->one(
                $db,
                'SELECT UUID_FACTURA, TIPUS_FACTURA, ESTAT_FACTURA, DATA_EMISSIO
                 FROM factura WHERE UUID_FACTURA = ? FOR UPDATE',
                [$source['invoice_uuid']]
            );
            $rectificative = $this->one(
                $db,
                'SELECT UUID_FACTURA, TIPUS_FACTURA, ESTAT_FACTURA, DATA_EMISSIO
                 FROM factura WHERE UUID_FACTURA = ? FOR UPDATE',
                [(string) $transfer['UUID_RECTIFICATIVE_FACTURA']]
            );
            $link = $this->one(
                $db,
                'SELECT UUID_FACTURA_RECTIFICADA, UUID_FACTURA_RECTIFICATIVA,
                        MOTIU, MODE_RECTIFICACIO
                 FROM factura_rectificacio
                 WHERE UUID_FACTURA_RECTIFICATIVA = ?
                   AND UUID_FACTURA_RECTIFICADA = ? FOR UPDATE',
                [(string) $transfer['UUID_RECTIFICATIVE_FACTURA'],
                 $source['invoice_uuid']]
            );
            try {
                $this->fiscal->assertCancellationReference(
                    $sourceInvoice ?? [],
                    $rectificative ?? [],
                    $link ?? []
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('Successive transfer rectificative no longer matches the current source invoice.');
            }

            $destination = $this->one(
                $db,
                'SELECT * FROM commercial_operation
                 WHERE UUID_OPERATION = ? FOR UPDATE',
                [(string) $transfer['TO_UUID_OPERATION']]
            );
            if ($destination === null
                || $destination['SOURCE_TYPE'] !== 'CURS'
                || $destination['PRODUCT_TYPE'] !== 'CURS'
                || $destination['CURRENCY'] !== 'EUR'
                || !in_array((string) $destination['STATUS'], ['PAID', 'COMPLETED'], true)
                || trim((string) ($destination['UUID_FACTURA'] ?? '')) === ''
                || (string) $destination['UUID_OPERATION'] === (string) $source['operation_uuid']
            ) {
                throw SifException::conflict('Successive replacement has no committed final course operation.');
            }

            foreach ([$source['operation_uuid'], (string) $destination['UUID_OPERATION']] as $op) {
                $people = $this->many(
                    $db,
                    "SELECT PARTY_KEY FROM commercial_operation_party
                     WHERE UUID_OPERATION = ? AND PARTY_ROLE = 'PARTICIPANT' FOR UPDATE",
                    [$op]
                );
                if (count($people) !== 1
                    || (string) $people[0]['PARTY_KEY']
                        !== (string) $root['HOLDER_PARTY_KEY']
                ) {
                    throw SifException::conflict('Successive transfer changed the promotion holder.');
                }
            }

            $snapshot = json_decode(
                (string) $destination['PRICE_SNAPSHOT_JSON'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            $pricing = is_array($snapshot)
                ? ($snapshot['novice_promotion_transfer'] ?? null)
                : null;
            $amount = $this->cents((string) $transfer['AMOUNT']);
            $finalNet = $this->cents((string) $destination['NET_AMOUNT']);
            if (!is_array($pricing)
                || ($pricing['uuid_transfer'] ?? '') !== $uuidTransfer
                || ($pricing['source_kind'] ?? '') !== $sourceKind
                || ($pricing['source_id'] ?? '') !== $sourceId
                || !isset($pricing['amount'], $pricing['ordinary_net_before_promotion'])
                || $this->cents((string) $pricing['amount']) !== $amount
                || $this->cents((string) $pricing['ordinary_net_before_promotion']) < $amount
                || $this->cents((string) $pricing['ordinary_net_before_promotion'])
                    - $amount !== $finalNet
                || $this->cents((string) $destination['GROSS_AMOUNT'])
                    - $this->cents((string) $destination['DISCOUNT_AMOUNT'])
                    !== $finalNet
            ) {
                throw SifException::conflict('Final successive transfer price does not identify the exact predecessor value.');
            }
            $ordinaryNet = $this->cents(
                (string) $pricing['ordinary_net_before_promotion']
            );

            $invoice = $this->one(
                $db,
                'SELECT UUID_FACTURA, TIPUS_FACTURA, ESTAT_FACTURA,
                        ESTAT_COBRAMENT, TOTAL
                 FROM factura WHERE UUID_FACTURA = ? FOR UPDATE',
                [(string) $destination['UUID_FACTURA']]
            );
            if ($invoice === null
                || !in_array((string) $invoice['TIPUS_FACTURA'], ['F1', 'F2'], true)
                || $invoice['ESTAT_FACTURA'] !== 'ISSUED'
                || $invoice['ESTAT_COBRAMENT'] !== 'PAID'
                || $finalNet < 0
                || $this->cents((string) $invoice['TOTAL']) !== $finalNet
            ) {
                throw SifException::conflict('Successive destination invoice is not final and fully settled.');
            }

            $sourceIdEnrollment = trim((string) ($destination['SOURCE_ID'] ?? ''));
            if ($sourceIdEnrollment === '' || !ctype_digit($sourceIdEnrollment)
                || $this->one(
                    $db,
                    "SELECT ID FROM fact_rels WHERE UUID_FACTURA = ?
                     AND SOURCE_TYPE = 'INSCRIPCIO' AND SOURCE_ID = ? LIMIT 1",
                    [(string) $invoice['UUID_FACTURA'], (int) $sourceIdEnrollment]
                ) === null
            ) {
                throw SifException::conflict('Successive destination invoice is not tied to its enrollment.');
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
                !== $finalNet
            ) {
                throw SifException::conflict('Successive destination residual does not match confirmed external cash.');
            }

            $this->assertOriginalJasomStillPaid(
                $db,
                (string) $root['ORIGIN_UUID_OPERATION']
            );

            $this->closeSource(
                $db,
                $sourceKind,
                $sourceId,
                $timestamp
            );

            $update = $db->prepare(
                "UPDATE novice_promotion_application_transfer
                 SET STATUS = 'CONFIRMED', CONFIRMED_AT = ?,
                     UUID_DESTINATION_FACTURA = ?, FINAL_NET_AMOUNT = ?,
                     ORDINARY_NET_BEFORE_PROMOTION = ?,
                     APPROVAL_DECISION_ID = ?, APPROVED_BY = ?, APPROVED_AT = ?
                 WHERE UUID_TRANSFER = ?
                   AND STATUS = 'PENDING_FISCAL_REVIEW'"
            );
            $update->execute([
                $timestamp,
                (string) $invoice['UUID_FACTURA'],
                $this->money($finalNet),
                $this->money($ordinaryNet),
                (string) $approval['decision_id'],
                (string) $approval['reviewer_id'],
                (string) $approval['approved_at_utc'],
                $uuidTransfer,
            ]);
            if ($update->rowCount() !== 1) {
                throw SifException::conflict('Successive transfer changed during confirmation.');
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
                'TRANSFER',
                'SUCCESS',
                (string) $destination['UUID_OPERATION'],
                'SECRETARIAT',
                (string) $approval['reviewer_id'],
                $uuidTransfer,
                (string) $approval['decision_id'],
                'NOVICE_SUCCESSIVE_TRANSFER_CONFIRMED',
                json_encode([
                    'uuid_transfer' => $uuidTransfer,
                    'source_kind' => $sourceKind,
                    'source_id' => $sourceId,
                    'from_uuid_operation' => (string) $source['operation_uuid'],
                    'to_uuid_operation' => (string) $destination['UUID_OPERATION'],
                    'uuid_rectificative'
                        => (string) $transfer['UUID_RECTIFICATIVE_FACTURA'],
                    'uuid_final_invoice' => (string) $invoice['UUID_FACTURA'],
                    'promotional_amount_moved_not_redebited'
                        => (string) $transfer['AMOUNT'],
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                $timestamp,
            ]);

            $db->commit();
            return [
                'uuid_transfer' => $uuidTransfer,
                'source_kind' => $sourceKind,
                'source_id' => $sourceId,
                'status' => 'CONFIRMED',
                'uuid_destination_factura' => (string) $invoice['UUID_FACTURA'],
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

    private function lockCurrentSource(
        \PDO $db,
        array $transfer,
        string $rootUuid
    ): array {
        $previous = trim((string) ($transfer['PREVIOUS_UUID_TRANSFER'] ?? ''));
        $derived = trim((string) ($transfer['UUID_DERIVED_APPLICATION'] ?? ''));

        if ($previous !== '' && $derived === '') {
            $row = $this->one(
                $db,
                'SELECT ROOT_UUID_ENTITLEMENT, TO_UUID_OPERATION,
                        UUID_DESTINATION_FACTURA, AMOUNT, STATUS
                 FROM novice_promotion_application_transfer
                 WHERE UUID_TRANSFER = ? FOR UPDATE',
                [$previous]
            );
            if ($row === null
                || $row['STATUS'] !== 'CONFIRMED'
                || (string) $row['ROOT_UUID_ENTITLEMENT'] !== $rootUuid
                || trim((string) ($row['UUID_DESTINATION_FACTURA'] ?? '')) === ''
            ) {
                throw SifException::conflict('Previous transfer is no longer the current confirmed exposure.');
            }
            if ($this->one(
                $db,
                'SELECT UUID_DERIVED_BALANCE FROM novice_promotion_derived_balance
                 WHERE SOURCE_UUID_TRANSFER = ? FOR UPDATE',
                [$previous]
            ) !== null) {
                throw SifException::conflict('Previous transfer already became a cancellation-derived balance.');
            }
            return [
                'PREVIOUS_TRANSFER',
                $previous,
                [
                    'operation_uuid' => (string) $row['TO_UUID_OPERATION'],
                    'invoice_uuid' => (string) $row['UUID_DESTINATION_FACTURA'],
                    'amount' => (string) $row['AMOUNT'],
                ],
            ];
        }

        if ($derived !== '' && $previous === '') {
            $row = $this->one(
                $db,
                'SELECT a.ROOT_UUID_ENTITLEMENT, a.UUID_DESTINATION_OPERATION,
                        a.UUID_DESTINATION_FACTURA, a.AMOUNT, a.STATUS,
                        b.HOLDER_PARTY_KEY
                 FROM novice_promotion_derived_application a
                 JOIN novice_promotion_derived_balance b
                   ON b.UUID_DERIVED_BALANCE = a.UUID_DERIVED_BALANCE
                 WHERE a.UUID_DERIVED_APPLICATION = ? FOR UPDATE',
                [$derived]
            );
            if ($row === null
                || $row['STATUS'] !== 'APPLIED'
                || (string) $row['ROOT_UUID_ENTITLEMENT'] !== $rootUuid
                || trim((string) ($row['UUID_DESTINATION_FACTURA'] ?? '')) === ''
            ) {
                throw SifException::conflict('Derived application is no longer the current applied exposure.');
            }
            if ($this->one(
                $db,
                'SELECT UUID_DERIVED_BALANCE FROM novice_promotion_derived_balance
                 WHERE SOURCE_UUID_DERIVED_APPLICATION = ? FOR UPDATE',
                [$derived]
            ) !== null) {
                throw SifException::conflict('Derived application already became a cancellation-derived balance.');
            }
            return [
                'DERIVED_APPLICATION',
                $derived,
                [
                    'operation_uuid' => (string) $row['UUID_DESTINATION_OPERATION'],
                    'invoice_uuid' => (string) $row['UUID_DESTINATION_FACTURA'],
                    'amount' => (string) $row['AMOUNT'],
                ],
            ];
        }

        throw SifException::conflict('Successive transfer has an ambiguous source.');
    }

    private function closeSource(
        \PDO $db,
        string $kind,
        string $id,
        string $timestamp
    ): void {
        if ($kind === 'PREVIOUS_TRANSFER') {
            $stmt = $db->prepare(
                "UPDATE novice_promotion_application_transfer
                 SET STATUS = 'CANCELLED', CLOSED_AT = ?,
                     CLOSE_REASON = 'TRANSFERRED_TO_COURSE'
                 WHERE UUID_TRANSFER = ? AND STATUS = 'CONFIRMED'"
            );
        } else {
            $stmt = $db->prepare(
                "UPDATE novice_promotion_derived_application
                 SET STATUS = 'TRANSFERRED', CLOSED_AT = ?,
                     REASON_CODE = 'TRANSFERRED_TO_COURSE'
                 WHERE UUID_DERIVED_APPLICATION = ? AND STATUS = 'APPLIED'"
            );
        }
        $stmt->execute([$timestamp, $id]);
        if ($stmt->rowCount() !== 1) {
            throw SifException::conflict('Current promotional predecessor changed during transfer.');
        }
    }

    private function lockRoot(\PDO $db, string $uuid): array
    {
        $row = $this->one(
            $db,
            'SELECT e.UUID_ENTITLEMENT, e.STATUS, e.HOLDER_PARTY_KEY,
                    g.ORIGIN_UUID_OPERATION, v.STATUS AS VALIDATION_STATUS
             FROM commercial_entitlement e
             JOIN novice_promotion_grant g ON g.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
             JOIN discount_validation v ON v.UUID_VALIDATION = g.UUID_VALIDATION
             WHERE e.UUID_ENTITLEMENT = ? FOR UPDATE',
            [$uuid]
        );
        if ($row === null || $row['STATUS'] !== 'ACTIVE'
            || $row['VALIDATION_STATUS'] !== 'VALIDATED'
        ) {
            throw SifException::conflict('Novice root is not active for successive transfer.');
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
             FROM commercial_operation WHERE UUID_OPERATION = ? FOR UPDATE',
            [$uuidOperation]
        );
        if ($origin === null
            || $origin['SOURCE_TYPE'] !== 'CURS'
            || $origin['PRODUCT_CODE'] !== 'JASOM'
            || !in_array((string) $origin['STATUS'], ['PAID', 'INVOICED', 'COMPLETED'], true)
        ) {
            throw SifException::conflict('Original JASOM is not a fully paid active origin.');
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
                !== $total
            ) {
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

    private function cents(string $amount): int
    {
        if (!preg_match('/^(-?)(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($amount), $m)) {
            throw SifException::validation('Invalid successive transfer amount.');
        }
        $cents = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '', 2, '0');
        return $m[1] === '-' ? -$cents : $cents;
    }

    private function money(int $cents): string
    {
        if ($cents < 0) {
            throw SifException::validation('Negative successive transfer amount is invalid.');
        }
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
