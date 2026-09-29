<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Contract\InvoiceVisibilityPolicyInterface;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\DocumentRepository;
use Prisma\Sif\Repository\InvoiceReadRepository;
use Prisma\Sif\Service\InvoiceQueryService;
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
                '00000000-0000-0000-0000-000000000000'
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
