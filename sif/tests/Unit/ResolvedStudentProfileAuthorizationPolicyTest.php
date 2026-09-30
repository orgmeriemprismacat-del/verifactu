<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Service\ResolvedStudentProfileAuthorizationPolicy;
use Prisma\Sif\Tests\Support\Assert;

final class ResolvedStudentProfileAuthorizationPolicyTest
{
    public function testMissingServerResolvedScopeFailsClosed(): void
    {
        $policy = new ResolvedStudentProfileAuthorizationPolicy();
        $profile = ['ID' => 42];

        Assert::same(false, $policy->canView([], $profile));
        Assert::same(false, $policy->canChange([], $profile, ['correu' => ['to' => 'new@example.test']]));
    }

    public function testReadScopeDoesNotGrantWrite(): void
    {
        $policy = new ResolvedStudentProfileAuthorizationPolicy();
        $actor = [
            'student_scope' => [
                'enrollments' => [
                    '42' => 'READ',
                ],
            ],
        ];
        $profile = ['ID' => 42];

        Assert::same(true, $policy->canView($actor, $profile));
        Assert::same(false, $policy->canChange($actor, $profile, ['correu' => ['to' => 'new@example.test']]));
    }

    public function testWriteScopeGrantsViewAndChangeOnlyForDeclaredEnrollment(): void
    {
        $policy = new ResolvedStudentProfileAuthorizationPolicy();
        $actor = [
            'student_scope' => [
                'enrollments' => [
                    '42' => 'WRITE',
                ],
            ],
        ];

        Assert::same(true, $policy->canView($actor, ['ID' => 42]));
        Assert::same(true, $policy->canChange($actor, ['ID' => 42], ['correu' => ['to' => 'new@example.test']]));
        Assert::same(false, $policy->canView($actor, ['ID' => 43]));
    }

    public function testAllScopeRequiresExplicitWriteFlagForChanges(): void
    {
        $policy = new ResolvedStudentProfileAuthorizationPolicy();

        Assert::same(
            true,
            $policy->canView(['student_scope' => ['all' => true]], ['ID' => 42])
        );
        Assert::same(
            false,
            $policy->canChange(['student_scope' => ['all' => true]], ['ID' => 42], [])
        );
        Assert::same(
            true,
            $policy->canChange(
                ['student_scope' => ['all' => true, 'write' => true]],
                ['ID' => 42],
                ['correu' => ['to' => 'new@example.test']]
            )
        );
    }
}
