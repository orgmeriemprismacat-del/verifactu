<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

require_once dirname(__DIR__, 3)
    . '/codi-drive/intranet-actual/LegacyUsocCourseChangePricingSourceInterface.php';
require_once dirname(__DIR__, 3)
    . '/codi-drive/intranet-actual/LegacyUsocCourseChangePricingResolver.php';

final class LegacyUsocCourseChangePricingResolverTest
{
    public function testResolvesTargetPricesAndUsesSourceHoursForManagementFee(): void
    {
        $source = new LegacyUsocCourseChangePricingFakeSource();
        $resolver = new \LegacyUsocCourseChangePricingResolver($source);

        $result = $resolver->resolve(
            880,
            '2027',
            '01',
            'CURS-B',
            4
        );

        Assert::same(980, $result['idpag']);
        Assert::same('100.00', $result['target']['target_standard_course_amount']);
        Assert::same('75.00', $result['target']['target_student_course_amount']);
        Assert::same('8.00', $result['target']['management_fee']);
        Assert::same('30', $source->managementFeeHours);
        Assert::same(true, $result['invariants']['management_fee_uses_source_hours']);
    }

    public function testNonFeeChangeDoesNotReadManagementFee(): void
    {
        $source = new LegacyUsocCourseChangePricingFakeSource();
        $resolver = new \LegacyUsocCourseChangePricingResolver($source);

        $result = $resolver->resolve(
            880,
            '2027',
            '01',
            'CURS-B',
            2
        );

        Assert::same('0.00', $result['target']['management_fee']);
        Assert::same(null, $source->managementFeeHours);
        Assert::same(1, $source->editionCalls);
    }

    public function testRejectsUnvalidatedUsocSource(): void
    {
        $source = new LegacyUsocCourseChangePricingFakeSource();
        $source->enrollment['valid_desc'] = 0;

        Assert::throws(\RuntimeException::class, static function () use ($source): void {
            (new \LegacyUsocCourseChangePricingResolver($source))->resolve(
                880,
                '2027',
                '01',
                'CURS-B',
                1
            );
        }, 409);
    }

    public function testRejectsZeroEntityOrZeroStudentTarget(): void
    {
        $source = new LegacyUsocCourseChangePricingFakeSource();
        $source->studentPrice = '100.00';

        Assert::throws(\RuntimeException::class, static function () use ($source): void {
            (new \LegacyUsocCourseChangePricingResolver($source))->resolve(
                880,
                '2027',
                '01',
                'CURS-B',
                1
            );
        }, 409);

        $source->studentPrice = '0.00';

        Assert::throws(\RuntimeException::class, static function () use ($source): void {
            (new \LegacyUsocCourseChangePricingResolver($source))->resolve(
                880,
                '2027',
                '01',
                'CURS-B',
                1
            );
        }, 409);
    }
}

final class LegacyUsocCourseChangePricingFakeSource
    implements \LegacyUsocCourseChangePricingSourceInterface
{
    public array $enrollment = [
        'data_insc' => '2026-09-01 10:00:00',
        'tipus_desc' => 4,
        'valid_desc' => 1,
        'idpag' => 980,
        'year' => '2026',
        'month' => '09',
        'course' => 'CURS-A',
    ];

    public string $studentPrice = '75.00';
    public ?string $managementFeeHours = null;
    public int $editionCalls = 0;

    public function enrollment(int $idInsc): array
    {
        return $this->enrollment;
    }

    public function edition(string $year, string $month, string $course): array
    {
        $this->editionCalls++;

        if ($course === 'CURS-A') {
            return [
                'kind' => 'COURSE',
                'title' => 'Curs A',
                'hours' => '30',
                'price_id' => 1,
            ];
        }

        return [
            'kind' => 'COURSE',
            'title' => 'Curs B',
            'hours' => '20',
            'price_id' => 2,
        ];
    }

    public function activeStandardPrice(int $priceId): string
    {
        return '100.00';
    }

    public function activeUsocPrice(
        int $priceId,
        string $course,
        string $month,
        string $effectiveDate
    ): string {
        return $this->studentPrice;
    }

    public function managementFee(string $hours): string
    {
        $this->managementFeeHours = $hours;
        return '8.00';
    }
}
