<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\CourseChangeImpactClassifier;
use Prisma\Sif\Tests\Support\Assert;

final class CourseChangeImpactClassifierTest
{
    public function testSameAmountWithoutInvoiceNeedsNoFiscalCorrection(): void
    {
        $result = $this->classifier()->classify($this->base());

        Assert::same('SAME', $result['price_relation']);
        Assert::same('NONE', $result['fiscal_decision']);
        Assert::same('NONE', $result['economic_decision']);
        Assert::same('STANDARD', $result['pricing_mode']);
    }

    public function testHigherAmountWithSameServiceUsesDifferenceAndLeavesAmountDue(): void
    {
        $result = $this->classifier()->classify($this->base([
            'standard_target_amount' => '130.00',
            'proposed_target_amount' => '130.00',
            'invoice_issued' => true,
        ]));

        Assert::same('HIGHER', $result['price_relation']);
        Assert::same('RECTIFY_DIFFERENCE', $result['fiscal_decision']);
        Assert::same('AMOUNT_DUE', $result['economic_decision']);
        Assert::same('30.00', $result['amount_due']);
        Assert::same('+30.00', $result['course_delta']);
    }

    public function testLowerAmountWithSameServiceUsesDifferenceAndCreatesExcessToResolve(): void
    {
        $result = $this->classifier()->classify($this->base([
            'standard_target_amount' => '80.00',
            'proposed_target_amount' => '80.00',
            'invoice_issued' => true,
        ]));

        Assert::same('LOWER', $result['price_relation']);
        Assert::same('RECTIFY_DIFFERENCE', $result['fiscal_decision']);
        Assert::same('EXCESS_TO_RESOLVE', $result['economic_decision']);
        Assert::same('20.00', $result['excess_amount']);
        Assert::same('-20.00', $result['course_delta']);
    }

    public function testDifferentCourseWithSameAmountProposesRectifyAndReissue(): void
    {
        $result = $this->classifier()->classify($this->base([
            'invoice_issued' => true,
            'service_changed' => true,
        ]));

        Assert::same('SAME', $result['price_relation']);
        Assert::same('RECTIFY_AND_REISSUE', $result['fiscal_decision']);
        Assert::same('SERVICE_OR_CONCEPT_CHANGED', $result['fiscal_reason_code']);
        Assert::same('NONE', $result['economic_decision']);
    }

    public function testManualPriceRequiresIndependentReason(): void
    {
        Assert::throws(SifException::class, function (): void {
            $this->classifier()->classify($this->base([
                'standard_target_amount' => '120.00',
                'proposed_target_amount' => '95.00',
                'manual_price_reason' => '',
            ]));
        }, 422);
    }

    public function testManualPriceWithReasonIsExplicitlyClassified(): void
    {
        $result = $this->classifier()->classify($this->base([
            'standard_target_amount' => '120.00',
            'proposed_target_amount' => '95.00',
            'manual_price_reason' => 'Preu excepcional aprovat per gestió',
            'invoice_issued' => true,
        ]));

        Assert::same('MANUAL', $result['pricing_mode']);
        Assert::same('LOWER', $result['price_relation']);
        Assert::same('RECTIFY_DIFFERENCE', $result['fiscal_decision']);
        Assert::same('5.00', $result['excess_amount']);
    }

    public function testManagementFeeRemainsSeparateButAffectsOutstandingBalance(): void
    {
        $result = $this->classifier()->classify($this->base([
            'management_fee' => '15.00',
            'invoice_issued' => true,
        ]));

        Assert::same('100.00', $result['effective_target_amount']);
        Assert::same('15.00', $result['management_fee']);
        Assert::same('115.00', $result['target_total']);
        Assert::same('RECTIFY_DIFFERENCE', $result['fiscal_decision']);
        Assert::same('15.00', $result['amount_due']);
    }

    private function classifier(): CourseChangeImpactClassifier
    {
        return new CourseChangeImpactClassifier();
    }

    private function base(array $overrides = []): array
    {
        return array_merge([
            'original_amount' => '100.00',
            'standard_target_amount' => '100.00',
            'proposed_target_amount' => '100.00',
            'management_fee' => '0.00',
            'paid_amount' => '100.00',
            'invoice_issued' => false,
            'service_changed' => false,
            'manual_price_reason' => null,
        ], $overrides);
    }
}
