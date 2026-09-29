<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\NovicePromotionApprovedDerivedCancellationPolicy;
use Prisma\Sif\Tests\Support\Assert;

/** Pure decision binding: no MySQL, fiscal issuer or bank integration. */
final class NovicePromotionApprovedDerivedCancellationPolicyTest
{
    public function testExactDecisionMatchesDerivedApplicationReview(): void
    {
        [$approval, $review, $snapshot] = $this->fixture();
        (new NovicePromotionApprovedDerivedCancellationPolicy())->assertMatches(
            $approval, 'review-child', $review, $snapshot, '2026-09-29 18:00:00'
        );
        Assert::same('30.00', $approval['approved_promotional_amount']);
    }

    public function testCannotSwapParentBalanceOrSourceApplication(): void
    {
        [$approval, $review, $snapshot] = $this->fixture();
        $approval['uuid_parent_derived_balance'] = 'other-parent';
        Assert::throws(\InvalidArgumentException::class, static function () use (
            $approval, $review, $snapshot
        ): void {
            (new NovicePromotionApprovedDerivedCancellationPolicy())->assertMatches(
                $approval, 'review-child', $review, $snapshot, '2026-09-29 18:00:00'
            );
        });

        [$approval, $review, $snapshot] = $this->fixture();
        $approval['uuid_source_derived_application'] = 'other-app';
        Assert::throws(\InvalidArgumentException::class, static function () use (
            $approval, $review, $snapshot
        ): void {
            (new NovicePromotionApprovedDerivedCancellationPolicy())->assertMatches(
                $approval, 'review-child', $review, $snapshot, '2026-09-29 18:00:00'
            );
        });
    }

    public function testCannotIncreasePromotionOrCash(): void
    {
        [$approval, $review, $snapshot] = $this->fixture();
        $approval['approved_promotional_amount'] = '30.01';
        Assert::throws(\InvalidArgumentException::class, static function () use (
            $approval, $review, $snapshot
        ): void {
            (new NovicePromotionApprovedDerivedCancellationPolicy())->assertMatches(
                $approval, 'review-child', $review, $snapshot, '2026-09-29 18:00:00'
            );
        });

        [$approval, $review, $snapshot] = $this->fixture();
        $approval['approved_cash_amount'] = '20.01';
        Assert::throws(\InvalidArgumentException::class, static function () use (
            $approval, $review, $snapshot
        ): void {
            (new NovicePromotionApprovedDerivedCancellationPolicy())->assertMatches(
                $approval, 'review-child', $review, $snapshot, '2026-09-29 18:00:00'
            );
        });
    }

    public function testPendingOrFutureDecisionFailsClosed(): void
    {
        [$approval, $review, $snapshot] = $this->fixture();
        $approval['decision'] = 'PENDING';
        Assert::throws(\InvalidArgumentException::class, static function () use (
            $approval, $review, $snapshot
        ): void {
            (new NovicePromotionApprovedDerivedCancellationPolicy())->assertMatches(
                $approval, 'review-child', $review, $snapshot, '2026-09-29 18:00:00'
            );
        });

        [$approval, $review, $snapshot] = $this->fixture();
        $approval['approved_at_utc'] = '2026-09-29 18:00:01';
        Assert::throws(\InvalidArgumentException::class, static function () use (
            $approval, $review, $snapshot
        ): void {
            (new NovicePromotionApprovedDerivedCancellationPolicy())->assertMatches(
                $approval, 'review-child', $review, $snapshot, '2026-09-29 18:00:00'
            );
        });
    }

    public function testCannotUseWrongRectificativeOrEvidence(): void
    {
        [$approval, $review, $snapshot] = $this->fixture();
        $approval['uuid_rectificative'] = 'other-r';
        Assert::throws(\InvalidArgumentException::class, static function () use (
            $approval, $review, $snapshot
        ): void {
            (new NovicePromotionApprovedDerivedCancellationPolicy())->assertMatches(
                $approval, 'review-child', $review, $snapshot, '2026-09-29 18:00:00'
            );
        });

        [$approval, $review, $snapshot] = $this->fixture();
        $approval['evidence_ref'] = 'other-policy';
        Assert::throws(\InvalidArgumentException::class, static function () use (
            $approval, $review, $snapshot
        ): void {
            (new NovicePromotionApprovedDerivedCancellationPolicy())->assertMatches(
                $approval, 'review-child', $review, $snapshot, '2026-09-29 18:00:00'
            );
        });
    }

    private function fixture(): array
    {
        $review = [
            'CREATED_AT' => '2026-09-29 16:00:00',
            'SOURCE_UUID_DERIVED_APPLICATION' => 'dapp-1',
            'PARENT_UUID_DERIVED_BALANCE' => 'derived-parent-1',
            'UUID_RECTIFICATIVE_FACTURA' => 'rect-child-1',
            'PROMOTIONAL_ORIGIN_AMOUNT' => '30.00',
        ];
        $snapshot = [
            'state' => 'PENDING_FISCAL_REVIEW',
            'proposed_cash_amount' => '20.00',
            'policy_evidence_ref' => 'derived-cancel-policy-1',
        ];
        $approval = [
            'review_uuid' => 'review-child',
            'decision_type' => 'NOVICE_DERIVED_APPLICATION_CANCELLATION',
            'decision_id' => 'decision-child-1',
            'decision' => 'APPROVED',
            'reviewer_id' => 'secretariat-1',
            'evidence_ref' => 'derived-cancel-policy-1',
            'approved_at_utc' => '2026-09-29 16:10:00',
            'uuid_source_derived_application' => 'dapp-1',
            'uuid_parent_derived_balance' => 'derived-parent-1',
            'uuid_rectificative' => 'rect-child-1',
            'approved_promotional_amount' => '30.00',
            'approved_cash_amount' => '20.00',
        ];
        return [$approval, $review, $snapshot];
    }
}
