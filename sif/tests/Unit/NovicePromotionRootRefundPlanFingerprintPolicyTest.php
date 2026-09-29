<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\NovicePromotionRootRefundPlanFingerprintPolicy;
use Prisma\Sif\Tests\Support\Assert;

final class NovicePromotionRootRefundPlanFingerprintPolicyTest
{
    public function testSamePlanWithDifferentListOrderHasSameHash(): void
    {
        $policy = new NovicePromotionRootRefundPlanFingerprintPolicy();
        $a = $policy->fingerprint($this->plan());
        $plan = $this->plan();
        $plan['cancel_available'] = array_reverse($plan['cancel_available']);
        $plan['recover_active_applications'] = array_reverse($plan['recover_active_applications']);
        $b = $policy->fingerprint($plan);

        Assert::same($a['hash'], $b['hash']);
        Assert::same($a['json'], $b['json']);
    }

    public function testChangingRecoveryAmountChangesHash(): void
    {
        $policy = new NovicePromotionRootRefundPlanFingerprintPolicy();
        $a = $policy->fingerprint($this->plan());
        $plan = $this->plan();
        $plan['recover_active_applications'][0]['amount'] = '40.01';
        $plan['total_recover_active'] = '70.01';
        $b = $policy->fingerprint($plan);

        Assert::same(false, hash_equals($a['hash'], $b['hash']));
    }

    public function testChangingLogicalDestinationChangesHash(): void
    {
        $policy = new NovicePromotionRootRefundPlanFingerprintPolicy();
        $a = $policy->fingerprint($this->plan());
        $plan = $this->plan();
        $plan['recover_active_applications'][0]['application_id'] = 'transfer:other';
        $b = $policy->fingerprint($plan);

        Assert::same(false, hash_equals($a['hash'], $b['hash']));
    }

    public function testMissingLogicalIdFailsClosed(): void
    {
        Assert::throws(\InvalidArgumentException::class, function (): void {
            $plan = $this->plan();
            unset($plan['cancel_available'][0]['right_id']);
            (new NovicePromotionRootRefundPlanFingerprintPolicy())->fingerprint($plan);
        });
    }

    public function testMalformedAmountFailsClosed(): void
    {
        Assert::throws(\InvalidArgumentException::class, function (): void {
            $plan = $this->plan();
            $plan['total_cancel_available'] = '50.001';
            (new NovicePromotionRootRefundPlanFingerprintPolicy())->fingerprint($plan);
        });
    }

    private function plan(): array
    {
        return [
            'root' => [
                'uuid_entitlement' => 'root-1',
                'holder_party_key' => 'party-1',
                'origin_uuid_operation' => 'jasom-op',
            ],
            'cancel_available' => [
                ['right_id' => 'right:d2', 'amount' => '30.00'],
                ['right_id' => 'root:root-1', 'amount' => '20.00'],
            ],
            'recover_active_applications' => [
                ['application_id' => 'transfer:t2', 'right_id' => 'root:root-1', 'amount' => '40.00'],
                ['application_id' => 'dapp:da2', 'right_id' => 'right:d2', 'amount' => '30.00'],
            ],
            'total_cancel_available' => '50.00',
            'total_recover_active' => '70.00',
        ];
    }
}
