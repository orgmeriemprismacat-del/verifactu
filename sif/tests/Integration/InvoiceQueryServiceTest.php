<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Contract\InvoiceVisibilityPolicyInterface;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\DocumentJobRepository;
use Prisma\Sif\Repository\DocumentRepository;
use Prisma\Sif\Repository\InvoiceReadRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\RectificationRepository;
use Prisma\Sif\Service\InvoiceBeforePaymentDocumentQueueService;
use Prisma\Sif\Service\InvoiceQueryService;
use Prisma\Sif\Service\ManualRectificationPayloadBuilder;
use Prisma\Sif\Service\ManualRectificationService;
use Prisma\Sif\Service\ResolvedInvoiceVisibilityPolicy;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class InvoiceQueryServiceTest
{
    public function testViewReturnsSeparatedReadModelWithoutMutatingFiscalOrEconomicState(): void
    {
        $db = TestDatabase::fresh();
        $issued = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload());
        (new DocumentRepository())->registerDocument(
            $db,
            $issued['uuid_factura'],
            'PDF',
            '/private/invoices/example.pdf',
            '%PDF-1.4 test'
        );

        $before = $this->stateCounts($db);
        $service = new InvoiceQueryService($db, new InvoiceReadRepository(), $this->allowAllPolicy());

        $result = $service->view(
            ['actor_id' => 'operator-test'],
            $issued['uuid_factura']
        );

        $after = $this->stateCounts($db);

        Assert::same(true, $result['ok']);
        Assert::same($issued['uuid_factura'], $result['invoice']['uuid_factura']);
        Assert::same($issued['num_visible'], $result['invoice']['num_visible']);
        Assert::same('ISSUED', $result['invoice']['estat_factura']);
        Assert::same('PENDING', $result['invoice']['estat_aeat']);
        Assert::same(1, count($result['lines']));
        Assert::same(1, count($result['relations']));
        Assert::same(1, count($result['documents']));
        Assert::same(false, array_key_exists('PATH_FITXER', $result['documents'][0]));
        Assert::same($before, $after);
    }

    public function testViewExposesPendingDocumentJobOnlyInFullProjection(): void
    {
        $db = TestDatabase::fresh();
        $issued = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'QUERY|DOC-JOB|PENDING',
        ]));

        (new InvoiceBeforePaymentDocumentQueueService(
            new TransactionRunner($db),
            new DocumentJobRepository(),
            'uc004-fiscal-pdf-v1'
        ))->ensurePdf(
            $issued['uuid_factura'],
            'INTRANET|FACTURA_ABANS_COBRAR|REF:QUERY-DOC'
        );

        $full = new InvoiceQueryService(
            $db,
            new InvoiceReadRepository(),
            $this->allowAllPolicy()
        );
        $fullView = $full->view(['actor_id' => 'operator-test'], $issued['uuid_factura']);

        Assert::same(1, count($fullView['document_jobs']));
        Assert::same('PDF', $fullView['document_jobs'][0]['DOCUMENT_TYPE']);
        Assert::same('PENDING', $fullView['document_jobs'][0]['STATUS']);
        Assert::same('uc004-fiscal-pdf-v1', $fullView['document_jobs'][0]['GENERATOR_VERSION']);
        Assert::same(false, array_key_exists('IDEMPOTENCY_KEY', $fullView['document_jobs'][0]));
        Assert::same(false, array_key_exists('STORAGE_KEY', $fullView['document_jobs'][0]));

        $minimal = new InvoiceQueryService(
            $db,
            new InvoiceReadRepository(),
            new ResolvedInvoiceVisibilityPolicy()
        );
        $minimalView = $minimal->view([
            'actor_id' => 'student-test',
            'invoice_scope' => [
                'invoices' => [
                    $issued['uuid_factura'] => 'MINIMAL',
                ],
            ],
        ], $issued['uuid_factura']);

        Assert::same([], $minimalView['document_jobs']);
    }

    public function testViewFailsClosedWhenVisibilityPolicyDeniesInvoice(): void
    {
        $db = TestDatabase::fresh();
        $issued = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload());
        $service = new InvoiceQueryService($db, new InvoiceReadRepository(), $this->denyAllPolicy());

        Assert::throws(
            SifException::class,
            static fn () => $service->view(['actor_id' => 'denied'], $issued['uuid_factura']),
            403
        );
    }

    public function testViewReturnsNotFoundForUnknownInvoice(): void
    {
        $db = TestDatabase::fresh();
        $service = new InvoiceQueryService($db, new InvoiceReadRepository(), $this->allowAllPolicy());

        Assert::throws(
            SifException::class,
            static fn () => $service->view(
                ['actor_id' => 'operator-test'],
                '11111111-1111-4111-8111-111111111111'
            ),
            404
        );
    }

    public function testSearchUsesExactCriteriaAndFiltersUnauthorizedRows(): void
    {
        $db = TestDatabase::fresh();
        $issued = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload());
        $allowed = new InvoiceQueryService($db, new InvoiceReadRepository(), $this->allowAllPolicy());
        $denied = new InvoiceQueryService($db, new InvoiceReadRepository(), $this->denyAllPolicy());

        $byNumber = $allowed->search(
            ['actor_id' => 'operator-test'],
            ['num_visible' => $issued['num_visible']]
        );
        $wildcardLiteral = $allowed->search(
            ['actor_id' => 'operator-test'],
            ['billing_nif' => '%']
        );
        $deniedSearch = $denied->search(
            ['actor_id' => 'denied'],
            ['num_visible' => $issued['num_visible']]
        );

        Assert::same(1, $byNumber['count']);
        Assert::same($issued['uuid_factura'], $byNumber['results'][0]['uuid_factura']);
        Assert::same(0, $wildcardLiteral['count']);
        Assert::same(0, $deniedSearch['count']);
    }

    public function testViewKeepsPaymentsAndRectificationAsSeparateRelations(): void
    {
        $db = TestDatabase::fresh();
        $payload = Fixtures::invoicePayload([
            'idempotency_key' => 'QUERY|ORIGINAL|WITH_PAYMENT',
            'payment' => [
                'idempotency_key' => 'PAYMENT|QUERY|ORIGINAL',
                'movement_type' => 'CHARGE',
                'method' => 'REDSYS',
                'source_channel' => 'REDSYS',
                'amount' => '120.00',
                'movement_date' => '2026-09-29 10:00:00',
                'provider_ref' => 'QUERY-PAYMENT-1',
            ],
        ]);
        $original = IssueInvoiceTest::serviceFor($db)->issueInvoice($payload);

        $rectificationService = new ManualRectificationService(
            new ManualPaymentInvoiceRepository(),
            new RectificationRepository(),
            new ManualRectificationPayloadBuilder(),
            IssueInvoiceTest::serviceFor($db)
        );
        $rectification = $rectificationService->issueByUuid($db, $original['uuid_factura'], [
            'amount' => '-40.00',
            'reason' => 'DEVOLUCIO_PARCIAL',
            'mode' => 'DIFERENCIES',
            'concept' => 'Rectificacio parcial',
        ]);

        $query = new InvoiceQueryService($db, new InvoiceReadRepository(), $this->allowAllPolicy());
        $originalView = $query->view(['actor_id' => 'operator-test'], $original['uuid_factura']);
        $rectificationView = $query->view(['actor_id' => 'operator-test'], $rectification['uuid_factura']);

        Assert::same('RECTIFIED', $originalView['invoice']['estat_factura']);
        Assert::same('PAID', $originalView['invoice']['estat_cobrament']);
        Assert::same(1, count($originalView['payments']));
        Assert::same(1, count($originalView['rectifications']));
        Assert::same($rectification['uuid_factura'], $originalView['rectifications'][0]['UUID_FACTURA_RECTIFICATIVA']);

        Assert::same('R', $rectificationView['invoice']['tipus_serie']);
        Assert::same(0, count($rectificationView['payments']));
        Assert::same(1, count($rectificationView['rectifications']));
        Assert::same($original['uuid_factura'], $rectificationView['rectifications'][0]['UUID_FACTURA_RECTIFICADA']);
    }

    public function testResolvedScopePolicyReturnsMinimalProjectionForScopedInvoice(): void
    {
        $db = TestDatabase::fresh();
        $issued = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload());
        $service = new InvoiceQueryService(
            $db,
            new InvoiceReadRepository(),
            new ResolvedInvoiceVisibilityPolicy()
        );
        $actor = [
            'actor_id' => 'student-test',
            'invoice_scope' => [
                'invoices' => [
                    $issued['uuid_factura'] => 'MINIMAL',
                ],
            ],
        ];

        $result = $service->view($actor, $issued['uuid_factura']);

        Assert::same($issued['uuid_factura'], $result['invoice']['uuid_factura']);
        Assert::same(false, array_key_exists('billing', $result['invoice']));
        Assert::same(false, array_key_exists('totals', $result['invoice']));
        Assert::same([], $result['documents']);
        Assert::same([], $result['document_jobs']);
        Assert::same([], $result['payments']);
    }

    public function testSearchRejectsEmptyCriteriaAndInvalidLegacyRelation(): void
    {
        $db = TestDatabase::fresh();
        $service = new InvoiceQueryService($db, new InvoiceReadRepository(), $this->allowAllPolicy());

        Assert::throws(
            SifException::class,
            static fn () => $service->search(['actor_id' => 'operator-test'], []),
            422
        );

        Assert::throws(
            SifException::class,
            static fn () => $service->search(
                ['actor_id' => 'operator-test'],
                ['factura_relacionada' => '500 OR 1=1']
            ),
            422
        );
    }

    private function allowAllPolicy(): InvoiceVisibilityPolicyInterface
    {
        return new class implements InvoiceVisibilityPolicyInterface {
            public function canView(array $actor, array $invoice, array $relations): bool
            {
                return true;
            }

            public function project(array $actor, array $view): array
            {
                return $view;
            }
        };
    }

    private function denyAllPolicy(): InvoiceVisibilityPolicyInterface
    {
        return new class implements InvoiceVisibilityPolicyInterface {
            public function canView(array $actor, array $invoice, array $relations): bool
            {
                return false;
            }

            public function project(array $actor, array $view): array
            {
                return [];
            }
        };
    }

    private function stateCounts(\PDO $db): array
    {
        $tables = [
            'factura',
            'factura_linia',
            'factura_registres',
            'fiscal_queue',
            'payment_transaction',
            'payment_allocation',
            'factura_documents',
            'factura_rectificacio',
        ];
        $counts = [];

        foreach ($tables as $table) {
            $counts[$table] = (int) $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        }

        return $counts;
    }
}
