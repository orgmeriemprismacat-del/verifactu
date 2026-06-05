<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\FiscalSequenceRepository;
use Prisma\Sif\Repository\InvoiceRepository;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\InvoiceService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class IssueInvoiceTest
{
    public function testIssueInvoiceCreatesFiscalRecordAndQueue(): void
    {
        $db = TestDatabase::fresh();
        $service = self::serviceFor($db);

        $result = $service->issueInvoice(Fixtures::invoicePayload());

        Assert::same(true, $result['ok']);
        Assert::same(false, $result['idempotency_reused']);
        Assert::same('A2026/000001', $result['num_visible']);
        Assert::matchesRegularExpression('/^[0-9a-f-]{36}$/', $result['uuid_factura']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_linia')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fact_rels')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT LAST_FISCAL_ORDER FROM fiscal_chain_state WHERE ID = 1')->fetchColumn());
    }

    public function testIssueInvoiceReusesExistingInvoiceForSameIdempotencyKey(): void
    {
        $db = TestDatabase::fresh();
        $service = self::serviceFor($db);

        $first = $service->issueInvoice(Fixtures::invoicePayload());
        $second = $service->issueInvoice(Fixtures::invoicePayload());

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_factura'], $second['uuid_factura']);
        Assert::same($first['num_visible'], $second['num_visible']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
    }

    private static function serviceFor(\PDO $db): InvoiceService
    {
        return new InvoiceService(
            new TransactionRunner($db),
            new InvoicePayloadValidator(),
            new FiscalSequenceRepository(),
            new InvoiceRepository(new UuidGenerator(), new HashCalculator())
        );
    }
}
