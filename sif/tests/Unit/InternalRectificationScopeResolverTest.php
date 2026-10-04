<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\InternalRectificationScopeResolver;
use Prisma\Sif\Tests\Support\Assert;

final class InternalRectificationScopeResolverTest
{
    public function testResolvesExplicitRectificationRole(): void
    {
        $resolved = (new InternalRectificationScopeResolver(['FACTURACIO']))->resolve([
            'actor_id' => 'operator-1',
            'roles' => ['SUPORT', 'FACTURACIO'],
            'request_id' => '12345678-1234-4123-8123-123456789abc',
            'source_channel' => 'INTERNAL_API',
        ]);

        Assert::same('operator-1', $resolved['actor_id']);
        Assert::same('FACTURACIO', $resolved['rectification_role']);
        Assert::same(true, $resolved['rectification_scope']['preview']);
        Assert::same(true, $resolved['rectification_scope']['issue']);
    }

    public function testFailsClosedWhenWriteRolesAreNotConfigured(): void
    {
        Assert::throws(\RuntimeException::class, function (): void {
            new InternalRectificationScopeResolver([]);
        });
    }

    public function testRejectsActorWithoutAuthorizedRole(): void
    {
        Assert::throws(SifException::class, function (): void {
            (new InternalRectificationScopeResolver(['FACTURACIO']))->resolve([
                'actor_id' => 'operator-2',
                'roles' => ['SUPORT'],
            ]);
        }, 403);
    }
}
