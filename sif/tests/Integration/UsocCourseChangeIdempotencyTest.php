<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\UsocCourseChangeIdempotency;
use Prisma\Sif\Tests\Support\Assert;

final class UsocCourseChangeIdempotencyTest
{
    public function testKeysAreStableCompactAndSeparatedByPayerAndEffect(): void
    {
        $policy = new UsocCourseChangeIdempotency();
        $requestId = 'uc013-course-change-892-' . str_repeat('x', 80);

        $studentInvoice = $policy->key(
            $requestId,
            'student',
            'target_invoice'
        );
        $sameStudentInvoice = $policy->key(
            $requestId,
            'STUDENT',
            'TARGET_INVOICE'
        );
        $entityInvoice = $policy->key(
            $requestId,
            'entity',
            'target_invoice'
        );
        $studentRectification = $policy->key(
            $requestId,
            'student',
            'rectify_source'
        );

        Assert::same($studentInvoice, $sameStudentInvoice);
        Assert::notSame($studentInvoice, $entityInvoice);
        Assert::notSame($studentInvoice, $studentRectification);
        Assert::same(true, strlen($studentInvoice) <= 160);
        Assert::stringContainsString('USOC|COURSE_CHANGE|', $studentInvoice);
        Assert::stringContainsString('|ROLE:STUDENT|TARGET_INVOICE', $studentInvoice);
    }

    public function testRejectsUnknownRoleEffectAndMalformedRequestId(): void
    {
        $policy = new UsocCourseChangeIdempotency();

        Assert::throws(SifException::class, static function () use ($policy): void {
            $policy->key('request-1', 'OTHER', 'TARGET_INVOICE');
        }, 422);

        Assert::throws(SifException::class, static function () use ($policy): void {
            $policy->key('request-1', 'STUDENT', 'UNKNOWN');
        }, 422);

        Assert::throws(SifException::class, static function () use ($policy): void {
            $policy->key('request id with spaces', 'STUDENT', 'TARGET_INVOICE');
        }, 422);
    }
}
