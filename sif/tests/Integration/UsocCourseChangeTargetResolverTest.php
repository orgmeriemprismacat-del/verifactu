<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\UsocCourseChangeTargetResolver;
use Prisma\Sif\Tests\Support\Assert;

final class UsocCourseChangeTargetResolverTest
{
    public function testResolvesStudentEntitySplitAndAssignsFeeToStudent(): void
    {
        $result = (new UsocCourseChangeTargetResolver())->resolve([
            'target_standard_course_amount' => '100.00',
            'target_student_course_amount' => '80.00',
            'management_fee' => '5.00',
        ]);

        Assert::same(true, $result['ok']);
        Assert::same('100.00', $result['target_standard_course_amount']);
        Assert::same('80.00', $result['target_student_course_amount']);
        Assert::same('20.00', $result['target_entity_course_amount']);
        Assert::same('5.00', $result['management_fee']);
        Assert::same('85.00', $result['target_student_total']);
        Assert::same('20.00', $result['target_entity_total']);
        Assert::same('105.00', $result['target_combined_total']);
        Assert::same(true, $result['entity_invoice_required']);
        Assert::same(true, $result['invariants']['management_fee_belongs_to_student']);
        Assert::same(true, $result['invariants']['never_cross_payer_funds']);
    }

    public function testAcceptsZeroEntityDifferenceWithoutInventingEntityInvoice(): void
    {
        $result = (new UsocCourseChangeTargetResolver())->resolve([
            'target_standard_course_amount' => '75.00',
            'target_student_course_amount' => '75.00',
            'management_fee' => '0.00',
        ]);

        Assert::same('0.00', $result['target_entity_course_amount']);
        Assert::same('75.00', $result['target_student_total']);
        Assert::same('0.00', $result['target_entity_total']);
        Assert::same(false, $result['entity_invoice_required']);
    }

    public function testNormalizesCommaDecimalsWithoutUsingFloats(): void
    {
        $result = (new UsocCourseChangeTargetResolver())->resolve([
            'target_standard_course_amount' => '73,00',
            'target_student_course_amount' => '54,75',
            'management_fee' => '1,25',
        ]);

        Assert::same('18.25', $result['target_entity_course_amount']);
        Assert::same('56.00', $result['target_student_total']);
        Assert::same('74.25', $result['target_combined_total']);
    }

    public function testRejectsStudentAmountAboveStandardTarget(): void
    {
        Assert::throws(SifException::class, static function (): void {
            (new UsocCourseChangeTargetResolver())->resolve([
                'target_standard_course_amount' => '80.00',
                'target_student_course_amount' => '81.00',
                'management_fee' => '0.00',
            ]);
        }, 422);
    }

    public function testRejectsNegativeOrMalformedAmounts(): void
    {
        Assert::throws(SifException::class, static function (): void {
            (new UsocCourseChangeTargetResolver())->resolve([
                'target_standard_course_amount' => '100.00',
                'target_student_course_amount' => '-1.00',
                'management_fee' => '0.00',
            ]);
        }, 422);

        Assert::throws(SifException::class, static function (): void {
            (new UsocCourseChangeTargetResolver())->resolve([
                'target_standard_course_amount' => '100.000',
                'target_student_course_amount' => '80.00',
                'management_fee' => '0.00',
            ]);
        }, 422);
    }
}
