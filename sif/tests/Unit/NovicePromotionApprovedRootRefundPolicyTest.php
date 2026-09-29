<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\NovicePromotionApprovedRootRefundPolicy;
use Prisma\Sif\Tests\Support\Assert;

final class NovicePromotionApprovedRootRefundPolicyTest
{
    public function testExactFinalDecisionMatchesFrozenReview(): void
    {
        [$approval, $review] = $this->fixture();
        (new NovicePromotionApprovedRootRefundPolicy())->assertMatches(
            $approval, $review, '2026-09-27 00:30:00'
        );
        Assert::same('plan-hash-1', $approval['plan_hash']);
    }

    public function testDifferentPlanHashOrTotalsCannotBeApproved(): void
    {
        foreach ([
            ['plan_hash', 'other-hash'],
            ['total_cancel_available', '50.01'],
            ['total_recover_active', '39.99'],
        ] as [$field, $value]) {
            [$approval, $review] = $this->fixture();
            $approval[$field] = $value;
            Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review): void {
                (new NovicePromotionApprovedRootRefundPolicy())->assertMatches(
                    $approval, $review, '2026-09-27 00:30:00'
                );
            });
        }
    }

    public function testDifferentRootOrReviewCannotBeApproved(): void
    {
        [$approval, $review] = $this->fixture();
        $approval['root_uuid_entitlement'] = 'other-root';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review): void {
            (new NovicePromotionApprovedRootRefundPolicy())->assertMatches(
                $approval, $review, '2026-09-27 00:30:00'
            );
        });

        [$approval, $review] = $this->fixture();
        $approval['review_uuid'] = 'other-review';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review): void {
            (new NovicePromotionApprovedRootRefundPolicy())->assertMatches(
                $approval, $review, '2026-09-27 00:30:00'
            );
        });
    }

    public function testPendingOrFutureDecisionFailsClosed(): void
    {
        [$approval, $review] = $this->fixture();
        $approval['decision'] = 'PENDING';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review): void {
            (new NovicePromotionApprovedRootRefundPolicy())->assertMatches(
                $approval, $review, '2026-09-27 00:30:00'
            );
        });

        [$approval, $review] = $this->fixture();
        $approval['approved_at_utc'] = '2026-09-27 00:30:01';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review): void {
            (new NovicePromotionApprovedRootRefundPolicy())->assertMatches(
                $approval, $review, '2026-09-27 00:30:00'
            );
        });
    }

    public function testNonPendingReviewCannotBeApprovedAgain(): void
    {
        [$approval, $review] = $this->fixture();
        $review['STATUS'] = 'REJECTED';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review): void {
            (new NovicePromotionApprovedRootRefundPolicy())->assertMatches(
                $approval, $review, '2026-09-27 00:30:00'
            );
        });
    }

    private function fixture(): array
    {
        $plan = [
            'total_cancel_available' => '50.00',
            'total_recover_active' => '40.00',
        ];
        $review = [
            'UUID_REVIEW' => 'refund-review-1',
            'ROOT_UUID_ENTITLEMENT' => 'root-1',
            'PLAN_HASH' => 'plan-hash-1',
            'PLAN_JSON' => json_encode($plan, JSON_THROW_ON_ERROR),
            'STATUS' => 'PENDING_APPROVAL',
            'REQUEST_EVIDENCE_REF' => 'refund-request-1',
            'REQUESTED_AT' => '2026-09-27 00:00:00',
        ];
        $approval = [
            'review_uuid' => 'refund-review-1',
            'decision_type' => 'NOVICE_ROOT_JASOM_REFUND',
            'decision_id' => 'refund-decision-1',
            'decision' => 'APPROVED',
            'reviewer_id' => 'secretariat-user',
            'evidence_ref' => 'refund-request-1',
            'approved_at_utc' => '2026-09-27 00:10:00',
            'root_uuid_entitlement' => 'root-1',
            'plan_hash' => 'plan-hash-1',
            'total_cancel_available' => '50.00',
            'total_recover_active' => '40.00',
        ];
        return [$approval, $review];
    }
}
