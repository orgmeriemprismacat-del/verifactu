<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\NovicePromotionApprovedCancellationPolicy;
use Prisma\Sif\Tests\Support\Assert;

/**
 * Pure decision binding, no MySQL, no banking or external approval connector.
 * A matching test fixture is NOT proof that an actual secretary has approved.
 */
final class NovicePromotionApprovedCancellationPolicyTest
{
    public function testMatchesIndependentDecisionToExactPendingProposal(): void
    {
        [$decision, $review, $application, $snapshot] = $this->fixture();
        (new NovicePromotionApprovedCancellationPolicy())->assertMatches(
            $decision, 'review-1', $review, $application, $snapshot, '2026-09-25 14:00:00'
        );
        Assert::same('90.00', $decision['approved_promotional_amount']);
    }

    public function testCannotSwapOriginalApplicationOrRectificative(): void
    {
        [$decision, $review, $application, $snapshot] = $this->fixture();
        $decision['uuid_original_application'] = 'other-application';
        Assert::throws(\InvalidArgumentException::class, static function () use (
            $decision, $review, $application, $snapshot
        ): void {
            (new NovicePromotionApprovedCancellationPolicy())->assertMatches(
                $decision, 'review-1', $review, $application, $snapshot, '2026-09-25 14:00:00'
            );
        });

        [$decision, $review, $application, $snapshot] = $this->fixture();
        $decision['uuid_rectificative'] = 'other-rectificative';
        Assert::throws(\InvalidArgumentException::class, static function () use (
            $decision, $review, $application, $snapshot
        ): void {
            (new NovicePromotionApprovedCancellationPolicy())->assertMatches(
                $decision, 'review-1', $review, $application, $snapshot, '2026-09-25 14:00:00'
            );
        });
    }

    public function testCannotIncreasePromotionOrCashComparedToStagedReview(): void
    {
        [$decision, $review, $application, $snapshot] = $this->fixture();
        $decision['approved_promotional_amount'] = '90.01';
        Assert::throws(\InvalidArgumentException::class, static function () use (
            $decision, $review, $application, $snapshot
        ): void {
            (new NovicePromotionApprovedCancellationPolicy())->assertMatches(
                $decision, 'review-1', $review, $application, $snapshot, '2026-09-25 14:00:00'
            );
        });

        [$decision, $review, $application, $snapshot] = $this->fixture();
        $decision['approved_cash_amount'] = '0.01';
        Assert::throws(\InvalidArgumentException::class, static function () use (
            $decision, $review, $application, $snapshot
        ): void {
            (new NovicePromotionApprovedCancellationPolicy())->assertMatches(
                $decision, 'review-1', $review, $application, $snapshot, '2026-09-25 14:00:00'
            );
        });
    }

    public function testCannotTreatProposalOrUnknownActorAsFinalizedApproval(): void
    {
        [$decision, $review, $application, $snapshot] = $this->fixture();
        $decision['decision'] = 'PENDING';
        Assert::throws(\InvalidArgumentException::class, static function () use (
            $decision, $review, $application, $snapshot
        ): void {
            (new NovicePromotionApprovedCancellationPolicy())->assertMatches(
                $decision, 'review-1', $review, $application, $snapshot, '2026-09-25 14:00:00'
            );
        });

        [$decision, $review, $application, $snapshot] = $this->fixture();
        $decision['reviewer_id'] = '';
        Assert::throws(\InvalidArgumentException::class, static function () use (
            $decision, $review, $application, $snapshot
        ): void {
            (new NovicePromotionApprovedCancellationPolicy())->assertMatches(
                $decision, 'review-1', $review, $application, $snapshot, '2026-09-25 14:00:00'
            );
        });
    }

    public function testApprovalCannotPredateProposalOrBeInFuture(): void
    {
        [$decision, $review, $application, $snapshot] = $this->fixture();
        $decision['approved_at_utc'] = '2026-09-25 11:59:59';
        Assert::throws(\InvalidArgumentException::class, static function () use (
            $decision, $review, $application, $snapshot
        ): void {
            (new NovicePromotionApprovedCancellationPolicy())->assertMatches(
                $decision, 'review-1', $review, $application, $snapshot, '2026-09-25 14:00:00'
            );
        });

        [$decision, $review, $application, $snapshot] = $this->fixture();
        $decision['approved_at_utc'] = '2026-09-25 14:00:01';
        Assert::throws(\InvalidArgumentException::class, static function () use (
            $decision, $review, $application, $snapshot
        ): void {
            (new NovicePromotionApprovedCancellationPolicy())->assertMatches(
                $decision, 'review-1', $review, $application, $snapshot, '2026-09-25 14:00:00'
            );
        });
    }

    private function fixture(): array
    {
        $review = [
            'CREATED_AT' => '2026-09-25 12:00:00',
            'UUID_RECTIFICATIVE_FACTURA' => 'rectificative-1',
            'PROMOTIONAL_ORIGIN_AMOUNT' => '90.00',
        ];
        $application = ['UUID_APPLICATION' => 'application-1'];
        $snapshot = [
            'state' => 'PENDING_FISCAL_REVIEW',
            'proposed_cash_amount' => '0.00',
            'policy_evidence_ref' => 'approved-policy-record-1',
        ];
        $decision = [
            'review_uuid' => 'review-1',
            'decision_type' => 'NOVICE_DESTINATION_CANCELLATION',
            'decision_id' => 'decision-1',
            'decision' => 'APPROVED',
            'reviewer_id' => 'authenticated-secretariat-1',
            'evidence_ref' => 'approved-policy-record-1',
            'approved_at_utc' => '2026-09-25 12:05:00',
            'uuid_original_application' => 'application-1',
            'uuid_rectificative' => 'rectificative-1',
            'approved_promotional_amount' => '90.00',
            'approved_cash_amount' => '0.00',
        ];
        return [$decision, $review, $application, $snapshot];
    }
}
