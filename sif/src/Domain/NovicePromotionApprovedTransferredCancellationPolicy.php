<?php

declare(strict_types=1);

namespace Prisma\Sif\Domain;

/**
 * Pure binding of a FINAL approved cancellation of the CURRENT course reached
 * through a confirmed transfer. Authentication remains the responsibility of
 * the trusted approval-source implementation.
 */
final class NovicePromotionApprovedTransferredCancellationPolicy
{
    public function assertMatches(
        array $approval,
        string $uuidReview,
        array $review,
        array $snapshot,
        string $nowUtc
    ): void {
        $expected = [
            'review_uuid' => $uuidReview,
            'decision_type' => 'NOVICE_TRANSFERRED_DESTINATION_CANCELLATION',
            'decision' => 'APPROVED',
            'uuid_source_transfer' => (string) ($review['SOURCE_UUID_TRANSFER'] ?? ''),
            'uuid_rectificative' => (string) ($review['UUID_RECTIFICATIVE_FACTURA'] ?? ''),
            'approved_promotional_amount' => (string) ($review['PROMOTIONAL_ORIGIN_AMOUNT'] ?? ''),
            'approved_cash_amount' => (string) ($snapshot['proposed_cash_amount'] ?? ''),
            'evidence_ref' => (string) ($snapshot['policy_evidence_ref'] ?? ''),
        ];
        foreach ($expected as $key => $value) {
            if ($value === '' || !isset($approval[$key]) || !is_string($approval[$key])
                || !hash_equals($value, $approval[$key])
            ) {
                throw new \InvalidArgumentException('Transferred-course cancellation approval differs from its immutable review.');
            }
        }

        foreach (['decision_id', 'reviewer_id', 'approved_at_utc'] as $field) {
            if (!isset($approval[$field]) || !is_string($approval[$field])
                || trim($approval[$field]) === ''
            ) {
                throw new \InvalidArgumentException('Transferred-course cancellation lacks final approval evidence.');
            }
        }
        if (strlen($approval['decision_id']) > 100
            || strlen($approval['reviewer_id']) > 100
            || strlen($approval['evidence_ref']) > 140
        ) {
            throw new \InvalidArgumentException('Transferred cancellation approval reference is too long.');
        }

        $created = (string) ($review['CREATED_AT'] ?? '');
        $approved = (string) $approval['approved_at_utc'];
        if (preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $created) !== 1
            || preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $approved) !== 1
            || preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $nowUtc) !== 1
            || $approved < $created || $approved > $nowUtc
            || (string) ($snapshot['state'] ?? '') !== 'PENDING_FISCAL_REVIEW'
        ) {
            throw new \InvalidArgumentException('Transferred cancellation approval chronology is inconsistent.');
        }
    }
}
