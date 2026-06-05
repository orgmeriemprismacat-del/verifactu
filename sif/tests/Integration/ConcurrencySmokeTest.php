<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ConcurrencySmokeTest
{
    public function testMultipleInvoicesHaveLinearFiscalOrder(): void
    {
        $db = TestDatabase::fresh();
        $service = IssueInvoiceTest::serviceFor($db);

        for ($i = 1; $i <= 10; $i++) {
            $service->issueInvoice(Fixtures::invoicePayload([
                'idempotency_key' => "REDSYS|CURS|IDPAG:{$i}|ORDER:ORDER{$i}",
                'relations' => [[
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => $i,
                    'factura_relacionada' => 500 + $i,
                    'idpag' => $i,
                    'ds_order' => "ORDER{$i}",
                    'visible_alumne' => 1,
                ]],
            ]));
        }

        Assert::same(10, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(10, (int) $db->query('SELECT LAST_NUM FROM fiscal_sequence WHERE TIPUS_SERIE = "A" AND ANY_FACT = 2026')->fetchColumn());
        Assert::same(10, (int) $db->query('SELECT LAST_FISCAL_ORDER FROM fiscal_chain_state WHERE ID = 1')->fetchColumn());
        Assert::same(10, (int) $db->query('SELECT COUNT(DISTINCT FISCAL_ORDER) FROM factura_registres')->fetchColumn());
        Assert::same(10, (int) $db->query('SELECT COUNT(DISTINCT HASH_FACT) FROM factura_registres')->fetchColumn());
    }
}
