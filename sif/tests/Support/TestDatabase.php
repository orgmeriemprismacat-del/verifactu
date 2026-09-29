<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Support;

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\MigrationRunner;

final class TestDatabase
{
    public static function connect(): \PDO
    {
        $config = require dirname(__DIR__, 2) . '/config/sif.php';
        self::assertSafeTestConfig($config);
        $db = ConnectionFactory::make($config);
        self::assertIsTestDatabase($db);

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

        // Reset only data in the explicitly named local test database.
        // The migration ledger is preserved so recorded migration hashes are
        // still verified on each run. MySQL TRUNCATE is not transactional.
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach (array_keys($runner->expectedSchema()) as $table) {
                if ($db->query("SELECT 1 FROM {$table} LIMIT 1")->fetchColumn() !== false) {
                    $db->exec("TRUNCATE TABLE {$table}");
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
        self::assertIsTestDatabase($db);
        (new MigrationRunner(dirname(__DIR__, 2) . '/database'))->migrate($db);
    }

    private static function assertSafeTestConfig(array $config): void
    {
        if (($config['env'] ?? '') !== 'test') {
            throw new \RuntimeException('Destructive test actions require SIF_ENV=test.');
        }

        $dsn = (string) ($config['db']['dsn'] ?? '');
        if (!str_starts_with($dsn, 'mysql:')) {
            throw new \RuntimeException('Tests require a dedicated MySQL DSN.');
        }

        $options = [];
        foreach (explode(';', substr($dsn, 6)) as $part) {
            if (!str_contains($part, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $part, 2));
            $options[strtolower($key)] = $value;
        }

        $database = $options['dbname'] ?? '';
        $host = strtolower($options['host'] ?? '');
        if (!preg_match('/^sif_test(?:_[a-z0-9_]+)?$/D', $database)
            || !in_array($host, ['127.0.0.1', 'localhost'], true)
        ) {
            throw new \RuntimeException('Tests require an explicitly named sif_test* database on localhost.');
        }
    }

    private static function assertIsTestDatabase(\PDO $db): void
    {
        $actual = (string) $db->query('SELECT DATABASE()')->fetchColumn();
        if (!preg_match('/^sif_test(?:_[a-z0-9_]+)?$/D', $actual)) {
            throw new \RuntimeException('Refusing schema/data changes outside the isolated sif_test* database.');
        }
    }
}
