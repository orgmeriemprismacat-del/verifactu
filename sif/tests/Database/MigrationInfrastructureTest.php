<?php

namespace Prisma\Sif\Tests\Database;

use Prisma\Sif\Database\MigrationRunner;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\ScriptRunner;
use Prisma\Sif\Tests\Support\TestDatabase;

final class MigrationInfrastructureTest
{
    private function runner(): MigrationRunner
    {
        return new MigrationRunner(dirname(__DIR__, 2) . '/database');
    }

    public function testAllMigrationsAreAppliedAndRepeatDoesNotResetData(): void
    {
        $db = TestDatabase::fresh();
        $runner = $this->runner();
        Assert::same(62, count($runner->expectedSchema()));
        Assert::same(count($runner->files()), (int) $db->query('SELECT COUNT(*) FROM sif_schema_migration')->fetchColumn());
        Assert::same(false, in_array(false, $runner->inspect($db), true));
        Assert::same(1, (int) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='enrollment_import_item' AND COLUMN_NAME='ROW_NUMBER'")->fetchColumn());
        $db->exec("UPDATE fiscal_chain_state SET LAST_FISCAL_ORDER=42 WHERE ID=1");
        $messages = $runner->migrate($db);
        Assert::same(count($runner->files()), count($messages));
        foreach ($messages as $message) {
            Assert::stringContainsString('Skipped ', $message);
        }
        Assert::same(42, (int) $db->query('SELECT LAST_FISCAL_ORDER FROM fiscal_chain_state WHERE ID=1')->fetchColumn());
        TestDatabase::fresh();
    }

    public function testAlteredMigrationHashBlocksRunnerAndReadiness(): void
    {
        $db = TestDatabase::fresh();
        $runner = $this->runner();
        $file = basename($runner->files()[0]);
        $original = hash_file('sha256', $runner->files()[0]);
        $stmt = $db->prepare('UPDATE sif_schema_migration SET SHA256=? WHERE MIGRATION_FILE=?');
        try {
            $stmt->execute([str_repeat('0', 64), $file]);
            Assert::same(false, $runner->inspect($db)['migration:' . $file]);
            Assert::throws(\RuntimeException::class, static fn () => $runner->migrate($db));
        } finally {
            $stmt->execute([$original, $file]);
        }
    }

    public function testMissingExtensionTableAndAddedColumnBlockReadiness(): void
    {
        $db = TestDatabase::fresh();
        $runner = $this->runner();
        $db->exec('RENAME TABLE academic_reconciliation_item TO test_hidden_academic_item');
        try {
            Assert::same(false, $runner->inspect($db)['academic_reconciliation_item_table']);
        } finally {
            $db->exec('RENAME TABLE test_hidden_academic_item TO academic_reconciliation_item');
        }
        $db->exec('ALTER TABLE fiscal_queue RENAME COLUMN NEXT_RETRY_AT TO TEST_HIDDEN_RETRY');
        try {
            Assert::same(false, $runner->inspect($db)['fiscal_queue_columns']);
        } finally {
            $db->exec('ALTER TABLE fiscal_queue RENAME COLUMN TEST_HIDDEN_RETRY TO NEXT_RETRY_AT');
        }
    }

    public function testFreshClearsExtensionDataAndRestoresSeedAndForeignKeys(): void
    {
        $db = TestDatabase::fresh();
        $db->exec("INSERT INTO redsys_payment_intent (UUID_INTENT, DS_ORDER, SOURCE_TYPE, EXPECTED_AMOUNT, SNAPSHOT_JSON) VALUES (UUID(), 'TEST-CLEANUP', 'TEST', 1.00, '{}')");
        $db->exec('UPDATE fiscal_chain_state SET LAST_FISCAL_ORDER=17 WHERE ID=1');
        $db = TestDatabase::fresh();
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM redsys_payment_intent')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT LAST_FISCAL_ORDER FROM fiscal_chain_state WHERE ID=1')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT @@FOREIGN_KEY_CHECKS')->fetchColumn());
        $error = Assert::throws(\PDOException::class, static fn () => $db->exec("INSERT INTO payment_allocation (UUID_PAYMENT, UUID_FACTURA, IMPORT_ASSIGNAT, TIPUS_ASSIGNACIO) VALUES (UUID(), UUID(), 1, 'INVOICE_PAYMENT')"));
        Assert::same(1452, $error->errorInfo[1]);
    }

    public function testDatabaseGuardRejectsProductionAndMisleadingDsn(): void
    {
        foreach ([
            ['env'=>'production', 'db'=>['dsn'=>'mysql:host=localhost;dbname=sif_test']],
            ['env'=>'local', 'db'=>['dsn'=>'mysql:host=localhost;dbname=sif_test']],
            ['env'=>'test', 'db'=>['dsn'=>'mysql:host=test-server;dbname=production']],
            ['env'=>'test', 'db'=>['dsn'=>'mysql:host=localhost;dbname=sif_test_production;dbname=production']],
        ] as $config) {
            Assert::throws(\RuntimeException::class, static fn () => TestDatabase::assertSafeTestConfig($config));
        }
        TestDatabase::assertSafeTestConfig(['env'=>'test', 'db'=>['dsn'=>'mysql:host=localhost;dbname=sif_test']]);
    }

    public function testRunnerRejectsConcurrentSuiteAndProductionBeforeResettingData(): void
    {
        $db = TestDatabase::fresh();
        $db->exec('UPDATE fiscal_chain_state SET LAST_FISCAL_ORDER=73 WHERE ID=1');
        try {
            // The parent suite holds the advisory lock throughout its execution.
            $result = ScriptRunner::run('tests/run-tests.php');
            Assert::same(1, $result['exit_code']);
            Assert::stringContainsString('Another test suite is using this database.', $result['stderr']);
            $result = ScriptRunner::run('tests/run-tests.php', ['SIF_ENV' => 'production']);
            Assert::same(1, $result['exit_code']);
            Assert::stringContainsString('Tests require SIF_ENV=test', $result['stderr']);
            Assert::same(73, (int) $db->query('SELECT LAST_FISCAL_ORDER FROM fiscal_chain_state WHERE ID=1')->fetchColumn());
        } finally {
            TestDatabase::fresh();
        }
    }
}

