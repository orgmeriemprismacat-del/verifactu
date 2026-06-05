<?php

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to run migrations with SIF_ENV=production.\n");
    exit(1);
}

$db = new PDO($config['db']['dsn'], $config['db']['user'], $config['db']['password']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('SET NAMES utf8mb4');

foreach (glob(dirname(__DIR__) . '/database/migrations/*.sql') as $file) {
    $db->exec(file_get_contents($file));
    echo "Migrated {$file}\n";
}

foreach (glob(dirname(__DIR__) . '/database/seeds/*.sql') as $file) {
    $db->exec(file_get_contents($file));
    echo "Seeded {$file}\n";
}
