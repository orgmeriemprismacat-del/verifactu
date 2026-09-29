<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Service\ResolvedInvoiceVisibilityPolicy;
use Prisma\Sif\Tests\Support\Assert;

final class ResolvedInvoiceVisibilityPolicyTest
{
    public function testMissingServerResolvedScopeFailsClosed(): void
    {
        $policy = new ResolvedInvoiceVisibilityPolicy();
        $invoice = ['UUID_FACTURA' => 'invoice-a'];

        Assert::same(false, $policy->canView([], $invoice, []));
    }

    public function testSpecificUuidScopeAllowsOnlyDeclaredInvoice(): void
    {
        $policy = new ResolvedInvoiceVisibilityPolicy();
        $actor = [
            'invoice_scope' => [
                'invoices' => [
                    'invoice-a' => 'FULL',
                ],
            ],
        ];

        Assert::same(true, $policy->canView($actor, ['UUID_FACTURA' => 'invoice-a'], []));
        Assert::same(false, $policy->canView($actor, ['UUID_FACTURA' => 'invoice-b'], []));
    }

    public function testMinimalProjectionRemovesFiscalDetailsAndDocuments(): void
    {
        $policy = new ResolvedInvoiceVisibilityPolicy();
        $actor = [
            'invoice_scope' => [
                'invoices' => [
                    'invoice-a' => 'MINIMAL',
                ],
            ],
        ];
        $view = [
            'ok' => true,
            'invoice' => [
                'uuid_factura' => 'invoice-a',
                'num_visible' => 'A2026/000001',
                'tipus_factura' => 'F1',
                'data_emissio' => '2026-09-29 10:00:00',
                'estat_factura' => 'ISSUED',
                'estat_cobrament' => 'PENDING',
                'estat_aeat' => 'PENDING',
                'billing' => ['nif' => '12345678Z'],
                'totals' => ['total' => '120.00'],
            ],
            'lines' => [['concept' => 'private']],
            'relations' => [['SOURCE_ID' => 10]],
            'payments' => [['UUID_PAYMENT' => 'p1']],
            'documents' => [['ID' => 1]],
        ];

        $result = $policy->project($actor, $view);

        Assert::same('invoice-a', $result['invoice']['uuid_factura']);
        Assert::same(false, array_key_exists('billing', $result['invoice']));
        Assert::same(false, array_key_exists('totals', $result['invoice']));
        Assert::same([], $result['lines']);
        Assert::same([], $result['relations']);
        Assert::same([], $result['payments']);
        Assert::same([], $result['documents']);
    }

    public function testAllScopeCanUseFullProjectionForTrustedInternalAdapter(): void
    {
        $policy = new ResolvedInvoiceVisibilityPolicy();
        $actor = [
            'invoice_scope' => [
                'all' => true,
                'projection' => 'FULL',
            ],
        ];
        $view = [
            'ok' => true,
            'invoice' => [
                'uuid_factura' => 'invoice-a',
                'billing' => ['nif' => '12345678Z'],
            ],
        ];

        Assert::same(true, $policy->canView($actor, ['UUID_FACTURA' => 'invoice-a'], []));
        Assert::same($view, $policy->project($actor, $view));
    }
}
