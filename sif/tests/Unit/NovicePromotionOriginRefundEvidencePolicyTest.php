<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\NovicePromotionOriginRefundEvidencePolicy;
use Prisma\Sif\Tests\Support\Assert;

final class NovicePromotionOriginRefundEvidencePolicyTest
{
    public function testConfirmedRefundMatchesFrozenReviewAndFullAmount(): void
    {
        [$evidence, $review] = $this->fixture();
        (new NovicePromotionOriginRefundEvidencePolicy())->assertMatches(
            $evidence, $review, '90.00', '2026-09-27 01:30:00'
        );
        Assert::same('90.00', $evidence['refunded_amount']);
    }

    public function testPartialRefundCannotUnlockCommercialCancellation(): void
    {
        [$evidence, $review] = $this->fixture();
        $evidence['refunded_amount'] = '89.99';
        Assert::throws(\InvalidArgumentException::class, static function () use ($evidence, $review): void {
            (new NovicePromotionOriginRefundEvidencePolicy())->assertMatches(
                $evidence, $review, '90.00', '2026-09-27 01:30:00'
            );
        });
    }

    public function testOtherRootOrOriginCannotReuseRefundEvidence(): void
    {
        [$evidence, $review] = $this->fixture();
        $evidence['root_uuid_entitlement'] = 'other-root';
        Assert::throws(\InvalidArgumentException::class, static function () use ($evidence, $review): void {
            (new NovicePromotionOriginRefundEvidencePolicy())->assertMatches(
                $evidence, $review, '90.00', '2026-09-27 01:30:00'
            );
        });

        [$evidence, $review] = $this->fixture();
        $evidence['origin_uuid_operation'] = 'other-jasom';
        Assert::throws(\InvalidArgumentException::class, static function () use ($evidence, $review): void {
            (new NovicePromotionOriginRefundEvidencePolicy())->assertMatches(
                $evidence, $review, '90.00', '2026-09-27 01:30:00'
            );
        });
    }

    public function testRefundConfirmationCannotPredateReviewOrBeFuture(): void
    {
        [$evidence, $review] = $this->fixture();
        $evidence['confirmed_at_utc'] = '2026-09-27 00:59:59';
        Assert::throws(\InvalidArgumentException::class, static function () use ($evidence, $review): void {
            (new NovicePromotionOriginRefundEvidencePolicy())->assertMatches(
                $evidence, $review, '90.00', '2026-09-27 01:30:00'
            );
        });

        [$evidence, $review] = $this->fixture();
        $evidence['confirmed_at_utc'] = '2026-09-27 01:30:01';
        Assert::throws(\InvalidArgumentException::class, static function () use ($evidence, $review): void {
            (new NovicePromotionOriginRefundEvidencePolicy())->assertMatches(
                $evidence, $review, '90.00', '2026-09-27 01:30:00'
            );
        });
    }

    public function testEvidenceIdIsMandatoryAndBounded(): void
    {
        [$evidence, $review] = $this->fixture();
        $evidence['refund_evidence_id'] = '';
        Assert::throws(\InvalidArgumentException::class, static function () use ($evidence, $review): void {
            (new NovicePromotionOriginRefundEvidencePolicy())->assertMatches(
                $evidence, $review, '90.00', '2026-09-27 01:30:00'
            );
        });
    }

    private function fixture(): array
    {
        $review = [
            'UUID_REVIEW' => 'refund-review-1',
            'ROOT_UUID_ENTITLEMENT' => 'root-1',
            'ORIGIN_UUID_OPERATION' => 'jasom-op',
            'REQUESTED_AT' => '2026-09-27 01:00:00',
        ];
        $evidence = [
            'review_uuid' => 'refund-review-1',
            'refund_evidence_id' => 'provider-refund-1',
            'root_uuid_entitlement' => 'root-1',
            'origin_uuid_operation' => 'jasom-op',
            'refunded_amount' => '90.00',
            'confirmed_at_utc' => '2026-09-27 01:20:00',
        ];
        return [$evidence, $review];
    }
}
