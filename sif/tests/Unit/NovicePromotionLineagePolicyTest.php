<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\NovicePromotionLineagePolicy;
use Prisma\Sif\Tests\Support\Assert;

/**
 * Pure UC-111 accounting examples; NEVER executes MySQL, AEAT or cash refunds.
 */
final class NovicePromotionLineagePolicyTest
{
    public function testDirectOriginalUseAndUnusedRemainder(): void
    {
        $result = $this->policy()->planOriginalRefund('jasom', [
            $this->right('jasom', null, '90.00', '20.00'),
        ], [
            $this->application('first-course', 'jasom', '70.00', 'ACTIVE'),
        ]);

        Assert::same('20.00', $result['total_cancel_available']);
        Assert::same('70.00', $result['total_recover_active']);
        Assert::same('first-course', $result['recover_active_applications'][0]['application_id']);
        Assert::same(true, $result['review_required']);
    }

    public function testNinetyConvertedIntoCancellationRightThenFortyUsedClaimsOnlyForty(): void
    {
        $result = $this->policy()->planOriginalRefund('jasom', [
            $this->right('jasom', null, '90.00', '0.00'),
            $this->right('cancellation', 'original-course', '90.00', '50.00'),
        ], [
            $this->application(
                'original-course', 'jasom', '90.00', 'REPLACED_BY_DERIVED',
                null, 'cancellation'
            ),
            $this->application('later-course', 'cancellation', '40.00', 'ACTIVE'),
        ]);
        Assert::same('50.00', $result['total_cancel_available']);
        Assert::same('40.00', $result['total_recover_active']);
        Assert::same(1, count($result['recover_active_applications']));
        Assert::same('later-course', $result['recover_active_applications'][0]['application_id']);
        Assert::same('cancellation', $result['cancel_available'][0]['right_id']);
    }

    public function testOriginalRemainderAndCancellationRemainderAreBothCancelled(): void
    {
        $result = $this->policy()->planOriginalRefund('jasom', [
            $this->right('jasom', null, '90.00', '20.00'),
            $this->right('cancellation', 'first-course', '70.00', '30.00'),
        ], [
            $this->application(
                'first-course', 'jasom', '70.00', 'REPLACED_BY_DERIVED',
                null, 'cancellation'
            ),
            $this->application('new-course', 'cancellation', '40.00', 'ACTIVE'),
        ]);
        Assert::same('50.00', $result['total_cancel_available']);
        Assert::same('40.00', $result['total_recover_active']);
        Assert::same(2, count($result['cancel_available']));
    }

    public function testCourseChangeTransfersAttributionWithoutDoubleConsumption(): void
    {
        $result = $this->policy()->planOriginalRefund('jasom', [
            $this->right('jasom', null, '90.00', '20.00'),
        ], [
            $this->application('old-course', 'jasom', '70.00', 'REPLACED_BY_TRANSFER', 'new-course'),
            $this->application('new-course', 'jasom', '70.00', 'ACTIVE'),
        ]);
        Assert::same('20.00', $result['total_cancel_available']);
        Assert::same('70.00', $result['total_recover_active']);
        Assert::same('new-course', $result['recover_active_applications'][0]['application_id']);
    }

    public function testNestedCancellationBalanceCountsOnlyCurrentDescendant(): void
    {
        $result = $this->policy()->planOriginalRefund('jasom', [
            $this->right('jasom', null, '90.00', '0.00'),
            $this->right('first-cancellation', 'root-use', '90.00', '0.00'),
            $this->right('second-cancellation', 'first-derived-use', '90.00', '50.00'),
        ], [
            $this->application('root-use', 'jasom', '90.00', 'REPLACED_BY_DERIVED', null, 'first-cancellation'),
            $this->application(
                'first-derived-use', 'first-cancellation', '90.00', 'REPLACED_BY_DERIVED',
                null, 'second-cancellation'
            ),
            $this->application('second-derived-use', 'second-cancellation', '40.00', 'ACTIVE'),
        ]);
        Assert::same('50.00', $result['total_cancel_available']);
        Assert::same('40.00', $result['total_recover_active']);
        Assert::same('second-derived-use', $result['recover_active_applications'][0]['application_id']);
    }

    public function testReservationMustBeReconciledBeforeOriginalRefund(): void
    {
        Assert::throws(\InvalidArgumentException::class, function (): void {
            $this->policy()->planOriginalRefund('jasom', [
                $this->right('jasom', null, '90.00', '50.00'),
            ], [
                $this->application('pending-course', 'jasom', '40.00', 'RESERVED'),
            ]);
        });
    }

    public function testMissingDerivedLinkCannotHidePreviousConsumption(): void
    {
        Assert::throws(\InvalidArgumentException::class, function (): void {
            $this->policy()->planOriginalRefund('jasom', [
                $this->right('jasom', null, '90.00', '0.00'),
                $this->right('unlinked', 'old-course', '90.00', '50.00'),
            ], [
                $this->application('old-course', 'jasom', '90.00', 'ACTIVE'),
                $this->application('new-course', 'unlinked', '40.00', 'ACTIVE'),
            ]);
        });
    }

    public function testDerivedAmountCannotExceedCancelledPromotionalUse(): void
    {
        Assert::throws(\InvalidArgumentException::class, function (): void {
            $this->policy()->planOriginalRefund('jasom', [
                $this->right('jasom', null, '90.00', '0.00'),
                $this->right('inflated', 'old-course', '91.00', '91.00'),
            ], [
                $this->application('old-course', 'jasom', '90.00', 'REPLACED_BY_DERIVED', null, 'inflated'),
            ]);
        });
    }

    public function testDuplicateActiveExposuresCannotExceedRootIssued(): void
    {
        Assert::throws(\InvalidArgumentException::class, function (): void {
            $this->policy()->planOriginalRefund('jasom', [
                $this->right('jasom', null, '90.00', '0.00'),
            ], [
                $this->application('first', 'jasom', '60.00', 'ACTIVE'),
                $this->application('second', 'jasom', '60.00', 'ACTIVE'),
            ]);
        });
    }

    public function testTransferThatChangesAmountFailsClosed(): void
    {
        Assert::throws(\InvalidArgumentException::class, function (): void {
            $this->policy()->planOriginalRefund('jasom', [
                $this->right('jasom', null, '90.00', '20.00'),
            ], [
                $this->application('old', 'jasom', '70.00', 'REPLACED_BY_TRANSFER', 'new'),
                $this->application('new', 'jasom', '40.00', 'ACTIVE'),
            ]);
        });
    }

    private function policy(): NovicePromotionLineagePolicy
    {
        return new NovicePromotionLineagePolicy();
    }

    private function right(
        string $id,
        ?string $parent,
        string $issued,
        string $available,
        string $status = 'ACTIVE'
    ): array {
        return [
            'id' => $id,
            'parent_application_id' => $parent,
            'issued' => $issued,
            'available' => $available,
            'status' => $status,
        ];
    }

    private function application(
        string $id,
        string $right,
        string $amount,
        string $status,
        ?string $successor = null,
        ?string $derived = null
    ): array {
        return [
            'id' => $id,
            'right_id' => $right,
            'amount' => $amount,
            'status' => $status,
            'successor_application_id' => $successor,
            'derived_right_id' => $derived,
        ];
    }
}
