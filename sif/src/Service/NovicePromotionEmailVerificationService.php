<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

/**
 * UC-111 recipient verification: authenticated holder + control of mailbox.
 *
 * The caller MUST derive $authenticatedPartyKey from an authenticated session
 * and enforce its authorization. An enrollment email or an input party key
 * alone is never verification of mailbox control.
 *
 * A trusted private transport receives the one-time secret IN MEMORY; the
 * browser only receives the challenge UUID. Nothing writes plaintext secrets
 * to the DB, Git, logs or public responses. No SMTP adapter is included.
 */
final class NovicePromotionEmailVerificationService
{
    public function __construct(private UuidGenerator $uuids)
    {
    }

    public function request(
        \PDO $db,
        string $uuidEntitlement,
        string $authenticatedPartyKey,
        string $candidateEmail,
        NoviceEmailChallengeTransportInterface $transport,
        ?\DateTimeImmutable $now = null
    ): array {
        $this->assertOutsideTransaction($db);
        $email = strtolower(trim($candidateEmail));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 180
            || trim($uuidEntitlement) === '' || trim($authenticatedPartyKey) === ''
        ) {
            throw SifException::validation('Invalid authenticated promotion recipient request.');
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $now = $now->setTimezone(new \DateTimeZone('UTC'));
        $timestamp = $now->format('Y-m-d H:i:s');
        $expiry = $now->modify('+15 minutes');
        $uuidChallenge = $this->uuids->generate();
        $oneTimeSecret = bin2hex(random_bytes(32));
        $digest = $this->digest($uuidEntitlement, $uuidChallenge, $oneTimeSecret);

        $db->beginTransaction();
        try {
            $this->assertHolderHasLiveRight($db, $uuidEntitlement, $authenticatedPartyKey, $timestamp);

            // Switching email must suspend delivery to an older verified
            // address. Once delivery has started, require manual review
            // rather than racing a recipient change against the mailer.
            $recipientStmt = $db->prepare(
                'SELECT EMAIL FROM novice_promotion_verified_recipient
                 WHERE UUID_ENTITLEMENT = ? FOR UPDATE'
            );
            $recipientStmt->execute([$uuidEntitlement]);
            $previousEmail = $recipientStmt->fetchColumn();
            if (is_string($previousEmail) && $previousEmail !== $email) {
                $outboxStmt = $db->prepare(
                    'SELECT STATUS FROM novice_promotion_code_outbox
                     WHERE UUID_ENTITLEMENT = ? FOR UPDATE'
                );
                $outboxStmt->execute([$uuidEntitlement]);
                if (in_array($outboxStmt->fetchColumn(), ['SENDING', 'SENT'], true)) {
                    throw SifException::conflict('Recipient change after a delivery attempt requires manual review.');
                }

                $db->prepare(
                    'DELETE FROM novice_promotion_verified_recipient WHERE UUID_ENTITLEMENT = ?'
                )->execute([$uuidEntitlement]);
            }

            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM novice_promotion_email_challenge
                 WHERE UUID_ENTITLEMENT = ? AND CREATED_AT >= ?'
            );
            $stmt->execute([$uuidEntitlement, $now->modify('-1 hour')->format('Y-m-d H:i:s')]);
            if ((int) $stmt->fetchColumn() >= 3) {
                throw SifException::conflict('Too many recent promotion email verification requests.');
            }

            $db->prepare(
                'UPDATE novice_promotion_email_challenge SET INVALIDATED_AT = ?
                 WHERE UUID_ENTITLEMENT = ? AND CONSUMED_AT IS NULL AND INVALIDATED_AT IS NULL'
            )->execute([$timestamp, $uuidEntitlement]);

            $db->prepare(
                'INSERT INTO novice_promotion_email_challenge
                 (UUID_CHALLENGE, UUID_ENTITLEMENT, HOLDER_PARTY_KEY, EMAIL,
                  TOKEN_HASH, CREATED_AT, EXPIRES_AT)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $uuidChallenge, $uuidEntitlement, $authenticatedPartyKey, $email,
                $digest, $timestamp, $expiry->format('Y-m-d H:i:s'),
            ]);
            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        // The transport receives the secret privately. A successful transport
        // call is provider ACCEPTANCE only; proof requires the holder to submit
        // the secret received at the mailbox in the confirm() operation.
        $accepted = false;
        try {
            $accepted = $transport->sendChallenge($email, $uuidChallenge, $oneTimeSecret, $expiry);
        } catch (\Throwable $ignored) {
            // Do not expose transport exception text or the challenge secret.
        } finally {
            unset($oneTimeSecret);
        }

        $db->beginTransaction();
        try {
            $statement = $db->prepare(
                $accepted
                    ? 'UPDATE novice_promotion_email_challenge SET SENT_AT = ?
                       WHERE UUID_CHALLENGE = ? AND CONSUMED_AT IS NULL AND INVALIDATED_AT IS NULL'
                    : 'UPDATE novice_promotion_email_challenge SET INVALIDATED_AT = ?
                       WHERE UUID_CHALLENGE = ? AND CONSUMED_AT IS NULL AND INVALIDATED_AT IS NULL'
            );
            $statement->execute([$timestamp, $uuidChallenge]);
            $recorded = $statement->rowCount() === 1;
            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        if (!$accepted || !$recorded) {
            throw SifException::conflict('Email verification message could not be registered.');
        }

        return [
            'uuid_challenge' => $uuidChallenge,
            'expires_at' => $expiry->format('Y-m-d H:i:s'),
            'message_accepted' => true,
        ];
    }

    public function confirm(
        \PDO $db,
        string $uuidEntitlement,
        string $authenticatedPartyKey,
        string $uuidChallenge,
        string $oneTimeSecret,
        ?\DateTimeImmutable $now = null
    ): array {
        $this->assertOutsideTransaction($db);
        if (trim($uuidEntitlement) === '' || trim($authenticatedPartyKey) === ''
            || trim($uuidChallenge) === '' || !preg_match('/^[a-f0-9]{64}$/D', $oneTimeSecret)
        ) {
            throw SifException::validation('Invalid promotion email confirmation request.');
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $timestamp = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $db->beginTransaction();
        try {
            $this->assertHolderHasLiveRight($db, $uuidEntitlement, $authenticatedPartyKey, $timestamp);

            $stmt = $db->prepare(
                'SELECT * FROM novice_promotion_email_challenge
                 WHERE UUID_CHALLENGE = ? AND UUID_ENTITLEMENT = ? FOR UPDATE'
            );
            $stmt->execute([$uuidChallenge, $uuidEntitlement]);
            $challenge = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!is_array($challenge)
                || (string) $challenge['HOLDER_PARTY_KEY'] !== $authenticatedPartyKey
            ) {
                throw SifException::conflict('Email verification challenge is not available.');
            }

            if ($challenge['CONSUMED_AT'] !== null || $challenge['INVALIDATED_AT'] !== null
                || $challenge['SENT_AT'] === null || (string) $challenge['EXPIRES_AT'] <= $timestamp
                || (int) $challenge['FAILED_ATTEMPTS'] >= 5
            ) {
                $db->commit();
                return ['verified' => false];
            }

            $valid = hash_equals(
                (string) $challenge['TOKEN_HASH'],
                $this->digest($uuidEntitlement, $uuidChallenge, $oneTimeSecret)
            );
            if (!$valid) {
                $attempts = (int) $challenge['FAILED_ATTEMPTS'] + 1;
                $db->prepare(
                    'UPDATE novice_promotion_email_challenge
                     SET FAILED_ATTEMPTS = ?, INVALIDATED_AT = ?
                     WHERE UUID_CHALLENGE = ?'
                )->execute([$attempts, $attempts >= 5 ? $timestamp : null, $uuidChallenge]);
                $db->commit();
                return ['verified' => false];
            }

            $outbox = $db->prepare(
                'SELECT STATUS FROM novice_promotion_code_outbox
                 WHERE UUID_ENTITLEMENT = ? FOR UPDATE'
            );
            $outbox->execute([$uuidEntitlement]);
            $deliveryStatus = $outbox->fetchColumn();
            $verified = $db->prepare(
                'SELECT EMAIL FROM novice_promotion_verified_recipient
                 WHERE UUID_ENTITLEMENT = ? FOR UPDATE'
            );
            $verified->execute([$uuidEntitlement]);
            $existingEmail = $verified->fetchColumn();

            if (in_array($deliveryStatus, ['SENDING', 'SENT'], true)
                && ($existingEmail === false
                    || (string) $existingEmail !== (string) $challenge['EMAIL'])
            ) {
                throw SifException::conflict('Changing the recipient during or after delivery requires manual review.');
            }

            $db->prepare(
                'INSERT INTO novice_promotion_verified_recipient
                 (UUID_ENTITLEMENT, EMAIL, VERIFIED_AT, VERIFICATION_REF, RECORDED_BY)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE EMAIL = VALUES(EMAIL), VERIFIED_AT = VALUES(VERIFIED_AT),
                   VERIFICATION_REF = VALUES(VERIFICATION_REF), RECORDED_BY = VALUES(RECORDED_BY)'
            )->execute([
                $uuidEntitlement, (string) $challenge['EMAIL'], $timestamp,
                $uuidChallenge, $authenticatedPartyKey,
            ]);
            $db->prepare(
                'UPDATE novice_promotion_email_challenge
                 SET CONSUMED_AT = ? WHERE UUID_CHALLENGE = ? AND CONSUMED_AT IS NULL'
            )->execute([$timestamp, $uuidChallenge]);

            $db->commit();
            return ['verified' => true, 'uuid_entitlement' => $uuidEntitlement];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    private function assertHolderHasLiveRight(
        \PDO $db,
        string $uuidEntitlement,
        string $authenticatedPartyKey,
        string $timestamp
    ): void {
        $stmt = $db->prepare(
            'SELECT e.HOLDER_PARTY_KEY, e.STATUS, e.EXPIRES_AT
             FROM commercial_entitlement e
             JOIN novice_promotion_grant g ON g.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
             WHERE e.UUID_ENTITLEMENT = ? FOR UPDATE'
        );
        $stmt->execute([$uuidEntitlement]);
        $right = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($right) || (string) $right['HOLDER_PARTY_KEY'] !== $authenticatedPartyKey
            || !in_array((string) $right['STATUS'], ['ISSUED', 'ACTIVE'], true)
            || $right['EXPIRES_AT'] === null || (string) $right['EXPIRES_AT'] <= $timestamp
        ) {
            throw SifException::conflict('Authenticated holder has no active novice promotion.');
        }
    }

    private function digest(string $right, string $challenge, string $secret): string
    {
        return hash('sha256', 'UC111|EMAIL|' . $right . '|' . $challenge . '|' . $secret);
    }

    private function assertOutsideTransaction(\PDO $db): void
    {
        if ($db->inTransaction()) {
            throw new \LogicException('Novice email confirmation must run outside a foreign transaction.');
        }
    }
}
