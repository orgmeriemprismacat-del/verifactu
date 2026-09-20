<?php

namespace Prisma\Sif\Tests\Support;

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\MigrationRunner;

final class TestDatabase
{
    public static function assertSafeTestConfig(array $config): void
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
        $db = ConnectionFactory::make($config);
        if (!preg_match('/^sif_test(?:_[a-z0-9_]+)?$/D', (string) $db->query('SELECT DATABASE()')->fetchColumn())) {
            throw new \RuntimeException('Connected database is not an isolated SIF test database.');
        }
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


