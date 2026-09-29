<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Domain\NovicePromotionAmountPolicy;
use Prisma\Sif\Exception\SifException;

/**
 * UC-111 / UC-20d: internal multi-use commercial promotion.
 *
 * Reserve BEFORE creating the final price/invoice, after the pricing backend
 * has persisted a trusted BEFORE_PROMOTION quote. A trusted checkout adapter
 * must incorporate the reservation in its price/tax calculation; this class
 * does NOT issue invoices, initiate Redsys, create a payment_transaction,
 * transfer prepaid money, send email, or authenticate an HTTP session.
 *
 * Complete AFTER the checkout's real invoice and settlement have committed,
 * using its persisted post-promotion pricing evidence. The application
 * ledger is immutable apart from its explicitly allowed state transitions.
 */
final class NovicePromotionRedemptionService
{
    public function __construct(private UuidGenerator $uuids)
    {
    }

    public function reserve(
        \PDO $db,
        string $code,
        string $authenticatedPartyKey,
        string $destinationOperationUuid,
        string $idempotencyKey,
        \DateTimeImmutable $reservationExpiresAt,
        ?string $requestedAmount = null,
        ?\DateTimeImmutable $now = null
    ): array {
        $this->assertOutsideTransaction($db);
        $code = strtoupper(trim($code));
        $partyKey = trim($authenticatedPartyKey);
        $destinationOperationUuid = trim($destinationOperationUuid);
        $idempotencyKey = trim($idempotencyKey);
        if (!preg_match('/^NOV-[A-F0-9]{40}$/D', $code)
            || $partyKey === '' || $destinationOperationUuid === ''
            || $idempotencyKey === '' || strlen($idempotencyKey) > 140
        ) {
            throw SifException::validation('Invalid promotion reservation request.');
        }

        $desired = $requestedAmount === null ? null : $this->cents($requestedAmount);
        if ($desired !== null && $desired <= 0) {
            throw SifException::validation('Requested promotional value must be positive.');
        }
        $codeHash = hash('sha256', $code);
        unset($code);
        $fingerprint = hash('sha256', json_encode([
            $codeHash, $partyKey, $destinationOperationUuid,
            $desired === null ? 'AUTO' : $this->money($desired),
        ], JSON_THROW_ON_ERROR));

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $nowUtc = $now->setTimezone(new \DateTimeZone('UTC'));
        $reservationUtc = $reservationExpiresAt->setTimezone(new \DateTimeZone('UTC'));
        $issuedAt = $nowUtc->format('Y-m-d H:i:s');
        $reservedUntil = $reservationUtc->format('Y-m-d H:i:s');
        if ($reservationUtc <= $nowUtc || $reservationUtc > $nowUtc->modify('+1 day')) {
            throw SifException::validation('Promotion reservation must expire within one day.');
        }

        $db->beginTransaction();
        try {
            $right = $this->one(
                $db,
                "SELECT e.UUID_ENTITLEMENT, e.STATUS AS ENTITLEMENT_STATUS, e.EXPIRES_AT,
                        e.HOLDER_PARTY_KEY, e.ISSUED_AT, e.ORIGIN_UUID_OPERATION,
                        e.ENTITLEMENT_TYPE, e.CURRENCY,
                        g.AVAILABLE_AMOUNT, g.ORIGINAL_CASH_AMOUNT,
                        v.STATUS AS VALIDATION_STATUS,
                        o.STATUS AS DELIVERY_STATUS
                 FROM commercial_entitlement e
                 JOIN novice_promotion_grant g ON g.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
                 JOIN discount_validation v ON v.UUID_VALIDATION = g.UUID_VALIDATION
                 JOIN novice_promotion_code_outbox o ON o.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
                 WHERE e.CODE_HASH = ? FOR UPDATE",
                [$codeHash]
            );

            if ($right === null || (string) $right['HOLDER_PARTY_KEY'] !== $partyKey) {
                throw SifException::conflict('Promotion is not available to this authenticated person.');
            }

            $rightUuid = (string) $right['UUID_ENTITLEMENT'];
            $previous = $this->one(
                $db,
                'SELECT * FROM novice_promotion_application
                 WHERE IDEMPOTENCY_KEY = ? FOR UPDATE',
                [$idempotencyKey]
            );
            if ($previous !== null) {
                if ((string) $previous['UUID_ENTITLEMENT'] !== $rightUuid
                    || (string) $previous['UUID_DESTINATION_OPERATION'] !== $destinationOperationUuid
                    || !hash_equals((string) $previous['REQUEST_FINGERPRINT'], $fingerprint)
                ) {
                    throw SifException::conflict('Reservation idempotency key was reused with a different request.');
                }

                $db->commit();
                return $this->replayedReservation($previous, $issuedAt);
            }

            if ($right['ENTITLEMENT_STATUS'] !== 'ACTIVE'
                || $right['ENTITLEMENT_TYPE'] !== 'FUTURE_DISCOUNT'
                || $right['CURRENCY'] !== 'EUR'
                || $right['DELIVERY_STATUS'] !== 'SENT'
                || $right['VALIDATION_STATUS'] !== 'VALIDATED'
                || $right['EXPIRES_AT'] === null
                || (string) $right['EXPIRES_AT'] <= $issuedAt
                || $reservedUntil > (string) $right['EXPIRES_AT']
            ) {
                throw SifException::conflict('Promotion is not active, delivered, or valid until reservation expiry.');
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
                || (string) $destination['UUID_OPERATION'] === (string) $right['ORIGIN_UUID_OPERATION']
                || (string) $destination['CREATED_AT'] < (string) $right['ISSUED_AT']
            ) {
                throw SifException::conflict('Destination is not an eligible later course checkout.');
            }

            $participants = $this->many(
                $db,
                "SELECT PARTY_KEY FROM commercial_operation_party
                 WHERE UUID_OPERATION = ? AND PARTY_ROLE = 'PARTICIPANT' FOR UPDATE",
                [$destinationOperationUuid]
            );
            if (count($participants) !== 1 || (string) $participants[0]['PARTY_KEY'] !== $partyKey) {
                throw SifException::conflict('Promotion holder is not the later course participant.');
            }

            if ($this->one(
                $db,
                'SELECT UUID_APPLICATION FROM novice_promotion_application
                 WHERE UUID_DESTINATION_OPERATION = ? FOR UPDATE',
                [$destinationOperationUuid]
            ) !== null) {
                throw SifException::conflict('Destination already has a novice promotion reservation.');
            }

            // The persistent quote MUST be written by the authenticated SIF
            // price calculator, after ordinary promotions and BEFORE UC-111.
            // The source of the quote must not be a browser/client payload.
            $snapshot = json_decode((string) $destination['PRICE_SNAPSHOT_JSON'], true, 512, JSON_THROW_ON_ERROR);
            $quote = is_array($snapshot) ? ($snapshot['novice_promotion_quote'] ?? null) : null;
            $ordinaryNet = $this->cents((string) $destination['NET_AMOUNT']);
            if (!is_array($quote)
                || ($quote['stage'] ?? '') !== 'BEFORE_PROMOTION'
                || ($quote['source'] ?? '') !== 'TRUSTED_SIF_PRICING'
                || !isset($quote['ordinary_net'])
                || $this->cents((string) $quote['ordinary_net']) !== $ordinaryNet
                || $ordinaryNet <= 0
                || $this->cents((string) $destination['GROSS_AMOUNT'])
                    - $this->cents((string) $destination['DISCOUNT_AMOUNT']) !== $ordinaryNet
            ) {
                throw SifException::conflict('Destination has no trusted before-promotion price snapshot.');
            }

            $this->assertOriginalJasomStillPaid($db, (string) $right['ORIGIN_UUID_OPERATION']);

            $available = $this->cents((string) $right['AVAILABLE_AMOUNT']);
            try {
                $selection = (new NovicePromotionAmountPolicy())->allocate(
                    (string) $right['AVAILABLE_AMOUNT'],
                    $this->money($ordinaryNet),
                    $desired === null ? null : $this->money($desired)
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('Promotion exceeds the available balance or later course net price.');
            }
            $amount = $this->cents($selection['applied_amount']);

            $uuidApplication = $this->uuids->generate();
            $amountText = $this->money($amount);
            $debit = $db->prepare(
                'UPDATE novice_promotion_grant
                 SET AVAILABLE_AMOUNT = AVAILABLE_AMOUNT - ?
                 WHERE UUID_ENTITLEMENT = ? AND AVAILABLE_AMOUNT >= ?'
            );
            $debit->execute([$amountText, $rightUuid, $amountText]);
            if ($debit->rowCount() !== 1) {
                throw SifException::conflict('Promotion balance changed during reservation.');
            }

            $this->exec(
                $db,
                'INSERT INTO novice_promotion_application
                 (UUID_APPLICATION, UUID_ENTITLEMENT, UUID_DESTINATION_OPERATION,
                  IDEMPOTENCY_KEY, REQUEST_FINGERPRINT, AMOUNT,
                  DESTINATION_ORDINARY_NET_AMOUNT, STATUS, RESERVED_AT, RESERVATION_EXPIRES_AT)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $uuidApplication, $rightUuid, $destinationOperationUuid,
                    $idempotencyKey, $fingerprint, $amountText,
                    $this->money($ordinaryNet), 'RESERVED', $issuedAt, $reservedUntil,
                ]
            );
            $this->event(
                $db, $rightUuid, $destinationOperationUuid, $uuidApplication,
                'RESERVE', 'RESERVED', $issuedAt, ['amount' => $amountText]
            );

            $db->commit();
            return [
                'uuid_application' => $uuidApplication,
                'uuid_entitlement' => $rightUuid,
                'uuid_destination_operation' => $destinationOperationUuid,
                'reserved_amount' => $amountText,
                'available_amount' => $this->money($available - $amount),
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

    /**
     * The checkout service must FIRST commit the final authorized price,
     * original discounts + THIS promotional discount, its issued invoice and
     * real settlement. This method records application, NOT bank payment.
     *
     * Only a fully settled, single destination invoice is supported here.
     * A fully promo-covered checkout needs a legitimate zero-total issued
     * invoice that the fiscal system marks settled WITHOUT a bank payment.
     * Multiple destination invoices and rectification remain unintegrated.
     */
    public function confirmApplied(
        \PDO $db,
        string $uuidApplication,
        string $uuidDestinationInvoice,
        ?\DateTimeImmutable $now = null
    ): array {
        $this->assertOutsideTransaction($db);
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $timestamp = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $db->beginTransaction();
        try {
            // All promotion writers lock entitlement FIRST, application SECOND.
            // The initial id lookup does not lock and is rechecked below.
            $lookup = $this->one(
                $db,
                'SELECT UUID_ENTITLEMENT FROM novice_promotion_application WHERE UUID_APPLICATION = ?',
                [$uuidApplication]
            );
            if ($lookup === null) {
                throw SifException::conflict('Promotion application was not found.');
            }
            $entitlementLock = $this->one(
                $db,
                'SELECT UUID_ENTITLEMENT FROM commercial_entitlement WHERE UUID_ENTITLEMENT = ? FOR UPDATE',
                [(string) $lookup['UUID_ENTITLEMENT']]
            );
            if ($entitlementLock === null) {
                throw SifException::conflict('Promotional right no longer exists.');
            }
            $application = $this->one(
                $db,
                'SELECT * FROM novice_promotion_application WHERE UUID_APPLICATION = ? FOR UPDATE',
                [$uuidApplication]
            );
            if ($application === null
                || (string) $application['UUID_ENTITLEMENT'] !== (string) $lookup['UUID_ENTITLEMENT']
            ) {
                throw SifException::conflict('Promotion application was not found.');
            }

            if ($application['STATUS'] === 'APPLIED') {
                if ((string) $application['UUID_DESTINATION_FACTURA'] !== $uuidDestinationInvoice) {
                    throw SifException::conflict('Promotion application was confirmed for another invoice.');
                }
                $db->commit();
                return $this->appliedResult($application, true);
            }

            if ($application['STATUS'] !== 'RESERVED'
                || (string) $application['RESERVATION_EXPIRES_AT'] <= $timestamp
            ) {
                throw SifException::conflict('Promotion reservation is no longer eligible to complete.');
            }

            $right = $this->one(
                $db,
                'SELECT e.STATUS, e.EXPIRES_AT, e.HOLDER_PARTY_KEY, e.ORIGIN_UUID_OPERATION,
                        v.STATUS AS VALIDATION_STATUS
                 FROM commercial_entitlement e
                 JOIN novice_promotion_grant g ON g.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
                 JOIN discount_validation v ON v.UUID_VALIDATION = g.UUID_VALIDATION
                 WHERE e.UUID_ENTITLEMENT = ? FOR UPDATE',
                [(string) $application['UUID_ENTITLEMENT']]
            );
            if ($right === null
                || $right['STATUS'] !== 'ACTIVE'
                || $right['VALIDATION_STATUS'] !== 'VALIDATED'
                || $right['EXPIRES_AT'] === null
                || (string) $right['EXPIRES_AT'] <= $timestamp
            ) {
                throw SifException::conflict('Promotion was cancelled or expired before checkout settlement.');
            }

            $this->assertOriginalJasomStillPaid($db, (string) $right['ORIGIN_UUID_OPERATION']);
            $destinationUuid = (string) $application['UUID_DESTINATION_OPERATION'];
            $destination = $this->one(
                $db,
                'SELECT * FROM commercial_operation WHERE UUID_OPERATION = ? FOR UPDATE',
                [$destinationUuid]
            );
            if ($destination === null
                || !in_array((string) $destination['STATUS'], ['PAID', 'COMPLETED'], true)
                || (string) $destination['UUID_FACTURA'] !== $uuidDestinationInvoice
                || (string) $destination['CURRENCY'] !== 'EUR'
            ) {
                throw SifException::conflict('Destination operation has not committed its final paid invoice.');
            }

            $participants = $this->many(
                $db,
                "SELECT PARTY_KEY FROM commercial_operation_party
                 WHERE UUID_OPERATION = ? AND PARTY_ROLE = 'PARTICIPANT' FOR UPDATE",
                [$destinationUuid]
            );
            if (count($participants) !== 1
                || (string) $participants[0]['PARTY_KEY'] !== (string) $right['HOLDER_PARTY_KEY']
            ) {
                throw SifException::conflict('Destination participant changed after reservation.');
            }

            $amount = $this->cents((string) $application['AMOUNT']);
            $ordinaryNet = $this->cents((string) $application['DESTINATION_ORDINARY_NET_AMOUNT']);
            $finalNet = $this->cents((string) $destination['NET_AMOUNT']);
            $snapshot = json_decode((string) $destination['PRICE_SNAPSHOT_JSON'], true, 512, JSON_THROW_ON_ERROR);
            $applied = is_array($snapshot) ? ($snapshot['novice_promotion_application'] ?? null) : null;
            if (!is_array($applied)
                || (string) ($applied['uuid_application'] ?? '') !== $uuidApplication
                || (string) ($applied['uuid_entitlement'] ?? '') !== (string) $application['UUID_ENTITLEMENT']
                || !isset($applied['amount'], $applied['ordinary_net_before_promotion'])
                || $this->cents((string) $applied['amount']) !== $amount
                || $this->cents((string) $applied['ordinary_net_before_promotion']) !== $ordinaryNet
                || $ordinaryNet - $amount !== $finalNet
                || $this->cents((string) $destination['GROSS_AMOUNT'])
                    - $this->cents((string) $destination['DISCOUNT_AMOUNT']) !== $finalNet
            ) {
                throw SifException::conflict('Final fiscal price does not document this exact promotional application.');
            }

            $invoice = $this->one(
                $db,
                'SELECT UUID_FACTURA, TOTAL, ESTAT_FACTURA, ESTAT_COBRAMENT, TIPUS_FACTURA
                 FROM factura WHERE UUID_FACTURA = ? FOR UPDATE',
                [$uuidDestinationInvoice]
            );
            if ($invoice === null || $invoice['ESTAT_FACTURA'] !== 'ISSUED'
                || $invoice['ESTAT_COBRAMENT'] !== 'PAID'
                || !in_array((string) $invoice['TIPUS_FACTURA'], ['F1', 'F2'], true)
                || $finalNet < 0
                || $this->cents((string) $invoice['TOTAL']) !== $finalNet
            ) {
                throw SifException::conflict('Destination invoice is not an issued fully settled final price.');
            }

            // A 100%-covered course must NOT invent a zero-euro bank
            // transaction; a partially covered course must match the actual
            // confirmed net CASH attributed to the issued final invoice.
            $settledCash = $this->one(
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
            if ($this->cents((string) ($settledCash['NET_CASH'] ?? '0.00')) !== $finalNet) {
                throw SifException::conflict('Final destination invoice lacks exactly matched confirmed cash settlement.');
            }

            $sourceId = trim((string) ($destination['SOURCE_ID'] ?? ''));
            if (!ctype_digit($sourceId) || (int) $sourceId < 1 || $this->one(
                $db,
                "SELECT ID FROM fact_rels
                 WHERE UUID_FACTURA = ? AND SOURCE_TYPE = 'INSCRIPCIO' AND SOURCE_ID = ? LIMIT 1",
                [$uuidDestinationInvoice, (int) $sourceId]
            ) === null) {
                throw SifException::conflict('Final invoice is not linked to the reserved enrollment.');
            }

            $stmt = $db->prepare(
                "UPDATE novice_promotion_application
                 SET STATUS = 'APPLIED', UUID_DESTINATION_FACTURA = ?, APPLIED_AT = ?
                 WHERE UUID_APPLICATION = ? AND STATUS = 'RESERVED'"
            );
            $stmt->execute([$uuidDestinationInvoice, $timestamp, $uuidApplication]);
            if ($stmt->rowCount() !== 1) {
                throw SifException::conflict('Promotional application changed before confirmation.');
            }

            $this->event(
                $db,
                (string) $application['UUID_ENTITLEMENT'], $destinationUuid,
                $uuidApplication, 'APPLY', 'APPLIED', $timestamp,
                ['amount' => (string) $application['AMOUNT'], 'uuid_invoice' => $uuidDestinationInvoice]
            );

            $db->commit();
            $application['STATUS'] = 'APPLIED';
            $application['UUID_DESTINATION_FACTURA'] = $uuidDestinationInvoice;
            return $this->appliedResult($application, false);
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Release only a NON-INVOICED, UNSETTLED reservation, after a trusted
     * checkout reports failure, expiry or cancellation. Never mutate an
     * already APPLIED discount or an issued fiscal invoice.
     */
    public function release(
        \PDO $db,
        string $uuidApplication,
        string $reason,
        ?\DateTimeImmutable $now = null
    ): array {
        $this->assertOutsideTransaction($db);
        if (!in_array($reason, ['PAYMENT_FAILED', 'INTENT_EXPIRED', 'CHECKOUT_CANCELLED'], true)) {
            throw SifException::validation('Unsupported promotional reservation release reason.');
        }
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $timestamp = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $db->beginTransaction();
        try {
            // All promotion writers lock entitlement FIRST, application SECOND.
            // The initial id lookup does not lock and is rechecked below.
            $lookup = $this->one(
                $db,
                'SELECT UUID_ENTITLEMENT FROM novice_promotion_application WHERE UUID_APPLICATION = ?',
                [$uuidApplication]
            );
            if ($lookup === null) {
                throw SifException::conflict('Promotion application was not found.');
            }
            $entitlementLock = $this->one(
                $db,
                'SELECT UUID_ENTITLEMENT FROM commercial_entitlement WHERE UUID_ENTITLEMENT = ? FOR UPDATE',
                [(string) $lookup['UUID_ENTITLEMENT']]
            );
            if ($entitlementLock === null) {
                throw SifException::conflict('Promotional right no longer exists.');
            }
            $application = $this->one(
                $db,
                'SELECT * FROM novice_promotion_application WHERE UUID_APPLICATION = ? FOR UPDATE',
                [$uuidApplication]
            );
            if ($application === null
                || (string) $application['UUID_ENTITLEMENT'] !== (string) $lookup['UUID_ENTITLEMENT']
            ) {
                throw SifException::conflict('Promotion reservation was not found.');
            }
            if ($application['STATUS'] === 'RELEASED') {
                $db->commit();
                return ['uuid_application' => $uuidApplication, 'status' => 'RELEASED', 'idempotency_reused' => true];
            }
            if ($application['STATUS'] !== 'RESERVED') {
                throw SifException::conflict('Applied promotion cannot be released without destination correction.');
            }
            if ($reason === 'INTENT_EXPIRED' && (string) $application['RESERVATION_EXPIRES_AT'] > $timestamp) {
                throw SifException::conflict('Reservation has not expired.');
            }

            $destination = $this->one(
                $db,
                'SELECT UUID_FACTURA, STATUS FROM commercial_operation WHERE UUID_OPERATION = ? FOR UPDATE',
                [(string) $application['UUID_DESTINATION_OPERATION']]
            );
            if ($destination === null
                || trim((string) ($destination['UUID_FACTURA'] ?? '')) !== ''
                || trim((string) ($destination['UUID_INTENT'] ?? '')) !== ''
                || !in_array((string) $destination['STATUS'], ['READY_FOR_PAYMENT', 'PAYMENT_PENDING', 'CANCELLED'], true)
            ) {
                // A pending or ambiguous Redsys intent CANNOT be released just
                // because an HTTP request claims payment failure/expiry.
                throw SifException::conflict('Destination payment intent/invoice requires reconciliation before release.');
            }

            $sourceId = trim((string) ($destination['SOURCE_ID'] ?? ''));
            if ($sourceId === '' || !ctype_digit($sourceId) || (int) $sourceId < 1
                || $this->one(
                    $db,
                    "SELECT ID FROM fact_rels WHERE SOURCE_TYPE = 'INSCRIPCIO'
                     AND SOURCE_ID = ? LIMIT 1",
                    [(int) $sourceId]
                ) !== null
            ) {
                throw SifException::conflict('Destination has an origin invoice or an invalid enrollment reference.');
            }

            $entitlementUuid = (string) $application['UUID_ENTITLEMENT'];
            $grant = $this->one(
                $db,
                'SELECT ORIGINAL_CASH_AMOUNT, AVAILABLE_AMOUNT FROM novice_promotion_grant
                 WHERE UUID_ENTITLEMENT = ? FOR UPDATE',
                [$entitlementUuid]
            );
            $entitlement = $this->one(
                $db,
                'SELECT STATUS FROM commercial_entitlement WHERE UUID_ENTITLEMENT = ? FOR UPDATE',
                [$entitlementUuid]
            );
            if ($grant === null || $entitlement === null || $entitlement['STATUS'] !== 'ACTIVE') {
                throw SifException::conflict('Cancelled promotion requires a separate audited reversal, not release.');
            }

            $amount = $this->cents((string) $application['AMOUNT']);
            $available = $this->cents((string) $grant['AVAILABLE_AMOUNT']);
            $face = $this->cents((string) $grant['ORIGINAL_CASH_AMOUNT']);
            if ($available + $amount > $face) {
                throw SifException::conflict('Reservation release would exceed original promotional value.');
            }

            $this->exec(
                $db,
                'UPDATE novice_promotion_grant
                 SET AVAILABLE_AMOUNT = AVAILABLE_AMOUNT + ?
                 WHERE UUID_ENTITLEMENT = ?',
                [$this->money($amount), $entitlementUuid]
            );
            $this->exec(
                $db,
                "UPDATE novice_promotion_application
                 SET STATUS = 'RELEASED', RELEASED_AT = ?, REASON_CODE = ?
                 WHERE UUID_APPLICATION = ? AND STATUS = 'RESERVED'",
                [$timestamp, $reason, $uuidApplication]
            );
            $this->event(
                $db, $entitlementUuid, (string) $application['UUID_DESTINATION_OPERATION'],
                $uuidApplication, 'RELEASE', 'RELEASED', $timestamp,
                ['amount' => (string) $application['AMOUNT'], 'reason' => $reason]
            );
            $db->commit();
            return [
                'uuid_application' => $uuidApplication,
                'status' => 'RELEASED',
                'available_amount' => $this->money($available + $amount),
                'idempotency_reused' => false,
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    private function assertOriginalJasomStillPaid(\PDO $db, string $originUuid): void
    {
        $origin = $this->one(
            $db,
            'SELECT SOURCE_TYPE, SOURCE_ID, PRODUCT_CODE, STATUS, NET_AMOUNT
             FROM commercial_operation WHERE UUID_OPERATION = ? FOR UPDATE',
            [$originUuid]
        );
        if ($origin === null || $origin['SOURCE_TYPE'] !== 'CURS'
            || $origin['PRODUCT_CODE'] !== 'JASOM'
            || !in_array((string) $origin['STATUS'], ['PAID', 'INVOICED', 'COMPLETED'], true)
        ) {
            throw SifException::conflict('Original JASOM is not an active paid enrollment.');
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
                   SELECT 1 FROM fact_rels r WHERE r.UUID_FACTURA = f.UUID_FACTURA
                     AND r.SOURCE_TYPE = 'INSCRIPCIO' AND r.SOURCE_ID = ?
               )
             ORDER BY f.DATA_EMISSIO, f.UUID_FACTURA FOR UPDATE",
            [(int) $sourceId]
        );
        $netInvoiced = 0;
        $netCash = 0;
        foreach ($invoices as $invoice) {
            if ($invoice['ESTAT_FACTURA'] !== 'ISSUED'
                || $invoice['ESTAT_COBRAMENT'] !== 'PAID'
            ) {
                throw SifException::conflict('Original JASOM has an unpaid or nonissued invoice.');
            }
            $invoiceTotal = $this->cents((string) $invoice['TOTAL']);
            if ($invoiceTotal <= 0) {
                throw SifException::conflict('Original JASOM has an invalid invoice.');
            }
            $payment = $this->one(
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
            $cash = $this->cents((string) ($payment['NET_CASH'] ?? '0.00'));
            if ($cash !== $invoiceTotal) {
                throw SifException::conflict('Original JASOM is no longer fully paid in confirmed cash.');
            }
            $netInvoiced += $invoiceTotal;
            $netCash += $cash;
        }

        $approvedNet = $this->cents((string) $origin['NET_AMOUNT']);
        if ($approvedNet <= 0 || $netInvoiced !== $approvedNet || $netCash !== $approvedNet) {
            throw SifException::conflict('Original JASOM has incomplete or inconsistent confirmed payment.');
        }
    }

    private function replayedReservation(array $previous, string $now): array
    {
        if ($previous['STATUS'] === 'RELEASED' || $previous['STATUS'] === 'REVERSED'
            || ($previous['STATUS'] === 'RESERVED'
                && (string) $previous['RESERVATION_EXPIRES_AT'] <= $now)
        ) {
            throw SifException::conflict('Reservation was released or expired; create a new checkout operation.');
        }
        return [
            'uuid_application' => (string) $previous['UUID_APPLICATION'],
            'uuid_entitlement' => (string) $previous['UUID_ENTITLEMENT'],
            'uuid_destination_operation' => (string) $previous['UUID_DESTINATION_OPERATION'],
            'reserved_amount' => (string) $previous['AMOUNT'],
            'reservation_expires_at' => (string) $previous['RESERVATION_EXPIRES_AT'],
            'status' => (string) $previous['STATUS'],
            'idempotency_reused' => true,
        ];
    }

    private function appliedResult(array $application, bool $replayed): array
    {
        return [
            'uuid_application' => (string) $application['UUID_APPLICATION'],
            'uuid_entitlement' => (string) $application['UUID_ENTITLEMENT'],
            'uuid_destination_operation' => (string) $application['UUID_DESTINATION_OPERATION'],
            'uuid_destination_invoice' => (string) $application['UUID_DESTINATION_FACTURA'],
            'applied_amount' => (string) $application['AMOUNT'],
            'status' => 'APPLIED',
            'idempotency_reused' => $replayed,
        ];
    }

    private function event(
        \PDO $db, string $rightUuid, string $operationUuid, string $applicationUuid,
        string $action, string $newState, string $timestamp, array $changes
    ): void {
        $this->exec(
            $db,
            'INSERT INTO commercial_entitlement_event
             (UUID_EVENT, UUID_ENTITLEMENT, ACTION, RESULT, UUID_OPERATION,
              ACTOR_TYPE, CORRELATION_ID, CAUSATION_ID, REASON_CODE,
              CHANGESET_JSON, OCCURRED_AT)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $this->uuids->generate(), $rightUuid, $action, 'SUCCESS', $operationUuid,
                'SYSTEM', $applicationUuid, $applicationUuid,
                'NOVICE_PROMO_' . $newState,
                json_encode($changes + ['uuid_application' => $applicationUuid], JSON_THROW_ON_ERROR),
                $timestamp,
            ]
        );
    }

    private function cents(string $value): int
    {
        if (!preg_match('/^(-?)(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($value), $m)) {
            throw SifException::validation('Invalid promotional monetary amount.');
        }
        $cents = ((int) $m[2] * 100) + (int) str_pad($m[3] ?? '', 2, '0');
        return $m[1] === '-' ? -$cents : $cents;
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
        $rows = $this->many($db, $sql, $params);
        return $rows[0] ?? null;
    }

    private function many(\PDO $db, string $sql, array $params): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function exec(\PDO $db, string $sql, array $params): void
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    }

    private function assertOutsideTransaction(\PDO $db): void
    {
        if ($db->inTransaction()) {
            throw new \LogicException('Promotion redemption requires its own SIF transaction.');
        }
    }
}
