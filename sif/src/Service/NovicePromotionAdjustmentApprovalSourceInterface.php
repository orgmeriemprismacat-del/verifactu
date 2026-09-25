<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

/**
 * TRUSTED, INTERNAL approval evidence boundary for UC-111 adjustments.
 *
 * Implementations MUST fetch an already finalized, independently authorized
 * secretary/fiscal decision from the authenticated backoffice audit store,
 * NEVER from request JSON, an enrollment field, a caller-supplied actor name
 * or simply the existence of a factura_rectificacio link.
 *
 * A null decision means fail closed. This branch deliberately has NO live
 * implementation or public endpoint; the backoffice approval integration
 * must supply one after reviewing the policy and the actual rectificative.
 */
interface NovicePromotionAdjustmentApprovalSourceInterface
{
    /**
     * @return array<string, string>|null Mandatory keys: review_uuid,
     * decision_type, decision_id, decision, reviewer_id, evidence_ref,
     * approved_at_utc, uuid_original_application, uuid_rectificative,
     * approved_promotional_amount, approved_cash_amount.
     */
    public function approvedCancellation(string $uuidDerivedReview): ?array;

    /**
     * @return array<string, string>|null Mandatory keys:
     * review_uuid, decision_type, decision_id, decision, reviewer_id,
     * evidence_ref, approved_at_utc, uuid_original_application,
     * uuid_rectificative, uuid_new_operation, approved_promotional_amount.
     * The source must independently establish that the final course-change
     * decision was authorized and that its commercial amount is approved.
     */
    public function approvedFirstTransfer(string $uuidTransferReview): ?array;
}
