<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Tests\Support\Assert;
use RuntimeException;

require_once dirname(__DIR__, 3) . '/codi-drive/web-actual/inc/JasomNovicePaymentGate.php';

final class JasomNovicePaymentGateTest
{
    public function testNovicePendingCannotStartDirectCardPayment(): void
    {
        $row = $this->enrollment();
        $row['novice_decision'] = 0;

        Assert::throws(RuntimeException::class, static function () use ($row): void {
            \JasomNovicePaymentGate::authorizeEnrollment($row, self::post());
        });
    }

    public function testUnknownNoviceDecisionFailsClosedInsteadOfActingAsNoRequest(): void
    {
        $row = $this->enrollment();
        $row['novice_decision'] = null;

        Assert::throws(RuntimeException::class, static function () use ($row): void {
            \JasomNovicePaymentGate::authorizeEnrollment($row, self::post());
        });
    }

    public function testApprovalAllowsOnlyAmountStillOwed(): void
    {
        $row = $this->enrollment();
        $row['novice_decision'] = 1;

        $allowed = \JasomNovicePaymentGate::authorizeEnrollment($row, self::post());
        Assert::same('90.00', $allowed['total_amount']);
        Assert::same('50.00', $allowed['already_paid_amount']);
        Assert::same('40.00', $allowed['payment_amount']);
        Assert::same(1, $allowed['novice_decision']);
    }

    public function testDenialDoesNotCancelJasomEnrollment(): void
    {
        $row = $this->enrollment();
        $row['novice_decision'] = 2;

        Assert::same('40.00', \JasomNovicePaymentGate::authorizeEnrollment($row, self::post())['payment_amount']);
    }

    public function testNoNoviceRequestUsesOrdinaryPaymentPath(): void
    {
        $row = $this->enrollment();
        $row['novice_row_present'] = false;
        $row['novice_decision'] = null;

        Assert::same('40.00', \JasomNovicePaymentGate::authorizeEnrollment($row, self::post())['payment_amount']);
    }

    public function testTamperedCourseCodeCannotSelectAnotherEnrollment(): void
    {
        $row = $this->enrollment();
        $row['novice_decision'] = 1;
        $post = self::post();
        $post['codiCurs'] = 'ALTRE';

        Assert::throws(RuntimeException::class, static function () use ($row, $post): void {
            \JasomNovicePaymentGate::authorizeEnrollment($row, $post);
        });
    }

    public function testCannotPayMoreThanRemainingOrRepeatFullyPaidCourse(): void
    {
        $row = $this->enrollment();
        $row['novice_decision'] = 1;
        $post = self::post();
        $post['importPagare'] = '41.00';

        Assert::throws(RuntimeException::class, static function () use ($row, $post): void {
            \JasomNovicePaymentGate::authorizeEnrollment($row, $post);
        });

        $row['already_paid'] = '90.00';
        Assert::throws(RuntimeException::class, static function () use ($row): void {
            \JasomNovicePaymentGate::authorizeEnrollment($row, self::post());
        });
    }

    public function testNegativeOrNonNumericAmountsFail(): void
    {
        $row = $this->enrollment();
        $row['novice_decision'] = 1;

        foreach (['-1.00', 'not-money', '0.00', '40.001'] as $invalidAmount) {
            $post = self::post();
            $post['importPagare'] = $invalidAmount;
            Assert::throws(RuntimeException::class, static function () use ($row, $post): void {
                \JasomNovicePaymentGate::authorizeEnrollment($row, $post);
            });
        }
    }

    private function enrollment(): array
    {
        return [
            'enrollment_id' => 10,
            'course_code' => 'JASOM',
            'course_price' => '90.00',
            'already_paid' => '50.00',
            'novice_row_present' => true,
            'novice_decision' => 0,
        ];
    }

    private static function post(): array
    {
        return [
            'idPag' => '777',
            'codiCurs' => 'JASOM',
            'importPagare' => '40.00',
        ];
    }
}
