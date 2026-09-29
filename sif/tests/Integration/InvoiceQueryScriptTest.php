<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\ScriptRunner;
use Prisma\Sif\Tests\Support\TestDatabase;

final class InvoiceQueryScriptTest
{
    public function testQueryInvoiceCliReturnsReadModelWithoutMutatingState(): void
    {
        $db = TestDatabase::fresh();
        $issued = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload());
        $before = $this->counts($db);

        $run = ScriptRunner::run(
            'scripts/query-invoice.php',
            [],
            ['--uuid=' . $issued['uuid_factura']]
        );

        $after = $this->counts($db);
        $payload = json_decode((string) $run['stdout'], true);

        Assert::same(0, $run['exit_code']);
        Assert::same('', trim((string) $run['stderr']));
        Assert::same(true, is_array($payload));
        Assert::same(true, $payload['ok']);
        Assert::same(true, $payload['read_only']);
        Assert::same($issued['uuid_factura'], $payload['invoice']['uuid_factura']);
        Assert::same($before, $after);
    }

    public function testQueryInvoiceCliRefusesProductionMode(): void
    {
        $run = ScriptRunner::run(
            'scripts/query-invoice.php',
            ['SIF_ENV' => 'production'],
            ['--uuid=00000000-0000-0000-0000-000000000000']
        );

        Assert::same(1, $run['exit_code']);
        Assert::stringContainsString('Refusing privileged invoice queries', (string) $run['stderr']);
    }

    private function counts(\PDO $db): array
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
