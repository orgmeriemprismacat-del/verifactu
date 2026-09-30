<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\InternalInvoiceBeforePaymentScopeResolver;
use Prisma\Sif\Tests\Support\Assert;

final class InternalInvoiceBeforePaymentScopeResolverTest
{
    public function testAllowsConfiguredWriteRole(): void
    {
        $resolved = (new InternalInvoiceBeforePaymentScopeResolver([
            'facturacio',
            'administracio',
        ]))->resolve([
            'actor_id' => 'gestio-test',
            'roles' => ['altres', 'FACTURACIO'],
            'request_id' => 'request-test',
        ]);

        Assert::same('gestio-test', $resolved['actor_id']);
        Assert::same(['ALTRES', 'FACTURACIO'], $resolved['roles']);
        Assert::same(true, $resolved['invoice_before_payment_scope']['issue']);
        Assert::same('INTERNAL_ROLE', $resolved['invoice_before_payment_scope_source']);
    }

    public function testRejectsActorWithoutWriteRole(): void
    {
        Assert::throws(SifException::class, function (): void {
            (new InternalInvoiceBeforePaymentScopeResolver(['FACTURACIO']))->resolve([
                'actor_id' => 'consulta-test',
                'roles' => ['CONSULTA'],
            ]);
        }, 403);
    }

    public function testFailsClosedWhenWriteRolesAreNotConfigured(): void
    {
        Assert::throws(\RuntimeException::class, function (): void {
            new InternalInvoiceBeforePaymentScopeResolver([]);
        });
    }
}
