<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\NovicePromotionAmountPolicy;
use Prisma\Sif\Tests\Support\Assert;

final class NovicePromotionAmountPolicyTest
{
    public function testOrdinaryDiscountsAreAppliedBeforeNoviceBalance(): void
    {
        // Ordinary price 120.00; ordinary discount 12.00 => 108.00.
        $result = (new NovicePromotionAmountPolicy())->allocate('90.00', '108.00');
        Assert::same('90.00', $result['applied_amount']);
        Assert::same('0.00', $result['remaining_promotion']);
        Assert::same('18.00', $result['destination_net_after_promotion']);
    }

    public function testLargerPromotionKeepsResidualWithinSameRight(): void
    {
        $result = (new NovicePromotionAmountPolicy())->allocate('90.00', '70.00');
        Assert::same('70.00', $result['applied_amount']);
        Assert::same('20.00', $result['remaining_promotion']);
        Assert::same('0.00', $result['destination_net_after_promotion']);
    }

    public function testChosenPartialAmountLeavesSpendableRemainder(): void
    {
        $result = (new NovicePromotionAmountPolicy())->allocate('90.00', '70.00', '40.00');
        Assert::same('40.00', $result['applied_amount']);
        Assert::same('50.00', $result['remaining_promotion']);
        Assert::same('30.00', $result['destination_net_after_promotion']);
    }

    public function testCentsDoNotRoundOrAllowFractionalCent(): void
    {
        $policy = new NovicePromotionAmountPolicy();
        $result = $policy->allocate('0.03', '0.02');
        Assert::same('0.02', $result['applied_amount']);
        Assert::same('0.01', $result['remaining_promotion']);
        Assert::same('0.00', $result['destination_net_after_promotion']);
        Assert::throws(\InvalidArgumentException::class, static function () use ($policy): void {
            $policy->allocate('90.00', '70.001');
        });
    }

    public function testCannotChooseMoreThanCourseNetOrAvailable(): void
    {
        $policy = new NovicePromotionAmountPolicy();
        Assert::throws(\InvalidArgumentException::class, static function () use ($policy): void {
            $policy->allocate('90.00', '70.00', '70.01');
        });
        Assert::throws(\InvalidArgumentException::class, static function () use ($policy): void {
            $policy->allocate('20.00', '70.00', '20.01');
        });
    }

    public function testZeroOrNegativeSelectionsCannotReserve(): void
    {
        $policy = new NovicePromotionAmountPolicy();
        foreach (['0.00', '-1.00', 'not-money'] as $requested) {
            Assert::throws(\InvalidArgumentException::class, static function () use ($policy, $requested): void {
                $policy->allocate('90.00', '70.00', $requested);
            });
        }
        Assert::throws(\InvalidArgumentException::class, static function () use ($policy): void {
            $policy->allocate('0.00', '70.00');
        });
    }
}
