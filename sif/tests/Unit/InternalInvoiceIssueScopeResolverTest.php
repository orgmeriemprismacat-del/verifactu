<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\InternalInvoiceIssueScopeResolver;
use Prisma\Sif\Tests\Support\Assert;

final class InternalInvoiceIssueScopeResolverTest
{
    public function testAllowsConfiguredWriteRole(): void
    {
        $resolved = (new InternalInvoiceIssueScopeResolver(['facturacio', 'administracio']))->resolve([
            'actor_id' => 'gestio-test',
            'roles' => ['altres', 'FACTURACIO'],
            'request_id' => 'request-test',
        ]);

        Assert::same('gestio-test', $resolved['actor_id']);
        Assert::same(['ALTRES', 'FACTURACIO'], $resolved['roles']);
        Assert::same(true, $resolved['invoice_issue_scope']['issue']);
        Assert::same('FACTURACIO', $resolved['invoice_issue_role']);
        Assert::same('INTERNAL_ROLE', $resolved['invoice_issue_scope_source']);
    }

    public function testRejectsActorWithoutWriteRole(): void
    {
        Assert::throws(SifException::class, function (): void {
            (new InternalInvoiceIssueScopeResolver(['FACTURACIO']))->resolve([
                'actor_id' => 'consulta-test',
                'roles' => ['CONSULTA'],
            ]);
        }, 403);
    }

    public function testFailsClosedWhenWriteRolesAreNotConfigured(): void
    {
        Assert::throws(\RuntimeException::class, function (): void {
            new InternalInvoiceIssueScopeResolver([]);
        });
    }
}
