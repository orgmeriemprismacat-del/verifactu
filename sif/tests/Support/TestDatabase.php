<?php

namespace Prisma\Sif\Tests\Support;

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\MigrationRunner;

final class TestDatabase
{
    private const TABLES = [
        'redsys_callback_queue',
        'redsys_payment_intent',
        'errors_verifactu',
        'factura_documents',
        'fiscal_queue',
        'fact_rels',
        'payment_allocation',
        'payment_transaction',
        'factura_rectificacio',
        'factura_registres',
        'factura_linia',
        'factura',
        'fiscal_sequence',
        'fiscal_chain_state',
        'redsys_notifications',
        'credit_balance',
    ];

    public static function connect(): \PDO
    {
        $dsn = (string) ($config['db']['dsn'] ?? '');
        preg_match_all('/(?:^mysql:|;)dbname=([^;]+)/', $dsn, $matches);
        $name = count($matches[1]) === 1 ? $matches[1][0] : '';
        if (($config['env'] ?? '') !== 'test' || !preg_match('/^sif_test(?:_[a-z0-9_]+)?$/D', $name)) {
            throw new \RuntimeException('Tests require SIF_ENV=test and a database named sif_test or sif_test_* .');
        }
    }

    public static function connect(): \PDO
    {
        $config = require dirname(__DIR__, 2) . '/config/sif.php';
        self::assertSafeTestConfig($config);

        $db = self::connect();
        foreach (glob(dirname(__DIR__, 2) . '/database/migrations/*.sql') ?: [] as $migration) {
            $db->exec(file_get_contents($migration));
        }
        self::truncateCoreTables($db);
        $db->exec(file_get_contents(dirname(__DIR__, 2) . '/database/seeds/2026_06_02_000001_seed_sif_core.sql'));

        return $db;
    }

    public static function fresh(): \PDO
    {
        $db = self::connect();
        $runner = new MigrationRunner(dirname(__DIR__, 2) . '/database');
        $runner->migrate($db);
        $checks = $runner->inspect($db);
        if (in_array(false, $checks, true)) {
            throw new \RuntimeException('Test schema is incomplete; recreate the isolated test database.');
        }
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach (array_keys($runner->expectedSchema()) as $table) {
                // Empty extension tables do not need expensive DDL on every test.
                if ($db->query("SELECT 1 FROM `{$table}` LIMIT 1")->fetchColumn() !== false) {
                    $db->exec("TRUNCATE TABLE `{$table}`");
                }
            }
        } finally {
            $db->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
        $runner->seed($db);
        return $db;
    }

    public static function applyCoreSchema(\PDO $db): void
    {
        $config = require dirname(__DIR__, 2) . '/config/sif.php';
        self::assertSafeTestConfig($config);
        if (!preg_match('/^sif_test(?:_[a-z0-9_]+)?$/D', (string) $db->query('SELECT DATABASE()')->fetchColumn())) {
            throw new \RuntimeException('Refusing schema writes outside a SIF test database.');
        }
        (new MigrationRunner(dirname(__DIR__, 2) . '/database'))->migrate($db);
    }
}


