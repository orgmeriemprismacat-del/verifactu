<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\NovicePromotionDestinationAdjustmentPolicy;
use Prisma\Sif\Tests\Support\Assert;

final class NovicePromotionDestinationAdjustmentPolicyTest
{
    public function testFullCancellationCreatesDistinctPromotionalBalanceForOneYear(): void
    {
        $plan = $this->policy()->planCancellation(
            '90.00', '0.00', '90.00', '0.00',
            new \DateTimeImmutable('2026-09-25 18:00:00', new \DateTimeZone('Europe/Madrid'))
        );
        Assert::same('90.00', $plan['promotional_derived_amount']);
        Assert::same('0.00', $plan['cash_refund_or_credit_eligible']);
        Assert::same('90.00', $plan['total_eligible_value']);
        Assert::same('0.00', $plan['promotional_forfeited']);
        Assert::same(true, $plan['creates_promotional_derived_right']);
        Assert::same('2026-09-25 16:00:00', $plan['derived_issued_at_utc']);
        Assert::same('2027-09-25 16:00:00', $plan['derived_expires_at_utc']);
    }

    public function testPartialRefundPreservesSeparateProvenanceOfPromoAndRealCash(): void
    {
        $plan = $this->policy()->planCancellation(
            '90.00', '30.00', '45.00', '15.00',
            new \DateTimeImmutable('2026-09-25 18:00:00', new \DateTimeZone('Europe/Madrid'))
        );
        Assert::same('45.00', $plan['promotional_derived_amount']);
        Assert::same('15.00', $plan['cash_refund_or_credit_eligible']);
        Assert::same('60.00', $plan['total_eligible_value']);
        Assert::same('45.00', $plan['promotional_forfeited']);
    }

    public function testCashOnlyCancellationDoesNotCreateFakePromotion(): void
    {
        $plan = $this->policy()->planCancellation(
            '90.00', '30.00', '0.00', '20.00',
            new \DateTimeImmutable('2026-09-25 18:00:00', new \DateTimeZone('Europe/Madrid'))
        );
        Assert::same(false, $plan['creates_promotional_derived_right']);
        Assert::same(null, $plan['derived_expires_at_utc']);
        Assert::same('20.00', $plan['cash_refund_or_credit_eligible']);
    }

    public function testDerivedPartCannotExceedHistoricalPromotion(): void
    {
        Assert::throws(\InvalidArgumentException::class, function (): void {
            $this->policy()->planCancellation(
                '90.00', '30.00', '90.01', '0.00',
                new \DateTimeImmutable('2026-09-25 18:00:00', new \DateTimeZone('Europe/Madrid'))
            );
        });
    }

    public function testExternalCashPartCannotExceedConfirmedCash(): void
    {
        Assert::throws(\InvalidArgumentException::class, function (): void {
            $this->policy()->planCancellation(
                '90.00', '30.00', '90.00', '30.01',
                new \DateTimeImmutable('2026-09-25 18:00:00', new \DateTimeZone('Europe/Madrid'))
            );
        });
    }

    public function testCourseChangeTransfersAttributionInsteadOfGrantingNewPromotion(): void
    {
        $plan = $this->policy()->planCourseChange('70.00', '90.00');
        Assert::same('70.00', $plan['transfer_promotion']);
        Assert::same('20.00', $plan['new_course_remaining_before_real_payments']);
        Assert::same(false, $plan['new_promotion_grant']);
        Assert::same(false, $plan['restore_original_novice_balance']);
    }

    public function testCheaperCourseRequiresManualFiscalAdjustment(): void
    {
        Assert::throws(\InvalidArgumentException::class, function (): void {
            $this->policy()->planCourseChange('70.00', '60.00');
        });
    }

    private function policy(): NovicePromotionDestinationAdjustmentPolicy
    {
        return new NovicePromotionDestinationAdjustmentPolicy();
    }
}
