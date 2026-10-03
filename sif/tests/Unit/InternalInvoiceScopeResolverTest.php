<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\InternalInvoiceScopeResolver;
use Prisma\Sif\Tests\Support\Assert;

final class InternalInvoiceScopeResolverTest
{
    public function testFullRoleResolvesTrustedInternalAllScope(): void
    {
        $resolver = new InternalInvoiceScopeResolver(['facturacio'], ['consulta']);
        $actor = $resolver->resolve([
            'actor_id' => 'operator-1',
            'roles' => ['FACTURACIO'],
        ]);

        Assert::same('operator-1', $actor['actor_id']);
        Assert::same(['FACTURACIO'], $actor['roles']);
        Assert::same(true, $actor['invoice_scope']['all']);
        Assert::same('FULL', $actor['invoice_scope']['projection']);
        Assert::same('INTERNAL_ROLE', $actor['invoice_scope_source']);
    }

    public function testMinimalRoleResolvesMinimalProjection(): void
    {
        $resolver = new InternalInvoiceScopeResolver(['FACTURACIO'], ['SUPORT']);
        $actor = $resolver->resolve([
            'actor_id' => 'support-1',
            'roles' => ['suport'],
        ]);

        Assert::same(['SUPORT'], $actor['roles']);
        Assert::same('MINIMAL', $actor['invoice_scope']['projection']);
    }

    public function testUnknownRoleAndMissingActorFailClosed(): void
    {
        $resolver = new InternalInvoiceScopeResolver(['FACTURACIO'], ['SUPORT']);

        Assert::throws(
            SifException::class,
            static fn () => $resolver->resolve([
                'actor_id' => 'other-1',
                'roles' => ['ALTRE'],
            ]),
            403
        );

        Assert::throws(
            SifException::class,
            static fn () => $resolver->resolve([
                'actor_id' => '',
                'roles' => ['FACTURACIO'],
            ]),
            403
        );
    }
}
