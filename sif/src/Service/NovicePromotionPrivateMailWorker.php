<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

/**
 * INTERNAL UC-111 promotion delivery orchestration.
 *
 * No public endpoint, SMTP adapter, address-verification transport or
 * production schedule is installed by this class. It is safe to invoke only
 * after authenticating the administrative/worker execution context and
 * configuring private key retrieval and mail transport.
 *
 * A retry sends the SAME code. Provider acceptance != recipient delivery.
 */
final class NovicePromotionPrivateMailWorker
{
    public function __construct(
        private NovicePromotionDeliveryAttemptService $attempts,
        private NovicePromotionSealedCodeDecoder $decoder,
        private NovicePromotionCodeKeyProviderInterface $keys,
        private NovicePromotionMailTransportInterface $mailer
    ) {
    }

    public function deliverOnce(\PDO $db, string $uuidEntitlement): array
    {
        $claim = $this->attempts->claim($db, $uuidEntitlement);
        if ($claim['status'] !== 'CLAIMED') {
            return ['status' => (string) $claim['status']];
        }

        $claimId = (string) $claim['claim_id'];
        $code = null;
        $keyHex = null;
        $verifiedEmail = null;
        $prepared = false;
        try {
            // Revalidate entitlement, participant, verified recipient and ALL
            // JASOM invoices AFTER the claim and just before mail handoff.
            $sealed = $this->attempts->loadClaimForPrivateMailer($db, $uuidEntitlement, $claimId);
            $keyHex = $this->keys->getHexKeyForVersion((string) $sealed['key_version']);
            $code = $this->decoder->decode($sealed, $keyHex);
            $verifiedEmail = (string) $sealed['verified_email'];
            $prepared = filter_var($verifiedEmail, FILTER_VALIDATE_EMAIL) !== false;
        } catch (\Throwable $ignored) {
            // No code, recipient, SQL exception or key may leak to the caller.
        } finally {
            unset($keyHex, $sealed);
        }

        if (!$prepared) {
            try {
                $this->attempts->recordResult($db, $uuidEntitlement, $claimId, false);
            } catch (\Throwable $ignored) {
                // A stale attempt can be reclaimed by a later scheduled run.
            }
            throw SifException::conflict('Promotion delivery preparation failed; manual reconciliation may be needed.');
        }

        $accepted = false;
        try {
            // Deterministic message key may be forwarded to providers with
            // deduplication support; SMTP alone cannot guarantee one email.
            $accepted = $this->mailer->sendPromotionCode(
                (string) $verifiedEmail,
                (string) $code,
                'UC111-PROMO-' . $uuidEntitlement
            );
        } catch (\Throwable $ignored) {
            // A transport timeout is ambiguous; a retry may deliver the same
            // message twice, but does not change CODE_HASH or create a grant.
        } finally {
            unset($code, $verifiedEmail);
        }

        try {
            $recorded = $this->attempts->recordResult($db, $uuidEntitlement, $claimId, $accepted);
        } catch (\Throwable $ignored) {
            // After provider acceptance, do NOT overwrite the state as FAILED:
            // transport may already have delivered the unchanged code.
            throw SifException::conflict('Mail provider outcome could not be committed; retry requires reconciliation.');
        }

        return ['status' => (string) $recorded['status']];
    }

}
