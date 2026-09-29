<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\NovicePromotionApprovedTransferredCancellationPolicy;
use Prisma\Sif\Tests\Support\Assert;

/** Pure approval binding, no MySQL or fiscal issuer. */
final class NovicePromotionApprovedTransferredCancellationPolicyTest
{
    public function testExactDecisionMatchesTransferredCancellationReview(): void
    {
        [$approval, $review, $snapshot] = $this->fixture();
        (new NovicePromotionApprovedTransferredCancellationPolicy())->assertMatches(
            $approval, 'review-1', $review, $snapshot, '2026-09-26 18:00:00'
        );
        Assert::same('40.00', $approval['approved_promotional_amount']);
    }

    public function testCannotSwapTransferOrRectificative(): void
    {
        [$approval, $review, $snapshot] = $this->fixture();
        $approval['uuid_source_transfer'] = 'other-transfer';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review, $snapshot): void {
            (new NovicePromotionApprovedTransferredCancellationPolicy())->assertMatches(
                $approval, 'review-1', $review, $snapshot, '2026-09-26 18:00:00'
            );
        });

        [$approval, $review, $snapshot] = $this->fixture();
        $approval['uuid_rectificative'] = 'other-rectificative';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review, $snapshot): void {
            (new NovicePromotionApprovedTransferredCancellationPolicy())->assertMatches(
                $approval, 'review-1', $review, $snapshot, '2026-09-26 18:00:00'
            );
        });
    }

    public function testCannotIncreasePromotionalOrCashPart(): void
    {
        [$approval, $review, $snapshot] = $this->fixture();
        $approval['approved_promotional_amount'] = '40.01';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review, $snapshot): void {
            (new NovicePromotionApprovedTransferredCancellationPolicy())->assertMatches(
                $approval, 'review-1', $review, $snapshot, '2026-09-26 18:00:00'
            );
        });

        [$approval, $review, $snapshot] = $this->fixture();
        $approval['approved_cash_amount'] = '20.01';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review, $snapshot): void {
            (new NovicePromotionApprovedTransferredCancellationPolicy())->assertMatches(
                $approval, 'review-1', $review, $snapshot, '2026-09-26 18:00:00'
            );
        });
    }

    public function testPendingOrUnidentifiedDecisionFailsClosed(): void
    {
        [$approval, $review, $snapshot] = $this->fixture();
        $approval['decision'] = 'PENDING';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review, $snapshot): void {
            (new NovicePromotionApprovedTransferredCancellationPolicy())->assertMatches(
                $approval, 'review-1', $review, $snapshot, '2026-09-26 18:00:00'
            );
        });

        [$approval, $review, $snapshot] = $this->fixture();
        $approval['reviewer_id'] = '';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review, $snapshot): void {
            (new NovicePromotionApprovedTransferredCancellationPolicy())->assertMatches(
                $approval, 'review-1', $review, $snapshot, '2026-09-26 18:00:00'
            );
        });
    }

    public function testApprovalCannotPredateReviewOrBeFutureDated(): void
    {
        [$approval, $review, $snapshot] = $this->fixture();
        $approval['approved_at_utc'] = '2026-09-26 15:59:59';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review, $snapshot): void {
            (new NovicePromotionApprovedTransferredCancellationPolicy())->assertMatches(
                $approval, 'review-1', $review, $snapshot, '2026-09-26 18:00:00'
            );
        });

        [$approval, $review, $snapshot] = $this->fixture();
        $approval['approved_at_utc'] = '2026-09-26 18:00:01';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review, $snapshot): void {
            (new NovicePromotionApprovedTransferredCancellationPolicy())->assertMatches(
                $approval, 'review-1', $review, $snapshot, '2026-09-26 18:00:00'
            );
        });
    }

    private function fixture(): array
    {
        $review = [
            'CREATED_AT' => '2026-09-26 16:00:00',
            'SOURCE_UUID_TRANSFER' => 'transfer-1',
            'UUID_RECTIFICATIVE_FACTURA' => 'rectificative-2',
            'PROMOTIONAL_ORIGIN_AMOUNT' => '40.00',
        ];
        $snapshot = [
            'state' => 'PENDING_FISCAL_REVIEW',
            'proposed_cash_amount' => '20.00',
            'policy_evidence_ref' => 'policy-2',
        ];
        $approval = [
            'review_uuid' => 'review-1',
            'decision_type' => 'NOVICE_TRANSFERRED_DESTINATION_CANCELLATION',
            'decision_id' => 'decision-2',
            'decision' => 'APPROVED',
            'reviewer_id' => 'secretariat-user',
            'evidence_ref' => 'policy-2',
            'approved_at_utc' => '2026-09-26 16:05:00',
            'uuid_source_transfer' => 'transfer-1',
            'uuid_rectificative' => 'rectificative-2',
            'approved_promotional_amount' => '40.00',
            'approved_cash_amount' => '20.00',
        ];
        return [$approval, $review, $snapshot];
    }
}
