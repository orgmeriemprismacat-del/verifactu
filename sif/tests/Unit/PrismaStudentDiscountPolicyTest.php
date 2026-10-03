<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\PrismaStudentDiscountPolicy;
use Prisma\Sif\Tests\Support\Assert;

final class PrismaStudentDiscountPolicyTest
{
    public function testPaidHistoryIsEligibleAndPreservesEvidence(): void
    {
        $result = (new PrismaStudentDiscountPolicy())->evaluate([[
            'ID' => 41,
            'A_PAGAR' => '120.00',
            'PAGAMENT' => '40.00',
            'GENERAT' => 0,
            'IDPAG' => 700,
            'FACTURA_RELACIONADA' => null,
            'INSC_CURS' => '1',
        ]]);

        Assert::same(true, $result['eligible']);
        Assert::same('POSITIVE_PAYMENT', $result['reason']);
        Assert::same(41, $result['evidence']['source_id']);
        Assert::same(PrismaStudentDiscountPolicy::RULE_VERSION, $result['rule_version']);
    }

    public function testGiftCourseIsEligible(): void
    {
        $result = (new PrismaStudentDiscountPolicy())->evaluate([[
            'ID' => 42,
            'A_PAGAR' => '0.00',
            'PAGAMENT' => '0.00',
            'OBSERVACIONS' => 'CURS REGAL',
            'GENERAT' => 0,
            'INSC_CURS' => '1',
        ]]);

        Assert::same(true, $result['eligible']);
        Assert::same('GIFT_COURSE', $result['reason']);
    }

    public function testGeneratedHistoryIsEligibleUnderLegacyVersion(): void
    {
        $result = (new PrismaStudentDiscountPolicy())->evaluate([[
            'ID' => 43,
            'A_PAGAR' => '120.00',
            'PAGAMENT' => '0.00',
            'GENERAT' => 1,
            'INSC_CURS' => '1',
        ]]);

        Assert::same(true, $result['eligible']);
        Assert::same('GENERATED', $result['reason']);
    }

    public function testInvoiceBeforePaymentDoesNotGrantEligibilityUnderExecutableLegacyRule(): void
    {
        $result = (new PrismaStudentDiscountPolicy())->evaluate([[
            'ID' => 44,
            'A_PAGAR' => '120.00',
            'PAGAMENT' => '0.00',
            'GENERAT' => 0,
            'IDPAG' => 0,
            'FACTURA_RELACIONADA' => 901,
            'INSC_CURS' => '1',
        ]]);

        Assert::same(false, $result['eligible']);
        Assert::same('NO_ELIGIBLE_HISTORY', $result['reason']);
    }

    public function testExcludedStatusesDoNotGrantEligibility(): void
    {
        $result = (new PrismaStudentDiscountPolicy())->evaluate([
            [
                'ID' => 45,
                'A_PAGAR' => '120.00',
                'PAGAMENT' => '120.00',
                'GENERAT' => 0,
                'INSC_CURS' => 'D',
            ],
            [
                'ID' => 46,
                'A_PAGAR' => '120.00',
                'PAGAMENT' => '120.00',
                'GENERAT' => 0,
                'INSC_CURS' => 'M',
            ],
        ]);

        Assert::same(false, $result['eligible']);
        Assert::same('NO_ELIGIBLE_HISTORY', $result['reason']);
    }
}
