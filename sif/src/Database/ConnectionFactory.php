<?php

namespace Prisma\Sif\Database;

final class ConnectionFactory
{
    public static function make(array $config): \PDO
    {
        $db = new \PDO(
            $config['db']['dsn'],
            $config['db']['user'],
            $config['db']['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        $db->exec('SET NAMES utf8mb4');

        return $db;
    }
}
