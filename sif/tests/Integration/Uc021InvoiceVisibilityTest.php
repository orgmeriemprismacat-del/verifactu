<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\InvoiceReadRepository;
use Prisma\Sif\Service\InvoiceQueryService;
use Prisma\Sif\Service\ResolvedDocumentAuthorizationPolicy;
use Prisma\Sif\Service\ResolvedInvoiceVisibilityPolicy;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class Uc021InvoiceVisibilityTest
{
    public function testParticipantRelationDoesNotGrantJointInvoiceAccessWithoutResolvedScope(): void
    {
        $db = TestDatabase::fresh();
        $invoice = $this->jointInvoice($db);

        $service = new InvoiceQueryService(
            $db,
            new InvoiceReadRepository(),
            new ResolvedInvoiceVisibilityPolicy()
        );

        Assert::throws(SifException::class, function () use ($service, $invoice): void {
            $service->view([
                'actor_id' => 'participant-11',
                'actor_type' => 'PARTICIPANT',
            ], $invoice['uuid_factura']);
        }, 403);
    }

    public function testExplicitReceiverFullScopeCanViewJointInvoice(): void
    {
        $db = TestDatabase::fresh();
        $invoice = $this->jointInvoice($db);

        $service = new InvoiceQueryService(
            $db,
            new InvoiceReadRepository(),
            new ResolvedInvoiceVisibilityPolicy()
        );

        $view = $service->view([
            'actor_id' => 'billing-party-7',
            'actor_type' => 'BILLING_PARTY',
            'invoice_scope' => [
                'invoices' => [
                    $invoice['uuid_factura'] => 'FULL',
                ],
            ],
        ], $invoice['uuid_factura']);

        Assert::same($invoice['uuid_factura'], $view['invoice']['uuid_factura']);
        Assert::same('B12345678', $view['invoice']['billing']['nif']);
        Assert::same(2, count($view['lines']));
        Assert::same(2, count($view['relations']));
    }

    public function testDocumentDownloadRequiresExplicitFullScope(): void
    {
        $policy = new ResolvedDocumentAuthorizationPolicy();
        $uuid = '11111111-1111-4111-8111-111111111111';
        $invoice = ['UUID_FACTURA' => $uuid];
        $relations = [
            ['SOURCE_TYPE' => 'INSCRIPCIO', 'SOURCE_ID' => 11, 'VISIBLE_ALUMNE' => 0],
            ['SOURCE_TYPE' => 'INSCRIPCIO', 'SOURCE_ID' => 12, 'VISIBLE_ALUMNE' => 0],
        ];
        $document = ['ID' => 1, 'UUID_FACTURA' => $uuid, 'TIPUS' => 'PDF'];

        Assert::same(false, $policy->canDownload(
            ['actor_id' => 'participant-11'],
            $invoice,
            $relations,
            $document
        ));

        Assert::same(false, $policy->canDownload(
            [
                'actor_id' => 'participant-11',
                'invoice_scope' => [
                    'invoices' => [$uuid => 'MINIMAL'],
                ],
            ],
            $invoice,
            $relations,
            $document
        ));

        Assert::same(true, $policy->canDownload(
            [
                'actor_id' => 'billing-party-7',
                'invoice_scope' => [
                    'invoices' => [$uuid => 'FULL'],
                ],
            ],
            $invoice,
            $relations,
            $document
        ));
    }

    private function jointInvoice(\PDO $db): array
    {
        return IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC021|VISIBILITY|JOINT',
                'source_channel' => 'INTRANET',
                'emesa_abans_cobrament' => 1,
                'uc004_invoice_before_payment' => 1,
                'billing' => [
                    'name' => 'Escola Exemple SL',
                    'nif' => 'B12345678',
                    'email' => 'responsable@example.invalid',
                ],
                'totals' => [
                    'import_base' => '200.00',
                    'discount' => '0.00',
                    'taxable_base' => '200.00',
                    'iva_regim' => 'EXEMPT',
                    'iva_pct' => '0.00',
                    'iva_import' => '0.00',
                    'total' => '200.00',
                ],
                'lines' => [
                    [
                        'concept' => 'Participant 11',
                        'detail' => 'Curs conjunt',
                        'quantity' => '1.00',
                        'unit_price' => '80.00',
                        'base' => '80.00',
                        'import_base' => '80.00',
                        'discount_amount' => '0.00',
                        'taxable_base' => '80.00',
                        'iva_regim' => 'EXEMPT',
                        'iva_pct' => '0.00',
                        'iva_import' => '0.00',
                        'total' => '80.00',
                        'source_type' => 'INSCRIPCIO',
                        'source_id' => 11,
                    ],
                    [
                        'concept' => 'Participant 12',
                        'detail' => 'Curs conjunt',
                        'quantity' => '1.00',
                        'unit_price' => '120.00',
                        'base' => '120.00',
                        'import_base' => '120.00',
                        'discount_amount' => '0.00',
                        'taxable_base' => '120.00',
                        'iva_regim' => 'EXEMPT',
                        'iva_pct' => '0.00',
                        'iva_import' => '0.00',
                        'total' => '120.00',
                        'source_type' => 'INSCRIPCIO',
                        'source_id' => 12,
                    ],
                ],
                'relations' => [
                    [
                        'source_type' => 'INSCRIPCIO',
                        'source_id' => 11,
                        'relation_type' => 'ORIGIN',
                        'visible_alumne' => 0,
                    ],
                    [
                        'source_type' => 'INSCRIPCIO',
                        'source_id' => 12,
                        'relation_type' => 'ORIGIN',
                        'visible_alumne' => 0,
                    ],
                ],
            ])
        );
    }
}
