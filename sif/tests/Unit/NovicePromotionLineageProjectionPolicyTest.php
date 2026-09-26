<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\NovicePromotionLineagePolicy;
use Prisma\Sif\Domain\NovicePromotionLineageProjectionPolicy;
use Prisma\Sif\Tests\Support\Assert;

/**
 * Pure projection + refund-plan examples. No MySQL is used.
 */
final class NovicePromotionLineageProjectionPolicyTest
{
    public function testSuccessiveTransfersExposeOnlyLatestCourse(): void
    {
        $graph = $this->project(
            [['UUID_APPLICATION' => 'a1', 'AMOUNT' => '90.00', 'STATUS' => 'REVERSED', 'REASON_CODE' => 'TRANSFERRED_TO_COURSE']],
            [],
            [],
            [
                $this->transfer('t1', '90.00', 'CANCELLED', 'a1', null, null, 'TRANSFERRED_TO_COURSE'),
                $this->transfer('t2', '90.00', 'CONFIRMED', null, null, 't1'),
            ]
        );
        $plan = $this->plan($graph);
        Assert::same('0.00', $plan['total_cancel_available']);
        Assert::same('90.00', $plan['total_recover_active']);
        Assert::same('transfer:t2', $plan['recover_active_applications'][0]['application_id']);
    }

    public function testDerivedApplicationTransferExposesTransferNotOldApplication(): void
    {
        $graph = $this->project(
            [['UUID_APPLICATION' => 'a1', 'AMOUNT' => '90.00', 'STATUS' => 'REVERSED', 'REASON_CODE' => 'CONVERTED_TO_DERIVED']],
            [$this->balance('d1', '90.00', '50.00', 'ACTIVE', 'a1')],
            [[
                'UUID_DERIVED_APPLICATION' => 'da1',
                'UUID_DERIVED_BALANCE' => 'd1',
                'AMOUNT' => '40.00',
                'STATUS' => 'TRANSFERRED',
                'REASON_CODE' => 'TRANSFERRED_TO_COURSE',
            ]],
            [$this->transfer('t1', '40.00', 'CONFIRMED', null, 'da1')]
        );
        $plan = $this->plan($graph);
        Assert::same('50.00', $plan['total_cancel_available']);
        Assert::same('40.00', $plan['total_recover_active']);
        Assert::same('transfer:t1', $plan['recover_active_applications'][0]['application_id']);
    }

    public function testTransferredCourseConvertedToDerivedRightDoesNotDoubleCountPredecessor(): void
    {
        $graph = $this->project(
            [['UUID_APPLICATION' => 'a1', 'AMOUNT' => '90.00', 'STATUS' => 'REVERSED', 'REASON_CODE' => 'TRANSFERRED_TO_COURSE']],
            [$this->balance('d2', '90.00', '50.00', 'ACTIVE', null, null, 't1')],
            [[
                'UUID_DERIVED_APPLICATION' => 'da2',
                'UUID_DERIVED_BALANCE' => 'd2',
                'AMOUNT' => '40.00',
                'STATUS' => 'APPLIED',
                'REASON_CODE' => null,
            ]],
            [$this->transfer('t1', '90.00', 'CANCELLED', 'a1', null, null, 'CONVERTED_TO_DERIVED')]
        );
        $plan = $this->plan($graph);
        Assert::same('50.00', $plan['total_cancel_available']);
        Assert::same('40.00', $plan['total_recover_active']);
        Assert::same('dapp:da2', $plan['recover_active_applications'][0]['application_id']);
    }

    public function testPendingTransferBlocksRootRefundProjection(): void
    {
        Assert::throws(\InvalidArgumentException::class, function (): void {
            $this->project(
                [['UUID_APPLICATION' => 'a1', 'AMOUNT' => '90.00', 'STATUS' => 'APPLIED', 'REASON_CODE' => null]],
                [],
                [],
                [$this->transfer('t1', '90.00', 'PENDING_FISCAL_REVIEW', 'a1')]
            );
        });
    }

    public function testPendingDerivedReviewBlocksRootRefundProjection(): void
    {
        Assert::throws(\InvalidArgumentException::class, function (): void {
            $this->project(
                [['UUID_APPLICATION' => 'a1', 'AMOUNT' => '90.00', 'STATUS' => 'APPLIED', 'REASON_CODE' => null]],
                [$this->balance('d1', '90.00', '0.00', 'PENDING_FISCAL_REVIEW', 'a1')],
                [],
                []
            );
        });
    }

    public function testMissingTransferSuccessorFailsInsteadOfRevivingHistoricalCourse(): void
    {
        Assert::throws(\InvalidArgumentException::class, function (): void {
            $graph = $this->project(
                [['UUID_APPLICATION' => 'a1', 'AMOUNT' => '90.00', 'STATUS' => 'REVERSED', 'REASON_CODE' => 'TRANSFERRED_TO_COURSE']],
                [],
                [],
                []
            );
            $this->plan($graph);
        });
    }

    public function testReservedDerivedApplicationBlocksRefundPlan(): void
    {
        $graph = $this->project(
            [['UUID_APPLICATION' => 'a1', 'AMOUNT' => '90.00', 'STATUS' => 'REVERSED', 'REASON_CODE' => 'CONVERTED_TO_DERIVED']],
            [$this->balance('d1', '90.00', '50.00', 'ACTIVE', 'a1')],
            [[
                'UUID_DERIVED_APPLICATION' => 'da1',
                'UUID_DERIVED_BALANCE' => 'd1',
                'AMOUNT' => '40.00',
                'STATUS' => 'RESERVED',
                'REASON_CODE' => null,
            ]],
            []
        );
        Assert::throws(\InvalidArgumentException::class, function () use ($graph): void {
            $this->plan($graph);
        });
    }

    private function project(
        array $originalApplications,
        array $derivedBalances,
        array $derivedApplications,
        array $transfers
    ): array {
        return (new NovicePromotionLineageProjectionPolicy())->project(
            [
                'UUID_ENTITLEMENT' => 'root-1',
                'ENTITLEMENT_STATUS' => 'ACTIVE',
                'ORIGINAL_CASH_AMOUNT' => '90.00',
                'AVAILABLE_AMOUNT' => '0.00',
            ],
            $originalApplications,
            $derivedBalances,
            $derivedApplications,
            $transfers
        );
    }

    private function plan(array $graph): array
    {
        return (new NovicePromotionLineagePolicy())->planOriginalRefund(
            $graph['root_right_id'],
            $graph['rights'],
            $graph['applications']
        );
    }

    private function balance(
        string $id,
        string $issued,
        string $available,
        string $status,
        ?string $sourceOriginal = null,
        ?string $sourceDerived = null,
        ?string $sourceTransfer = null
    ): array {
        return [
            'UUID_DERIVED_BALANCE' => $id,
            'PARENT_UUID_DERIVED_BALANCE' => null,
            'SOURCE_UUID_APPLICATION' => $sourceOriginal,
            'SOURCE_UUID_DERIVED_APPLICATION' => $sourceDerived,
            'SOURCE_UUID_TRANSFER' => $sourceTransfer,
            'PROMOTIONAL_ORIGIN_AMOUNT' => $issued,
            'AVAILABLE_PROMOTIONAL_AMOUNT' => $available,
            'STATUS' => $status,
        ];
    }

    private function transfer(
        string $id,
        string $amount,
        string $status,
        ?string $sourceOriginal = null,
        ?string $sourceDerived = null,
        ?string $previous = null,
        ?string $closeReason = null
    ): array {
        return [
            'UUID_TRANSFER' => $id,
            'ROOT_UUID_ENTITLEMENT' => 'root-1',
            'UUID_ORIGINAL_APPLICATION' => $sourceOriginal,
            'UUID_DERIVED_APPLICATION' => $sourceDerived,
            'PREVIOUS_UUID_TRANSFER' => $previous,
            'AMOUNT' => $amount,
            'STATUS' => $status,
            'CLOSE_REASON' => $closeReason,
        ];
    }
}
