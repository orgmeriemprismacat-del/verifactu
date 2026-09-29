<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\NovicePromotionRecoveryCompletionPolicy;
use Prisma\Sif\Tests\Support\Assert;

/** Pure closure accounting; no MySQL or payment side effects. */
final class NovicePromotionRecoveryCompletionPolicyTest
{
    public function testAllRecoveredClosesWithFullRecoveredValue(): void
    {
        $summary = (new NovicePromotionRecoveryCompletionPolicy())->summarize(
            $this->review('90.00'),
            [
                $this->recovery('r1', 'RECOVERED', '40.00'),
                $this->recovery('r2', 'RECOVERED', '50.00'),
            ]
        );

        Assert::same('90.00', $summary['accounted_total']);
        Assert::same('90.00', $summary['recovered_amount']);
        Assert::same(true, $summary['all_value_recovered']);
        Assert::same(2, $summary['recovered_count']);
    }

    public function testWaivedAndCancelledRemainDistinctButStillAccountForPlan(): void
    {
        $summary = (new NovicePromotionRecoveryCompletionPolicy())->summarize(
            $this->review('90.00'),
            [
                $this->recovery('r1', 'RECOVERED', '40.00'),
                $this->recovery('r2', 'WAIVED', '30.00'),
                $this->recovery('r3', 'CANCELLED', '20.00'),
            ]
        );

        Assert::same('40.00', $summary['recovered_amount']);
        Assert::same('30.00', $summary['waived_amount']);
        Assert::same('20.00', $summary['cancelled_amount']);
        Assert::same(false, $summary['all_value_recovered']);
        Assert::same(true, $summary['all_items_resolved']);
    }

    public function testPendingRecoveryBlocksClosure(): void
    {
        $pending = $this->recovery('r1', 'PENDING_RECOVERY', '90.00');
        $pending['RESOLVED_AT'] = null;
        $pending['RESOLUTION_CODE'] = null;
        $pending['RESOLUTION_ID'] = null;
        $pending['RESOLVED_BY'] = null;
        $pending['RESOLUTION_EVIDENCE_REF'] = null;

        Assert::throws(\InvalidArgumentException::class, function () use ($pending): void {
            (new NovicePromotionRecoveryCompletionPolicy())->summarize(
                $this->review('90.00'),
                [$pending]
            );
        });
    }

    public function testResolvedAmountsMustExactlyMatchFrozenPlan(): void
    {
        Assert::throws(\InvalidArgumentException::class, function (): void {
            (new NovicePromotionRecoveryCompletionPolicy())->summarize(
                $this->review('90.00'),
                [
                    $this->recovery('r1', 'RECOVERED', '40.00'),
                    $this->recovery('r2', 'WAIVED', '40.00'),
                ]
            );
        });
    }

    public function testZeroRecoveryPlanCanCloseWithNoItems(): void
    {
        $summary = (new NovicePromotionRecoveryCompletionPolicy())->summarize(
            $this->review('0.00'),
            []
        );

        Assert::same('0.00', $summary['accounted_total']);
        Assert::same(0, $summary['total_items']);
        Assert::same(true, $summary['all_value_recovered']);
    }

    private function review(string $recover): array
    {
        return [
            'UUID_REVIEW' => 'review-1',
            'ROOT_UUID_ENTITLEMENT' => 'root-1',
            'STATUS' => 'EXECUTED',
            'PLAN_JSON' => json_encode([
                'total_cancel_available' => '0.00',
                'total_recover_active' => $recover,
            ], JSON_THROW_ON_ERROR),
        ];
    }

    private function recovery(string $id, string $status, string $amount): array
    {
        return [
            'UUID_RECOVERY' => $id,
            'UUID_REVIEW' => 'review-1',
            'ROOT_UUID_ENTITLEMENT' => 'root-1',
            'AMOUNT' => $amount,
            'STATUS' => $status,
            'RESOLVED_AT' => '2026-09-27 00:30:00',
            'RESOLUTION_CODE' => 'TEST_RESOLUTION',
            'RESOLUTION_ID' => 'resolution-' . $id,
            'RESOLVED_BY' => 'test-actor',
            'RESOLUTION_EVIDENCE_REF' => 'evidence-' . $id,
        ];
    }
}
