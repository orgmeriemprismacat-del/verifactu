<?php

declare(strict_types=1);

namespace Prisma\Sif\Domain;

/**
 * Pure binding check for a trusted finalized backoffice decision.
 *
 * Passing this check proves only internal consistency of the returned
 * evidence. A separate authenticated approval-source implementation MUST
 * establish the reviewer's permissions and the decision's authenticity.
 */
final class NovicePromotionApprovedCancellationPolicy
{
    public function assertMatches(
        array $approval,
        string $uuidReview,
        array $review,
        array $application,
        array $snapshot,
        string $nowUtc
    ): void {
        $expected = [
            'review_uuid' => $uuidReview,
            'decision_type' => 'NOVICE_DESTINATION_CANCELLATION',
            'decision' => 'APPROVED',
            'uuid_original_application' => (string) ($application['UUID_APPLICATION'] ?? ''),
            'uuid_rectificative' => (string) ($review['UUID_RECTIFICATIVE_FACTURA'] ?? ''),
            'approved_promotional_amount' => (string) ($review['PROMOTIONAL_ORIGIN_AMOUNT'] ?? ''),
            'approved_cash_amount' => (string) ($snapshot['proposed_cash_amount'] ?? ''),
            'evidence_ref' => (string) ($snapshot['policy_evidence_ref'] ?? ''),
        ];
        foreach ($expected as $name => $value) {
            if ($value === '' || !isset($approval[$name]) || !is_string($approval[$name])
                || !hash_equals($value, $approval[$name])
            ) {
                throw new \InvalidArgumentException('Approved cancellation differs from the staged immutable review.');
            }
        }

        foreach (['decision_id', 'reviewer_id', 'approved_at_utc'] as $field) {
            if (!isset($approval[$field]) || !is_string($approval[$field])
                || trim($approval[$field]) === ''
            ) {
                throw new \InvalidArgumentException('Authenticated cancellation approval lacks final decision evidence.');
            }
        }
        $createdAt = (string) ($review['CREATED_AT'] ?? '');
        $approvedAt = $approval['approved_at_utc'];
        if (preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $createdAt) !== 1
            || preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $approvedAt) !== 1
            || preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $nowUtc) !== 1
            || $approvedAt < $createdAt
            || $approvedAt > $nowUtc
            || (string) ($snapshot['state'] ?? '') !== 'PENDING_FISCAL_REVIEW'
        ) {
            throw new \InvalidArgumentException('Cancellation approval predates or contradicts the pending proposal.');
        }
    }
}
