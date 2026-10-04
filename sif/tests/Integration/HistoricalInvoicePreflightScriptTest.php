<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class HistoricalInvoicePreflightScriptTest
{
    public function testPreflightIsReadOnlyAndChecksCutoverDependencies(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents(
            $root . '/scripts/preflight-historical-invoices.php'
        );
        if (!is_string($source)) {
            Assert::fail('Could not read historical invoice preflight script');
        }

        Assert::stringContainsString('PHP_SAPI', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('HistoricalInvoiceInventoryService', $source);
        Assert::stringContainsString('SIF_BLOCK_LEGACY_INVOICE_MUTATIONS', $source);
        Assert::stringContainsString('SIF_UC007_QUERY_ENABLED', $source);
        Assert::stringContainsString('ready_for_controlled_migration', $source);
        Assert::stringContainsString('ready_to_close_reconciliation', $source);
        Assert::stringContainsString("'read_only' => true", $source);
        Assert::stringContainsString("'production_authorized' => false", $source);

        foreach ([
            'importHistoricalInvoice(',
            'issueInvoice(',
            'registerPayment(',
            'INSERT INTO factura',
            'UPDATE factures',
            'DELETE FROM factures',
        ] as $forbidden) {
            Assert::same(false, str_contains($source, $forbidden));
        }
    }
}
