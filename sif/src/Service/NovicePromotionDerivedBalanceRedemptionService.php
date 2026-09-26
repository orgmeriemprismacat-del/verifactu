<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\NovicePromotionAmountPolicy;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

/**
 * UC-111 / DEC-23: INTERNAL spending of an ACTIVE cancellation-derived
 * promotional balance over one or more later courses.
 *
 * This balance has NO redeemable NOV-* code of its own in this design:
 * selection is by authenticated holder + server-side derived balance UUID.
 * The caller must derive HOLDER_PARTY_KEY from an authenticated account.
 *
 * Reserve happens BEFORE final pricing/invoice and debits the available
 * derived amount atomically. Confirm happens only AFTER the trusted pricing
 * engine, invoice and real residual settlement have committed and NEVER
 * debits the balance again. Release is allowed only while there is no Redsys
 * intent/fiscal invoice and the JASOM root still exists and remains paid.
 *
 * No method here emits/rectifies invoices, creates CHARGE/REFUND, authenticates
 * HTTP callers, sends codes, or executes the later root-JASOM clawback.
 */
final class NovicePromotionDerivedBalanceRedemptionService
{
    public function __construct(
        private UuidGenerator $uuids = new UuidGenerator(),
        private NovicePromotionAmountPolicy $amounts = new NovicePromotionAmountPolicy()
    ) {
    }

    public function reserve(
        \PDO $db,
        string $uuidDerivedBalance,
        string $authenticatedPartyKey,
        string $destinationOperationUuid,
        string $idempotencyKey,
        \DateTimeImmutable $reservationExpiresAt,
        ?string $requestedAmount = null,
        ?\DateTimeImmutable $now = null
    ): array {
        $this->assertOutsideTransaction($db);
        $uuidDerivedBalance = trim($uuidDerivedBalance);
        $party = trim($authenticatedPartyKey);
        $destinationOperationUuid = trim($destinationOperationUuid);
        $idempotencyKey = trim($idempotencyKey);
        if ($uuidDerivedBalance === '' || $party === '' || $destinationOperationUuid === ''
            || $idempotencyKey === '' || strlen($idempotencyKey) > 140
        ) {
            throw SifException::validation('Invalid derived promotional reservation request.');
        }

        $desired = $requestedAmount === null ? null : $this->cents($requestedAmount);
        if ($desired !== null && $desired <= 0) {
            throw SifException::validation('Requested derived promotional value must be positive.');
        }

        $fingerprint = hash('sha256', json_encode([
            $uuidDerivedBalance,
            $party,
            $destinationOperationUuid,
            $desired === null ? 'AUTO' : $this->money($desired),
        ], JSON_THROW_ON_ERROR));

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $nowUtc = $now->setTimezone(new \DateTimeZone('UTC'));
        $reservationUtc = $reservationExpiresAt->setTimezone(new \DateTimeZone('UTC'));
        $timestamp = $nowUtc->format('Y-m-d H:i:s');
        $reservedUntil = $reservationUtc->format('Y-m-d H:i:s');
        if ($reservationUtc <= $nowUtc || $reservationUtc > $nowUtc->modify('+1 day')) {
            throw SifException::validation('Derived promotion reservation must expire within one day.');
        }

        $db->beginTransaction();
        try {
            $lookup = $this->one(
                $db,
                'SELECT ROOT_UUID_ENTITLEMENT FROM novice_promotion_derived_balance
                 WHERE UUID_DERIVED_BALANCE = ?',
                [$uuidDerivedBalance]
            );
            if ($lookup === null) {
                throw SifException::conflict('Derived promotional balance does not exist.');
            }

            $root = $this->lockRoot($db, (string) $lookup['ROOT_UUID_ENTITLEMENT']);
            $derived = $this->one(
                $db,
                'SELECT * FROM novice_promotion_derived_balance
                 WHERE UUID_DERIVED_BALANCE = ? FOR UPDATE',
                [$uuidDerivedBalance]
            );
            if ($derived === null
                || (string) $derived['ROOT_UUID_ENTITLEMENT'] !== (string) $root['UUID_ENTITLEMENT']
                || (string) $derived['HOLDER_PARTY_KEY'] !== $party
                || $derived['STATUS'] !== 'ACTIVE'
                || $derived['ISSUED_AT'] === null || $derived['EXPIRES_AT'] === null
                || (string) $derived['EXPIRES_AT'] <= $timestamp
                || $reservedUntil > (string) $derived['EXPIRES_AT']
            ) {
                throw SifException::conflict('Derived promotional balance is not active for this authenticated holder.');
            }

            $previous = $this->one(
                $db,
                'SELECT * FROM novice_promotion_derived_application
                 WHERE IDEMPOTENCY_KEY = ? FOR UPDATE',
                [$idempotencyKey]
            );
            if ($previous !== null) {
                if ((string) $previous['UUID_DERIVED_BALANCE'] !== $uuidDerivedBalance
                    || (string) $previous['UUID_DESTINATION_OPERATION'] !== $destinationOperationUuid
                    || !is_string($previous['REQUEST_FINGERPRINT'])
                    || !hash_equals((string) $previous['REQUEST_FINGERPRINT'], $fingerprint)
                ) {
                    throw SifException::conflict('Derived reservation idempotency key was reused with another request.');
                }
                $db->commit();
                return $this->reservationReplay($previous, $timestamp);
            }

            $destination = $this->one(
                $db,
                'SELECT * FROM commercial_operation WHERE UUID_OPERATION = ? FOR UPDATE',
                [$destinationOperationUuid]
            );
            if ($destination === null
                || $destination['SOURCE_TYPE'] !== 'CURS'
                || $destination['PRODUCT_TYPE'] !== 'CURS'
                || $destination['CURRENCY'] !== 'EUR'
                || $destination['STATUS'] !== 'READY_FOR_PAYMENT'
                || trim((string) ($destination['UUID_FACTURA'] ?? '')) !== ''
                || trim((string) ($destination['UUID_INTENT'] ?? '')) !== ''
                || (string) $destination['CREATED_AT'] < (string) $derived['ISSUED_AT']
            ) {
                throw SifException::conflict('Destination is not an eligible later course for a derived balance.');
            }

            $participants = $this->many(
                $db,
                "SELECT PARTY_KEY FROM commercial_operation_party
                 WHERE UUID_OPERATION = ? AND PARTY_ROLE = 'PARTICIPANT' FOR UPDATE",
                [$destinationOperationUuid]
            );
            if (count($participants) !== 1 || (string) $participants[0]['PARTY_KEY'] !== $party) {
                throw SifException::conflict('Derived balance holder is not the later course participant.');
            }

            // A course can have only one UC-111 promotional path regardless
            // of whether it came from the initial JASOM right or a later
            // cancellation-derived right.
            if ($this->one(
                $db,
                'SELECT UUID_APPLICATION AS ID FROM novice_promotion_application
                 WHERE UUID_DESTINATION_OPERATION = ? FOR UPDATE',
                [$destinationOperationUuid]
            ) !== null
                || $this->one(
                    $db,
                    'SELECT UUID_DERIVED_APPLICATION AS ID FROM novice_promotion_derived_application
                     WHERE UUID_DESTINATION_OPERATION = ? FOR UPDATE',
                    [$destinationOperationUuid]
                ) !== null
                || $this->one(
                    $db,
                    "SELECT UUID_TRANSFER AS ID FROM novice_promotion_application_transfer
                     WHERE TO_UUID_OPERATION = ? AND STATUS <> 'CANCELLED' FOR UPDATE",
                    [$destinationOperationUuid]
                ) !== null
            ) {
                throw SifException::conflict('Destination already has a UC-111 promotion or transfer.');
            }

            $snapshot = json_decode(
                (string) $destination['PRICE_SNAPSHOT_JSON'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            $quote = is_array($snapshot) ? ($snapshot['novice_derived_balance_quote'] ?? null) : null;
            $ordinaryNet = $this->cents((string) $destination['NET_AMOUNT']);
            if (!is_array($quote)
                || ($quote['source'] ?? '') !== 'TRUSTED_SIF_PRICING'
                || ($quote['stage'] ?? '') !== 'BEFORE_DERIVED_PROMOTION'
                || ($quote['uuid_derived_balance'] ?? '') !== $uuidDerivedBalance
                || !isset($quote['ordinary_net'])
                || $this->cents((string) $quote['ordinary_net']) !== $ordinaryNet
                || $ordinaryNet <= 0
                || $this->cents((string) $destination['GROSS_AMOUNT'])
                    - $this->cents((string) $destination['DISCOUNT_AMOUNT']) !== $ordinaryNet
            ) {
                throw SifException::conflict('Destination has no trusted pre-derived-balance price snapshot.');
            }

            $this->assertOriginalJasomStillPaid($db, (string) $root['ORIGIN_UUID_OPERATION']);

            try {
                $allocation = $this->amounts->allocate(
                    (string) $derived['AVAILABLE_PROMOTIONAL_AMOUNT'],
                    $this->money($ordinaryNet),
                    $desired === null ? null : $this->money($desired)
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('Derived promotion exceeds available balance or destination price.');
            }

            $amount = $this->cents((string) $allocation['applied_amount']);
            $amountText = $this->money($amount);
            $debit = $db->prepare(
                'UPDATE novice_promotion_derived_balance
                 SET AVAILABLE_PROMOTIONAL_AMOUNT = AVAILABLE_PROMOTIONAL_AMOUNT - ?
                 WHERE UUID_DERIVED_BALANCE = ?
                   AND STATUS = ? AND AVAILABLE_PROMOTIONAL_AMOUNT >= ?'
            );
            $debit->execute([$amountText, $uuidDerivedBalance, 'ACTIVE', $amountText]);
            if ($debit->rowCount() !== 1) {
                throw SifException::conflict('Derived promotional balance changed during reservation.');
            }

            $uuidApplication = $this->uuids->generate();
            $stmt = $db->prepare(
                'INSERT INTO novice_promotion_derived_application
                 (UUID_DERIVED_APPLICATION, UUID_DERIVED_BALANCE, ROOT_UUID_ENTITLEMENT,
                  UUID_DESTINATION_OPERATION, UUID_DESTINATION_FACTURA, AMOUNT, STATUS,
                  RESERVED_AT, APPLIED_AT, CLOSED_AT, IDEMPOTENCY_KEY,
                  REQUEST_FINGERPRINT, DESTINATION_ORDINARY_NET_AMOUNT,
                  RESERVATION_EXPIRES_AT, RELEASED_AT, REASON_CODE)
                 VALUES (?, ?, ?, ?, NULL, ?, ?, ?, NULL, NULL, ?, ?, ?, ?, NULL, NULL)'
            );
            $stmt->execute([
                $uuidApplication,
                $uuidDerivedBalance,
                (string) $root['UUID_ENTITLEMENT'],
                $destinationOperationUuid,
                $amountText,
                'RESERVED',
                $timestamp,
                $idempotencyKey,
                $fingerprint,
                $this->money($ordinaryNet),
                $reservedUntil,
            ]);

            $this->audit(
                $db,
                (string) $root['UUID_ENTITLEMENT'],
                $destinationOperationUuid,
                $uuidApplication,
                'DERIVED_RESERVE',
                'DERIVED_BALANCE_RESERVED',
                $timestamp,
                [
                    'uuid_derived_balance' => $uuidDerivedBalance,
                    'amount' => $amountText,
                    'remaining_derived_balance' => (string) $allocation['remaining_promotion'],
                    'reservation_expires_at' => $reservedUntil,
                ]
            );

            $db->commit();
            return [
                'uuid_derived_application' => $uuidApplication,
                'uuid_derived_balance' => $uuidDerivedBalance,
                'uuid_destination_operation' => $destinationOperationUuid,
                'reserved_amount' => $amountText,
                'available_promotional_amount' => (string) $allocation['remaining_promotion'],
                'reservation_expires_at' => $reservedUntil,
                'status' => 'RESERVED',
                'idempotency_reused' => false,
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    public function confirmApplied(
        \PDO $db,
        string $uuidDerivedApplication,
        string $uuidDestinationInvoice,
        ?\DateTimeImmutable $now = null
    ): array {
        $this->assertOutsideTransaction($db);
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $timestamp = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $db->beginTransaction();
        try {
            $lookup = $this->one(
                $db,
                'SELECT ROOT_UUID_ENTITLEMENT, UUID_DERIVED_BALANCE
                 FROM novice_promotion_derived_application
                 WHERE UUID_DERIVED_APPLICATION = ?',
                [$uuidDerivedApplication]
            );
            if ($lookup === null) {
                throw SifException::conflict('Derived promotional application was not found.');
            }
            $root = $this->lockRoot($db, (string) $lookup['ROOT_UUID_ENTITLEMENT']);
            $derived = $this->one(
                $db,
                'SELECT * FROM novice_promotion_derived_balance
                 WHERE UUID_DERIVED_BALANCE = ? FOR UPDATE',
                [(string) $lookup['UUID_DERIVED_BALANCE']]
            );
            $application = $this->one(
                $db,
                'SELECT * FROM novice_promotion_derived_application
                 WHERE UUID_DERIVED_APPLICATION = ? FOR UPDATE',
                [$uuidDerivedApplication]
            );
            if ($derived === null || $application === null
                || (string) $derived['ROOT_UUID_ENTITLEMENT'] !== (string) $root['UUID_ENTITLEMENT']
                || (string) $application['ROOT_UUID_ENTITLEMENT'] !== (string) $root['UUID_ENTITLEMENT']
                || (string) $application['UUID_DERIVED_BALANCE'] !== (string) $derived['UUID_DERIVED_BALANCE']
            ) {
                throw SifException::conflict('Derived promotional lineage changed during confirmation.');
            }

            if ($application['STATUS'] === 'APPLIED') {
                if ((string) $application['UUID_DESTINATION_FACTURA'] !== $uuidDestinationInvoice) {
                    throw SifException::conflict('Derived promotion was applied to another invoice.');
                }
                $db->commit();
                return $this->appliedResult($application, true);
            }

            if ($application['STATUS'] !== 'RESERVED'
                || $application['RESERVATION_EXPIRES_AT'] === null
                || (string) $application['RESERVATION_EXPIRES_AT'] <= $timestamp
                || $derived['STATUS'] !== 'ACTIVE'
                || $derived['EXPIRES_AT'] === null
                || (string) $derived['EXPIRES_AT'] <= $timestamp
                || (string) $derived['HOLDER_PARTY_KEY'] !== (string) $root['HOLDER_PARTY_KEY']
            ) {
                throw SifException::conflict('Derived promotion is not eligible for final application.');
            }

            $this->assertOriginalJasomStillPaid($db, (string) $root['ORIGIN_UUID_OPERATION']);

            $destinationUuid = (string) $application['UUID_DESTINATION_OPERATION'];
            $destination = $this->one(
                $db,
                'SELECT * FROM commercial_operation WHERE UUID_OPERATION = ? FOR UPDATE',
                [$destinationUuid]
            );
            if ($destination === null
                || !in_array((string) $destination['STATUS'], ['PAID', 'COMPLETED'], true)
                || (string) $destination['UUID_FACTURA'] !== $uuidDestinationInvoice
                || $destination['CURRENCY'] !== 'EUR'
            ) {
                throw SifException::conflict('Derived destination has no committed final paid invoice.');
            }

            $participants = $this->many(
                $db,
                "SELECT PARTY_KEY FROM commercial_operation_party
                 WHERE UUID_OPERATION = ? AND PARTY_ROLE = 'PARTICIPANT' FOR UPDATE",
                [$destinationUuid]
            );
            if (count($participants) !== 1
                || (string) $participants[0]['PARTY_KEY'] !== (string) $root['HOLDER_PARTY_KEY']
            ) {
                throw SifException::conflict('Derived destination participant changed after reservation.');
            }

            $amount = $this->cents((string) $application['AMOUNT']);
            $ordinaryNet = $this->cents((string) $application['DESTINATION_ORDINARY_NET_AMOUNT']);
            $finalNet = $this->cents((string) $destination['NET_AMOUNT']);
            $snapshot = json_decode(
                (string) $destination['PRICE_SNAPSHOT_JSON'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            $applied = is_array($snapshot) ? ($snapshot['novice_derived_application'] ?? null) : null;
            if (!is_array($applied)
                || ($applied['uuid_derived_application'] ?? '') !== $uuidDerivedApplication
                || ($applied['uuid_derived_balance'] ?? '') !== (string) $derived['UUID_DERIVED_BALANCE']
                || !isset($applied['amount'], $applied['ordinary_net_before_promotion'])
                || $this->cents((string) $applied['amount']) !== $amount
                || $this->cents((string) $applied['ordinary_net_before_promotion']) !== $ordinaryNet
                || $ordinaryNet - $amount !== $finalNet
                || $this->cents((string) $destination['GROSS_AMOUNT'])
                    - $this->cents((string) $destination['DISCOUNT_AMOUNT']) !== $finalNet
            ) {
                throw SifException::conflict('Final course price does not document this exact derived-balance use.');
            }

            $invoice = $this->one(
                $db,
                'SELECT UUID_FACTURA, TOTAL, ESTAT_FACTURA, ESTAT_COBRAMENT, TIPUS_FACTURA
                 FROM factura WHERE UUID_FACTURA = ? FOR UPDATE',
                [$uuidDestinationInvoice]
            );
            if ($invoice === null
                || !in_array((string) $invoice['TIPUS_FACTURA'], ['F1', 'F2'], true)
                || $invoice['ESTAT_FACTURA'] !== 'ISSUED'
                || $invoice['ESTAT_COBRAMENT'] !== 'PAID'
                || $finalNet < 0
                || $this->cents((string) $invoice['TOTAL']) !== $finalNet
            ) {
                throw SifException::conflict('Derived destination invoice is not an issued fully settled final price.');
            }

            $sourceId = trim((string) ($destination['SOURCE_ID'] ?? ''));
            if ($sourceId === '' || !ctype_digit($sourceId) || (int) $sourceId < 1
                || $this->one(
                    $db,
                    "SELECT ID FROM fact_rels
                     WHERE UUID_FACTURA = ? AND SOURCE_TYPE = 'INSCRIPCIO'
                       AND SOURCE_ID = ? LIMIT 1",
                    [$uuidDestinationInvoice, (int) $sourceId]
                ) === null
            ) {
                throw SifException::conflict('Derived destination invoice is not linked to its enrollment.');
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
                [$uuidDestinationInvoice]
            );
            if ($this->cents((string) ($settlement['NET_CASH'] ?? '0.00')) !== $finalNet) {
                throw SifException::conflict('Derived destination residual does not match confirmed external cash.');
            }

            $stmt = $db->prepare(
                "UPDATE novice_promotion_derived_application
                 SET STATUS = 'APPLIED', UUID_DESTINATION_FACTURA = ?,
                     APPLIED_AT = ?, CLOSED_AT = ?
                 WHERE UUID_DERIVED_APPLICATION = ? AND STATUS = 'RESERVED'"
            );
            $stmt->execute([
                $uuidDestinationInvoice, $timestamp, $timestamp, $uuidDerivedApplication,
            ]);
            if ($stmt->rowCount() !== 1) {
                throw SifException::conflict('Derived application changed during final confirmation.');
            }

            $this->audit(
                $db,
                (string) $root['UUID_ENTITLEMENT'],
                $destinationUuid,
                $uuidDerivedApplication,
                'DERIVED_APPLY',
                'DERIVED_BALANCE_APPLIED',
                $timestamp,
                [
                    'uuid_derived_balance' => (string) $derived['UUID_DERIVED_BALANCE'],
                    'uuid_destination_invoice' => $uuidDestinationInvoice,
                    'amount' => (string) $application['AMOUNT'],
                ]
            );

            $application['STATUS'] = 'APPLIED';
            $application['UUID_DESTINATION_FACTURA'] = $uuidDestinationInvoice;
            $db->commit();
            return $this->appliedResult($application, false);
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    public function release(
        \PDO $db,
        string $uuidDerivedApplication,
        string $reason,
        ?\DateTimeImmutable $now = null
    ): array {
        $this->assertOutsideTransaction($db);
        if (!in_array($reason, ['PAYMENT_FAILED', 'INTENT_EXPIRED', 'CHECKOUT_CANCELLED'], true)) {
            throw SifException::validation('Unsupported derived promotional release reason.');
        }
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $timestamp = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $db->beginTransaction();
        try {
            $lookup = $this->one(
                $db,
                'SELECT ROOT_UUID_ENTITLEMENT, UUID_DERIVED_BALANCE
                 FROM novice_promotion_derived_application
                 WHERE UUID_DERIVED_APPLICATION = ?',
                [$uuidDerivedApplication]
            );
            if ($lookup === null) {
                throw SifException::conflict('Derived reservation was not found.');
            }
            $root = $this->lockRoot($db, (string) $lookup['ROOT_UUID_ENTITLEMENT']);
            $derived = $this->one(
                $db,
                'SELECT * FROM novice_promotion_derived_balance
                 WHERE UUID_DERIVED_BALANCE = ? FOR UPDATE',
                [(string) $lookup['UUID_DERIVED_BALANCE']]
            );
            $application = $this->one(
                $db,
                'SELECT * FROM novice_promotion_derived_application
                 WHERE UUID_DERIVED_APPLICATION = ? FOR UPDATE',
                [$uuidDerivedApplication]
            );
            if ($derived === null || $application === null
                || (string) $application['UUID_DERIVED_BALANCE'] !== (string) $derived['UUID_DERIVED_BALANCE']
            ) {
                throw SifException::conflict('Derived reservation lineage changed before release.');
            }

            if ($application['STATUS'] === 'RELEASED') {
                $db->commit();
                return [
                    'uuid_derived_application' => $uuidDerivedApplication,
                    'status' => 'RELEASED',
                    'idempotency_reused' => true,
                ];
            }
            if ($application['STATUS'] !== 'RESERVED'
                || $derived['STATUS'] !== 'ACTIVE'
                || (string) $derived['HOLDER_PARTY_KEY'] !== (string) $root['HOLDER_PARTY_KEY']
            ) {
                throw SifException::conflict('Only an active, un-applied derived reservation can be released.');
            }
            if ($reason === 'INTENT_EXPIRED'
                && ((string) ($application['RESERVATION_EXPIRES_AT'] ?? '') === ''
                    || (string) $application['RESERVATION_EXPIRES_AT'] > $timestamp)
            ) {
                throw SifException::conflict('Derived reservation has not actually expired.');
            }

            $destination = $this->one(
                $db,
                'SELECT UUID_FACTURA, UUID_INTENT, STATUS, SOURCE_ID
                 FROM commercial_operation WHERE UUID_OPERATION = ? FOR UPDATE',
                [(string) $application['UUID_DESTINATION_OPERATION']]
            );
            if ($destination === null
                || trim((string) ($destination['UUID_FACTURA'] ?? '')) !== ''
                || trim((string) ($destination['UUID_INTENT'] ?? '')) !== ''
                || !in_array((string) $destination['STATUS'], [
                    'READY_FOR_PAYMENT', 'PAYMENT_PENDING', 'CANCELLED',
                ], true)
            ) {
                throw SifException::conflict('Derived destination payment/fiscal state needs reconciliation before release.');
            }
            $sourceId = trim((string) ($destination['SOURCE_ID'] ?? ''));
            if ($sourceId === '' || !ctype_digit($sourceId)
                || $this->one(
                    $db,
                    "SELECT ID FROM fact_rels
                     WHERE SOURCE_TYPE = 'INSCRIPCIO' AND SOURCE_ID = ? LIMIT 1",
                    [(int) $sourceId]
                ) !== null
            ) {
                throw SifException::conflict('Derived destination already has fiscal evidence or invalid enrollment reference.');
            }

            $this->assertOriginalJasomStillPaid($db, (string) $root['ORIGIN_UUID_OPERATION']);

            $amount = $this->cents((string) $application['AMOUNT']);
            $available = $this->cents((string) $derived['AVAILABLE_PROMOTIONAL_AMOUNT']);
            $origin = $this->cents((string) $derived['PROMOTIONAL_ORIGIN_AMOUNT']);
            if ($available + $amount > $origin) {
                throw SifException::conflict('Derived release would exceed its approved promotional origin.');
            }

            $restore = $db->prepare(
                'UPDATE novice_promotion_derived_balance
                 SET AVAILABLE_PROMOTIONAL_AMOUNT = AVAILABLE_PROMOTIONAL_AMOUNT + ?
                 WHERE UUID_DERIVED_BALANCE = ? AND STATUS = ?'
            );
            $restore->execute([
                $this->money($amount),
                (string) $derived['UUID_DERIVED_BALANCE'],
                'ACTIVE',
            ]);
            if ($restore->rowCount() !== 1) {
                throw SifException::conflict('Derived balance changed while releasing reservation.');
            }

            $stmt = $db->prepare(
                "UPDATE novice_promotion_derived_application
                 SET STATUS = 'RELEASED', RELEASED_AT = ?, CLOSED_AT = ?, REASON_CODE = ?
                 WHERE UUID_DERIVED_APPLICATION = ? AND STATUS = 'RESERVED'"
            );
            $stmt->execute([$timestamp, $timestamp, $reason, $uuidDerivedApplication]);
            if ($stmt->rowCount() !== 1) {
                throw SifException::conflict('Derived reservation changed during release.');
            }

            $this->audit(
                $db,
                (string) $root['UUID_ENTITLEMENT'],
                (string) $application['UUID_DESTINATION_OPERATION'],
                $uuidDerivedApplication,
                'DERIVED_RELEASE',
                'DERIVED_BALANCE_RELEASED',
                $timestamp,
                [
                    'uuid_derived_balance' => (string) $derived['UUID_DERIVED_BALANCE'],
                    'amount' => (string) $application['AMOUNT'],
                    'reason' => $reason,
                ]
            );

            $db->commit();
            return [
                'uuid_derived_application' => $uuidDerivedApplication,
                'uuid_derived_balance' => (string) $derived['UUID_DERIVED_BALANCE'],
                'status' => 'RELEASED',
                'available_promotional_amount' => $this->money($available + $amount),
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
             JOIN novice_promotion_grant g ON g.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
             JOIN discount_validation v ON v.UUID_VALIDATION = g.UUID_VALIDATION
             WHERE e.UUID_ENTITLEMENT = ? FOR UPDATE',
            [$rootUuid]
        );
        if ($root === null || $root['STATUS'] !== 'ACTIVE'
            || $root['VALIDATION_STATUS'] !== 'VALIDATED'
        ) {
            throw SifException::conflict('JASOM root is not active for derived promotional spending.');
        }
        return $root;
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
            throw SifException::conflict('Original JASOM is not a fully paid active origin.');
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
                throw SifException::conflict('Original JASOM contains an unpaid or nonissued invoice.');
            }
            $total = $this->cents((string) $invoice['TOTAL']);
            if ($total <= 0) {
                throw SifException::conflict('Original JASOM invoice total is invalid.');
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

        if ($invoiced <= 0 || $invoiced !== $this->cents((string) $origin['NET_AMOUNT'])) {
            throw SifException::conflict('Original JASOM amount no longer matches fully paid invoices.');
        }
    }

    private function reservationReplay(array $application, string $now): array
    {
        if ($application['STATUS'] === 'RELEASED'
            || $application['STATUS'] === 'CANCELLED'
            || $application['STATUS'] === 'CONVERTED_TO_DERIVED'
            || $application['STATUS'] === 'TRANSFERRED'
            || ($application['STATUS'] === 'RESERVED'
                && ((string) ($application['RESERVATION_EXPIRES_AT'] ?? '') === ''
                    || (string) $application['RESERVATION_EXPIRES_AT'] <= $now))
        ) {
            throw SifException::conflict('Derived reservation is closed or expired; create a new checkout operation.');
        }
        return [
            'uuid_derived_application' => (string) $application['UUID_DERIVED_APPLICATION'],
            'uuid_derived_balance' => (string) $application['UUID_DERIVED_BALANCE'],
            'uuid_destination_operation' => (string) $application['UUID_DESTINATION_OPERATION'],
            'reserved_amount' => (string) $application['AMOUNT'],
            'reservation_expires_at' => (string) $application['RESERVATION_EXPIRES_AT'],
            'status' => (string) $application['STATUS'],
            'idempotency_reused' => true,
        ];
    }

    private function appliedResult(array $application, bool $replayed): array
    {
        return [
            'uuid_derived_application' => (string) $application['UUID_DERIVED_APPLICATION'],
            'uuid_derived_balance' => (string) $application['UUID_DERIVED_BALANCE'],
            'uuid_destination_operation' => (string) $application['UUID_DESTINATION_OPERATION'],
            'uuid_destination_invoice' => (string) $application['UUID_DESTINATION_FACTURA'],
            'applied_amount' => (string) $application['AMOUNT'],
            'status' => 'APPLIED',
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
        string $timestamp,
        array $changes
    ): void {
        $stmt = $db->prepare(
            'INSERT INTO commercial_entitlement_event
             (UUID_EVENT, UUID_ENTITLEMENT, ACTION, RESULT, UUID_OPERATION,
              ACTOR_TYPE, CORRELATION_ID, CAUSATION_ID, REASON_CODE,
              CHANGESET_JSON, OCCURRED_AT)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $this->uuids->generate(),
            $rootUuid,
            $action,
            'SUCCESS',
            $operationUuid,
            'SYSTEM',
            $correlationId,
            $correlationId,
            $reason,
            json_encode($changes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            $timestamp,
        ]);
    }

    private function cents(string $amount): int
    {
        if (!preg_match('/^(-?)(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($amount), $match)) {
            throw SifException::validation('Invalid derived promotional amount.');
        }
        $cents = (int) $match[2] * 100 + (int) str_pad($match[3] ?? '', 2, '0');
        return $match[1] === '-' ? -$cents : $cents;
    }

    private function money(int $cents): string
    {
        if ($cents < 0) {
            throw SifException::validation('Negative derived promotional amount is not allowed.');
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

    private function assertOutsideTransaction(\PDO $db): void
    {
        if ($db->inTransaction()) {
            throw new \LogicException('Derived promotional redemption needs its own SIF transaction.');
        }
    }
}
