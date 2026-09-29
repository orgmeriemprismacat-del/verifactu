<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\NovicePromotionApprovedTransferPolicy;
use Prisma\Sif\Tests\Support\Assert;

/** Pure validation of transfer decision binding; NO database or SMTP. */
final class NovicePromotionApprovedTransferPolicyTest
{
    public function testExactApprovedTransferMatchesThePendingReview(): void
    {
        [$approval, $review] = $this->fixture();
        (new NovicePromotionApprovedTransferPolicy())->assertMatches(
            $approval, 'transfer-1', $review, '2026-09-25 14:00:00'
        );
        Assert::same('70.00', $approval['approved_promotional_amount']);
    }

    public function testCannotApproveAnotherCourseOrHigherPromotionalAmount(): void
    {
        [$approval, $review] = $this->fixture();
        $approval['uuid_new_operation'] = 'another-course';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review): void {
            (new NovicePromotionApprovedTransferPolicy())->assertMatches(
                $approval, 'transfer-1', $review, '2026-09-25 14:00:00'
            );
        });
        [$approval, $review] = $this->fixture();
        $approval['approved_promotional_amount'] = '70.01';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review): void {
            (new NovicePromotionApprovedTransferPolicy())->assertMatches(
                $approval, 'transfer-1', $review, '2026-09-25 14:00:00'
            );
        });
    }

    public function testCannotSubstituteUnrelatedRectificativeOrSourceApplication(): void
    {
        [$approval, $review] = $this->fixture();
        $approval['uuid_rectificative'] = 'other-fiscal-document';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review): void {
            (new NovicePromotionApprovedTransferPolicy())->assertMatches(
                $approval, 'transfer-1', $review, '2026-09-25 14:00:00'
            );
        });
        [$approval, $review] = $this->fixture();
        $approval['uuid_original_application'] = 'other-application';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review): void {
            (new NovicePromotionApprovedTransferPolicy())->assertMatches(
                $approval, 'transfer-1', $review, '2026-09-25 14:00:00'
            );
        });
    }

    public function testPendingOrUndatedDecisionDoesNotAuthorizeTransfer(): void
    {
        [$approval, $review] = $this->fixture();
        $approval['decision'] = 'PENDING';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review): void {
            (new NovicePromotionApprovedTransferPolicy())->assertMatches(
                $approval, 'transfer-1', $review, '2026-09-25 14:00:00'
            );
        });
        [$approval, $review] = $this->fixture();
        $approval['approved_at_utc'] = '2026-09-25 11:59:59';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review): void {
            (new NovicePromotionApprovedTransferPolicy())->assertMatches(
                $approval, 'transfer-1', $review, '2026-09-25 14:00:00'
            );
        });
    }

    public function testCannotClaimFutureApprovalOrInventReviewer(): void
    {
        [$approval, $review] = $this->fixture();
        $approval['approved_at_utc'] = '2026-09-25 14:00:01';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review): void {
            (new NovicePromotionApprovedTransferPolicy())->assertMatches(
                $approval, 'transfer-1', $review, '2026-09-25 14:00:00'
            );
        });
        [$approval, $review] = $this->fixture();
        $approval['reviewer_id'] = '';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review): void {
            (new NovicePromotionApprovedTransferPolicy())->assertMatches(
                $approval, 'transfer-1', $review, '2026-09-25 14:00:00'
            );
        });
    }

    private function fixture(): array
    {
        $review = [
            'CREATED_AT' => '2026-09-25 12:00:00',
            'UUID_ORIGINAL_APPLICATION' => 'first-application',
            'UUID_RECTIFICATIVE_FACTURA' => 'first-rectificative',
            'TO_UUID_OPERATION' => 'next-course',
            'AMOUNT' => '70.00',
            'POLICY_EVIDENCE_REF' => 'authorized-change-decision-1',
        ];
        $approval = [
            'review_uuid' => 'transfer-1',
            'decision_type' => 'NOVICE_DESTINATION_TRANSFER',
            'decision_id' => 'decision-1',
            'decision' => 'APPROVED',
            'reviewer_id' => 'authenticated-secretariat-1',
            'evidence_ref' => 'authorized-change-decision-1',
            'approved_at_utc' => '2026-09-25 12:05:00',
            'uuid_original_application' => 'first-application',
            'uuid_rectificative' => 'first-rectificative',
            'uuid_new_operation' => 'next-course',
            'approved_promotional_amount' => '70.00',
        ];
        return [$approval, $review];
    }
}
