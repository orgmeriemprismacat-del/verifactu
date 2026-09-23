<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

/**
 * UC-111: durable, retryable OUTBOX DELIVERY CLAIMS only.
 *
 * Does not read/decrypt TOKEN_CIPHERTEXT, return a plaintext token, look up an
 * email from a form, perform SMTP, or create verified-recipient evidence.
 * The latter must originate from an independently authenticated address
 * confirmation workflow; do not import a legacy enrollment email as verified.
 *
 * A future private mail worker must recheck eligibility immediately BEFORE
 * sending, decrypt the existing token with its versioned external key, and
 * send the SAME token for retries. SMTP cannot guarantee exactly-once delivery.
 */
final class NovicePromotionDeliveryAttemptService
{
    public function __construct(private UuidGenerator $uuids)
    {
    }

    public function claim(\PDO $db, string $uuidEntitlement, ?\DateTimeImmutable $now = null): array
    {
        $this->assertOutsideTransaction($db);
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $timestamp = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $staleBefore = $now->setTimezone(new \DateTimeZone('UTC'))->modify('-30 minutes')
            ->format('Y-m-d H:i:s');

        $db->beginTransaction();
        try {
            $row = $this->one(
                $db,
                'SELECT e.UUID_ENTITLEMENT, e.STATUS AS RIGHT_STATUS, e.CODE_HASH,
                        e.EXPIRES_AT, e.HOLDER_PARTY_KEY,
                        g.ORIGIN_UUID_OPERATION, g.AVAILABLE_AMOUNT,
                        v.STATUS AS VALIDATION_STATUS,
                        op.SOURCE_ID AS ENROLLMENT_ID, op.STATUS AS ORIGIN_STATUS,
                        op.NET_AMOUNT AS ORIGIN_NET_AMOUNT,
                        p.EMAIL AS VERIFIED_EMAIL, p.VERIFIED_AT,
                        o.STATUS AS OUTBOX_STATUS, o.CLAIM_ID, o.CLAIMED_AT,
                        o.NEXT_ATTEMPT_AT, o.ATTEMPTS
                 FROM novice_promotion_code_outbox o
                 JOIN commercial_entitlement e ON e.UUID_ENTITLEMENT = o.UUID_ENTITLEMENT
                 JOIN novice_promotion_grant g ON g.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
                 JOIN discount_validation v ON v.UUID_VALIDATION = g.UUID_VALIDATION
                 JOIN commercial_operation op ON op.UUID_OPERATION = g.ORIGIN_UUID_OPERATION
                 LEFT JOIN novice_promotion_verified_recipient p
                    ON p.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
                 WHERE o.UUID_ENTITLEMENT = ? FOR UPDATE',
                [$uuidEntitlement]
            );

            if ($row === null) {
                throw SifException::conflict('Promotional code has not been prepared.');
            }

            if ($row['OUTBOX_STATUS'] === 'SENT') {
                $db->commit();
                return ['status' => 'ALREADY_SENT', 'claim_id' => null];
            }

            if ($row['OUTBOX_STATUS'] === 'SENDING'
                && $row['CLAIMED_AT'] !== null
                && (string) $row['CLAIMED_AT'] > $staleBefore
            ) {
                $db->commit();
                return ['status' => 'IN_FLIGHT', 'claim_id' => null];
            }

            if ($row['NEXT_ATTEMPT_AT'] !== null && (string) $row['NEXT_ATTEMPT_AT'] > $timestamp) {
                $db->commit();
                return ['status' => 'BACKOFF', 'claim_id' => null];
            }

            if ((int) $row['ATTEMPTS'] >= 5) {
                $db->commit();
                return ['status' => 'MANUAL_REVIEW', 'claim_id' => null];
            }

            $this->assertReady($db, $row, $timestamp);

            $claim = $this->uuids->generate();
            $update = $db->prepare(
                "UPDATE novice_promotion_code_outbox
                 SET STATUS = 'SENDING', CLAIM_ID = ?, CLAIMED_AT = ?,
                     ATTEMPTS = ATTEMPTS + 1, LAST_ERROR_CODE = NULL,
                     NEXT_ATTEMPT_AT = NULL
                 WHERE UUID_ENTITLEMENT = ?"
            );
            $update->execute([$claim, $timestamp, $uuidEntitlement]);
            if ($update->rowCount() !== 1) {
                throw SifException::conflict('Could not claim promotional delivery.');
            }

            $db->commit();
            return ['status' => 'CLAIMED', 'claim_id' => $claim];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * INTERNAL private-mailer handoff for the CURRENT claim.
     *
     * Rechecks the right, verified address, holder and EVERY JASOM installment
     * immediately before exposing ONLY the sealed token to the private
     * worker. A public HTTP controller must never expose this return value.
     */
    public function loadClaimForPrivateMailer(
        \PDO $db,
        string $uuidEntitlement,
        string $claimId,
        ?\DateTimeImmutable $now = null
    ): array {
        $this->assertOutsideTransaction($db);
        if ($uuidEntitlement === '' || $claimId === '') {
            throw SifException::validation('Delivery claim reference is required.');
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $timestamp = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $oldestClaim = $now->setTimezone(new \DateTimeZone('UTC'))->modify('-30 minutes')
            ->format('Y-m-d H:i:s');

        $db->beginTransaction();
        try {
            $right = $this->one(
                $db,
                'SELECT e.UUID_ENTITLEMENT, e.STATUS AS RIGHT_STATUS, e.CODE_HASH, e.EXPIRES_AT,
                        e.HOLDER_PARTY_KEY, g.ORIGIN_UUID_OPERATION, g.AVAILABLE_AMOUNT,
                        v.STATUS AS VALIDATION_STATUS, op.SOURCE_ID AS ENROLLMENT_ID,
                        op.STATUS AS ORIGIN_STATUS, op.NET_AMOUNT AS ORIGIN_NET_AMOUNT,
                        p.EMAIL AS VERIFIED_EMAIL, p.VERIFIED_AT,
                        o.STATUS AS OUTBOX_STATUS, o.CLAIM_ID, o.CLAIMED_AT,
                        o.TOKEN_CIPHERTEXT, o.TOKEN_NONCE, o.TOKEN_TAG, o.WRAP_KEY_VERSION
                 FROM novice_promotion_code_outbox o
                 JOIN commercial_entitlement e ON e.UUID_ENTITLEMENT = o.UUID_ENTITLEMENT
                 JOIN novice_promotion_grant g ON g.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
                 JOIN discount_validation v ON v.UUID_VALIDATION = g.UUID_VALIDATION
                 JOIN commercial_operation op ON op.UUID_OPERATION = g.ORIGIN_UUID_OPERATION
                 LEFT JOIN novice_promotion_verified_recipient p
                    ON p.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
                 WHERE o.UUID_ENTITLEMENT = ? FOR UPDATE',
                [$uuidEntitlement]
            );

            if ($right === null
                || $right['OUTBOX_STATUS'] !== 'SENDING'
                || (string) $right['CLAIM_ID'] !== $claimId
                || $right['CLAIMED_AT'] === null
                || (string) $right['CLAIMED_AT'] < $oldestClaim
            ) {
                throw SifException::conflict('Promotion delivery claim is no longer current.');
            }

            $this->assertReady($db, $right, $timestamp);

            $sealed = [
                'uuid_entitlement' => $uuidEntitlement,
                'claim_id' => $claimId,
                'verified_email' => (string) $right['VERIFIED_EMAIL'],
                'code_hash' => (string) $right['CODE_HASH'],
                'nonce' => (string) $right['TOKEN_NONCE'],
                'tag' => (string) $right['TOKEN_TAG'],
                'ciphertext' => (string) $right['TOKEN_CIPHERTEXT'],
                'key_version' => (string) $right['WRAP_KEY_VERSION'],
            ];
            $db->commit();
            return $sealed;
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Called ONLY after the trusted future SMTP provider reports a result.
     * A timeout is ambiguous: retrying can deliver the same code twice, but
     * never creates a different code or a second grant.
     */
    public function recordResult(
        \PDO $db,
        string $uuidEntitlement,
        string $claimId,
        bool $providerAccepted,
        ?\DateTimeImmutable $now = null
    ): array {
        $this->assertOutsideTransaction($db);
        if ($uuidEntitlement === '' || $claimId === '') {
            throw SifException::validation('Delivery claim reference is required.');
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $timestamp = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $retryAt = $now->setTimezone(new \DateTimeZone('UTC'))->modify('+1 hour')
            ->format('Y-m-d H:i:s');

        $db->beginTransaction();
        try {
            $row = $this->one(
                $db,
                'SELECT STATUS, CLAIM_ID FROM novice_promotion_code_outbox
                 WHERE UUID_ENTITLEMENT = ? FOR UPDATE',
                [$uuidEntitlement]
            );
            if ($row === null) {
                throw SifException::conflict('Promotional delivery attempt is missing.');
            }
            if ($row['STATUS'] === 'SENT') {
                $db->commit();
                return ['status' => 'ALREADY_SENT'];
            }
            if ($row['STATUS'] !== 'SENDING' || (string) $row['CLAIM_ID'] !== $claimId) {
                throw SifException::conflict('Stale or conflicting promotional delivery result.');
            }

            $stmt = $db->prepare(
                'UPDATE novice_promotion_code_outbox
                 SET STATUS = ?, SENT_AT = ?, LAST_ERROR_CODE = ?,
                     NEXT_ATTEMPT_AT = ?, CLAIM_ID = NULL, CLAIMED_AT = NULL
                 WHERE UUID_ENTITLEMENT = ? AND STATUS = ? AND CLAIM_ID = ?'
            );
            $stmt->execute([
                $providerAccepted ? 'SENT' : 'FAILED',
                $providerAccepted ? $timestamp : null,
                $providerAccepted ? null : 'MAIL_PROVIDER_NOT_CONFIRMED',
                $providerAccepted ? null : $retryAt,
                $uuidEntitlement, 'SENDING', $claimId,
            ]);
            if ($stmt->rowCount() !== 1) {
                throw SifException::conflict('Promotional delivery state was changed concurrently.');
            }

            if ($providerAccepted) {
                $stmt = $db->prepare(
                    'INSERT INTO commercial_entitlement_event
                     (UUID_EVENT, UUID_ENTITLEMENT, ACTION, RESULT, ACTOR_TYPE,
                      CORRELATION_ID, CAUSATION_ID, REASON_CODE, CHANGESET_JSON, OCCURRED_AT)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $this->uuids->generate(), $uuidEntitlement,
                    'DELIVER', 'SUCCESS', 'SYSTEM', $uuidEntitlement,
                    $claimId, 'NOVICE_CODE_MAIL_ACCEPTED',
                    json_encode(['provider_accepted' => true], JSON_THROW_ON_ERROR),
                    $timestamp,
                ]);
            }

            $db->commit();
            return ['status' => $providerAccepted ? 'SENT' : 'FAILED'];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    private function assertReady(\PDO $db, array $right, string $timestamp): void
    {
        if ($right['RIGHT_STATUS'] !== 'ACTIVE'
            || !is_string($right['CODE_HASH']) || strlen($right['CODE_HASH']) !== 64
            || $right['EXPIRES_AT'] === null || (string) $right['EXPIRES_AT'] <= $timestamp
            || $right['VALIDATION_STATUS'] !== 'VALIDATED'
            || !in_array((string) $right['ORIGIN_STATUS'], ['PAID', 'INVOICED', 'COMPLETED'], true)
            || $this->cents((string) $right['AVAILABLE_AMOUNT']) <= 0
            || !is_string($right['VERIFIED_EMAIL'])
            || filter_var($right['VERIFIED_EMAIL'], FILTER_VALIDATE_EMAIL) === false
            || $right['VERIFIED_AT'] === null
        ) {
            throw SifException::conflict('Promotional delivery eligibility or verified recipient is missing.');
        }

        $participants = $this->many(
            $db,
            "SELECT PARTY_KEY FROM commercial_operation_party
             WHERE UUID_OPERATION = ? AND PARTY_ROLE = 'PARTICIPANT' FOR UPDATE",
            [(string) $right['ORIGIN_UUID_OPERATION']]
        );
        if (count($participants) !== 1
            || (string) $participants[0]['PARTY_KEY'] !== (string) $right['HOLDER_PARTY_KEY']
        ) {
            throw SifException::conflict('Promotional owner does not match the JASOM participant.');
        }

        $sourceId = trim((string) $right['ENROLLMENT_ID']);
        if ($sourceId === '' || !ctype_digit($sourceId) || (int) $sourceId < 1) {
            throw SifException::conflict('Invalid original JASOM enrollment.');
        }

        // Re-evaluate all distinct original F1/F2 invoices and confirmed CASH,
        // not just the initial invoice (which may be a partial installment).
        $invoices = $this->many(
            $db,
            "SELECT f.UUID_FACTURA, f.TOTAL, f.ESTAT_FACTURA, f.ESTAT_COBRAMENT
             FROM factura f
             WHERE f.TIPUS_FACTURA IN ('F1', 'F2')
               AND EXISTS (
                   SELECT 1 FROM fact_rels r
                   WHERE r.UUID_FACTURA = f.UUID_FACTURA
                     AND r.SOURCE_TYPE = 'INSCRIPCIO' AND r.SOURCE_ID = ?
               )
             ORDER BY f.DATA_EMISSIO, f.UUID_FACTURA FOR UPDATE",
            [(int) $sourceId]
        );

        $netInvoices = 0;
        $netCash = 0;
        foreach ($invoices as $invoice) {
            if ($invoice['ESTAT_FACTURA'] !== 'ISSUED'
                || $invoice['ESTAT_COBRAMENT'] !== 'PAID'
            ) {
                throw SifException::conflict('JASOM original invoice is not fully paid.');
            }
            $invoiceTotal = $this->cents((string) $invoice['TOTAL']);
            if ($invoiceTotal <= 0) {
                throw SifException::conflict('Invalid original JASOM invoice value.');
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
                throw SifException::conflict('Refunded or missing JASOM installment prevents delivery.');
            }
            $netInvoices += $invoiceTotal;
            $netCash += $cash;
        }

        $expected = $this->cents((string) $right['ORIGIN_NET_AMOUNT']);
        if ($expected <= 0 || $netInvoices !== $expected || $netCash !== $expected) {
            throw SifException::conflict('Full original JASOM payment is no longer reconciled.');
        }
    }

    private function cents(string $amount): int
    {
        if (!preg_match('/^(-?)(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($amount), $match)) {
            throw SifException::validation('Invalid JASOM payment amount.');
        }
        $cents = (int) $match[2] * 100 + (int) str_pad($match[3] ?? '', 2, '0');
        return $match[1] === '-' ? -$cents : $cents;
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

    private function assertOutsideTransaction(\PDO $db): void
    {
        if ($db->inTransaction()) {
            throw new \LogicException('Promotion delivery requires an independent transaction.');
        }
    }
}
