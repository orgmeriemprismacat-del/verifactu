<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\NovicePromotionDerivedBalanceEligibilityPolicy;
use Prisma\Sif\Tests\Support\Assert;

/** Pure eligibility checks: no DB, payment gateway or fiscal issuer. */
final class NovicePromotionDerivedBalanceEligibilityPolicyTest
{
    public function testActiveHolderCanReserveWithinIndependentDerivedExpiry(): void
    {
        $policy = new NovicePromotionDerivedBalanceEligibilityPolicy();
        $policy->assertReservable(
            $this->balance(),
            'party-1',
            $this->time('2026-09-26 10:00:00'),
            $this->time('2026-09-26 11:00:00')
        );
        Assert::same(true, true);
    }

    public function testDifferentHolderOrExpiredBalanceCannotReserve(): void
    {
        $policy = new NovicePromotionDerivedBalanceEligibilityPolicy();
        Assert::throws(\InvalidArgumentException::class, function () use ($policy): void {
            $policy->assertReservable(
                $this->balance(),
                'other-party',
                $this->time('2026-09-26 10:00:00'),
                $this->time('2026-09-26 11:00:00')
            );
        });

        $expired = $this->balance();
        $expired['EXPIRES_AT'] = '2026-09-26 09:59:59';
        Assert::throws(\InvalidArgumentException::class, function () use ($policy, $expired): void {
            $policy->assertReservable(
                $expired,
                'party-1',
                $this->time('2026-09-26 10:00:00'),
                $this->time('2026-09-26 11:00:00')
            );
        });
    }

    public function testReservationCannotOutliveDerivedBalance(): void
    {
        $policy = new NovicePromotionDerivedBalanceEligibilityPolicy();
        Assert::throws(\InvalidArgumentException::class, function () use ($policy): void {
            $policy->assertReservable(
                $this->balance(),
                'party-1',
                $this->time('2026-09-26 10:00:00'),
                $this->time('2027-09-26 10:00:01')
            );
        });
    }

    public function testZeroAvailableDerivedValueCannotBeReserved(): void
    {
        $policy = new NovicePromotionDerivedBalanceEligibilityPolicy();
        $balance = $this->balance();
        $balance['AVAILABLE_PROMOTIONAL_AMOUNT'] = '0.00';
        Assert::throws(\InvalidArgumentException::class, function () use ($policy, $balance): void {
            $policy->assertReservable(
                $balance,
                'party-1',
                $this->time('2026-09-26 10:00:00'),
                $this->time('2026-09-26 11:00:00')
            );
        });
    }

    public function testOnlyLiveReservationCanBeConfirmedBeforeBothDeadlines(): void
    {
        $policy = new NovicePromotionDerivedBalanceEligibilityPolicy();
        $application = [
            'STATUS' => 'RESERVED',
            'UUID_DERIVED_BALANCE' => 'derived-1',
            'RESERVATION_EXPIRES_AT' => '2026-09-26 11:00:00',
        ];
        $policy->assertConfirmable(
            $this->balance(),
            $application,
            'party-1',
            $this->time('2026-09-26 10:30:00')
        );

        $application['RESERVATION_EXPIRES_AT'] = '2026-09-26 10:29:59';
        Assert::throws(\InvalidArgumentException::class, function () use ($policy, $application): void {
            $policy->assertConfirmable(
                $this->balance(),
                $application,
                'party-1',
                $this->time('2026-09-26 10:30:00')
            );
        });
    }

    private function balance(): array
    {
        return [
            'UUID_DERIVED_BALANCE' => 'derived-1',
            'HOLDER_PARTY_KEY' => 'party-1',
            'STATUS' => 'ACTIVE',
            'ISSUED_AT' => '2026-09-25 10:00:00',
            'EXPIRES_AT' => '2027-09-26 10:00:00',
            'AVAILABLE_PROMOTIONAL_AMOUNT' => '50.00',
        ];
    }

    private function time(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }
}
