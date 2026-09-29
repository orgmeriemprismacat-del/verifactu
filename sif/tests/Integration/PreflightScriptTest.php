<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\ScriptRunner;
use Prisma\Sif\Tests\Support\TestDatabase;

final class PreflightScriptTest
{
    public function testExecutableVerifiesAllMigrationsAndFailsOnLedgerTampering(): void
    {
        $db = TestDatabase::fresh();
        $result = ScriptRunner::run('scripts/preflight-sif.php');
        Assert::same(0, $result['exit_code']);
        Assert::same('', $result['stderr']);
        $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
        Assert::same(true, $json['ok']);
        Assert::same(true, $json['checks']['schema_verified']);
        $row = $db->query('SELECT MIGRATION_FILE, SHA256 FROM sif_schema_migration ORDER BY MIGRATION_FILE LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        $update = $db->prepare('UPDATE sif_schema_migration SET SHA256=? WHERE MIGRATION_FILE=?');
        try {
            $update->execute([str_repeat('0', 64), $row['MIGRATION_FILE']]);
            $result = ScriptRunner::run('scripts/preflight-sif.php');
            Assert::same(1, $result['exit_code']);
            $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
            Assert::same(false, $json['ok']);
            Assert::same(false, $json['checks']['schema_verified']);
        } finally {
            $update->execute([$row['SHA256'], $row['MIGRATION_FILE']]);
        }
    }

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
