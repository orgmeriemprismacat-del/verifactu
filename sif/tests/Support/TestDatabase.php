<?php

namespace Prisma\Sif\Tests\Support;

final class TestDatabase
{
    private const TABLES = [
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
        $config = require dirname(__DIR__, 2) . '/config/sif.php';

        return new \PDO(
            $config['db']['dsn'],
            $config['db']['user'],
            $config['db']['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    }

    public static function fresh(): \PDO
    {
        $config = require dirname(__DIR__, 2) . '/config/sif.php';
        self::assertSafeTestConfig($config);

        $db = self::connect();
        $db->exec(file_get_contents(dirname(__DIR__, 2) . '/database/migrations/2026_06_02_000001_create_sif_core.sql'));
        self::truncateCoreTables($db);
        $db->exec(file_get_contents(dirname(__DIR__, 2) . '/database/seeds/2026_06_02_000001_seed_sif_core.sql'));

        return $db;
    }

    public static function applyCoreSchema(\PDO $db): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/2026_06_02_000001_create_sif_core.sql');
        $seed = file_get_contents(dirname(__DIR__, 2) . '/database/seeds/2026_06_02_000001_seed_sif_core.sql');

        $db->exec($migration);
        $db->exec($seed);
    }

    private static function assertSafeTestConfig(array $config): void
    {
        $env = (string) ($config['env'] ?? 'local');
        $dsn = (string) ($config['db']['dsn'] ?? '');

        if ($env === 'production' || !str_contains($dsn, 'test')) {
            throw new \RuntimeException('TestDatabase::fresh() requires a non-production database DSN containing "test".');
        }
    }

    private static function truncateCoreTables(\PDO $db): void
    {
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach (self::TABLES as $table) {
                $db->exec("TRUNCATE TABLE {$table}");
            }
        } finally {
            $db->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}
