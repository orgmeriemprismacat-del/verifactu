<?php

namespace Prisma\Sif\Database;

final class ConnectionFactory
{
    public static function make(array $config): \PDO
    {
        return self::makeFromConfig($config['db'] ?? [], 'SIF DB');
    }

    public static function makeLegacy(array $config): \PDO
    {
        return self::makeFromConfig($config['legacy_db'] ?? [], 'Legacy DB');
    }

    private static function makeFromConfig(array $dbConfig, string $label): \PDO
    {
        $dsn = (string) ($dbConfig['dsn'] ?? '');
        if ($dsn === '') {
            throw new \RuntimeException("{$label} DSN not configured");
        }

        $db = new \PDO(
            $dsn,
            (string) ($dbConfig['user'] ?? ''),
            (string) ($dbConfig['password'] ?? ''),
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        $db->exec('SET NAMES utf8mb4');

        return $db;
    }
}
