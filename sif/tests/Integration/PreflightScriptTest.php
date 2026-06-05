<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class PreflightScriptTest
{
    public function testPreflightScriptChecksCoreTablesAndReturnsJson(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preflight-sif.php');

        if ($source === false) {
            Assert::fail('Could not read preflight script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('factura_table', $source);
        Assert::stringContainsString('factura_linia_table', $source);
        Assert::stringContainsString('factura_registres_table', $source);
        Assert::stringContainsString('fiscal_queue_table', $source);
        Assert::stringContainsString('payment_transaction_table', $source);
        Assert::stringContainsString('redsys_notifications_table', $source);
        Assert::stringContainsString('factura_documents_table', $source);
        Assert::stringContainsString('errors_verifactu_table', $source);
        Assert::stringContainsString('fiscal_chain_state_seeded', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);
        Assert::stringContainsString('exit(count($failed) === 0 ? 0 : 1)', $source);
    }
}
