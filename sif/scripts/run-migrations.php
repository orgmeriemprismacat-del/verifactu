<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\MigrationRunner;

$config = require dirname(__DIR__) . '/config/sif.php';
if (PHP_SAPI !== 'cli' || !in_array($config['env'], ['local', 'test', 'preproduction'], true)) {
    fwrite(STDERR, "Migrations require CLI and SIF_ENV=local, test or preproduction.\n");
    exit(1);
}
try {
    $runner = new MigrationRunner(dirname(__DIR__) . '/database');
    foreach ($runner->migrate(ConnectionFactory::make($config)) as $message) {
        echo $message, PHP_EOL;
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
