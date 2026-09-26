<?php

declare(strict_types=1);

namespace Prisma\Sif\Domain;

/**
 * Pure exact binding of a FINAL authorized root-JASOM refund decision to the
 * frozen commercial-consequences review. Authentication and permission checks
 * belong to the trusted approval-source implementation.
 */
final class NovicePromotionApprovedRootRefundPolicy
{
    public function assertMatches(
        array $approval,
        array $review,
        string $nowUtc
    ): void {
        $plan = json_decode((string) ($review['PLAN_JSON'] ?? ''), true);
        if (!is_array($plan)) {
            throw new \InvalidArgumentException('Frozen root-refund plan is unreadable.');
        }

        $expected = [
            'review_uuid' => (string) ($review['UUID_REVIEW'] ?? ''),
            'decision_type' => 'NOVICE_ROOT_JASOM_REFUND',
            'decision' => 'APPROVED',
            'root_uuid_entitlement' => (string) ($review['ROOT_UUID_ENTITLEMENT'] ?? ''),
            'plan_hash' => (string) ($review['PLAN_HASH'] ?? ''),
            'total_cancel_available' => (string) ($plan['total_cancel_available'] ?? ''),
            'total_recover_active' => (string) ($plan['total_recover_active'] ?? ''),
            'evidence_ref' => (string) ($review['REQUEST_EVIDENCE_REF'] ?? ''),
        ];
        foreach ($expected as $key => $value) {
            if ($value === '' || !isset($approval[$key]) || !is_string($approval[$key])
                || !hash_equals($value, $approval[$key])
            ) {
                throw new \InvalidArgumentException('Root refund approval differs from the frozen review.');
            }
        }

        foreach (['decision_id', 'reviewer_id', 'approved_at_utc'] as $key) {
            if (!isset($approval[$key]) || !is_string($approval[$key])
                || trim($approval[$key]) === ''
            ) {
                throw new \InvalidArgumentException('Root refund approval lacks final decision evidence.');
            }
        }
        if (strlen($approval['decision_id']) > 100
            || strlen($approval['reviewer_id']) > 100
            || strlen($approval['evidence_ref']) > 140
        ) {
            throw new \InvalidArgumentException('Root refund approval reference exceeds supported length.');
        }

        $requestedAt = (string) ($review['REQUESTED_AT'] ?? '');
        $approvedAt = (string) $approval['approved_at_utc'];
        if (preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $requestedAt) !== 1
            || preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $approvedAt) !== 1
            || preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $nowUtc) !== 1
            || $approvedAt < $requestedAt || $approvedAt > $nowUtc
            || (string) ($review['STATUS'] ?? '') !== 'PENDING_APPROVAL'
        ) {
            throw new \InvalidArgumentException('Root refund approval chronology or review state is invalid.');
        }
    }
}
