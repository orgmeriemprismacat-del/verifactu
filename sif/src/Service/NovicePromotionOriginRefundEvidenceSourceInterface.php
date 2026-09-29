<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

/**
 * Authoritative evidence that the ACTUAL bank/payment refund of the original
 * JASOM has completed while the promotion root is frozen in REFUND_REVIEW.
 *
 * Implementations must verify the real payment provider/accounting state.
 * This interface does not initiate the refund.
 */
interface NovicePromotionOriginRefundEvidenceSourceInterface
{
    /**
     * @return array<string, string>|null Mandatory keys:
     * review_uuid, refund_evidence_id, root_uuid_entitlement,
     * origin_uuid_operation, refunded_amount, confirmed_at_utc.
     */
    public function confirmedOriginRefund(string $uuidReview): ?array;
}
