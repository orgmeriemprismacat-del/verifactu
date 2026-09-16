<?php

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to run migrations with SIF_ENV=production.\n");
    exit(1);
}

$db = new PDO($config['db']['dsn'], $config['db']['user'], $config['db']['password']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('SET NAMES utf8mb4');

$db->exec(
    'CREATE TABLE IF NOT EXISTS sif_schema_migration (
        MIGRATION_FILE VARCHAR(255) NOT NULL PRIMARY KEY,
        SHA256 CHAR(64) NOT NULL,
        APPLIED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$readMigration = $db->prepare(
    'SELECT SHA256 FROM sif_schema_migration WHERE MIGRATION_FILE = :migration_file'
);
$recordMigration = $db->prepare(
    'INSERT INTO sif_schema_migration (MIGRATION_FILE, SHA256)
     VALUES (:migration_file, :sha256)'
);

$migrationFiles = glob(dirname(__DIR__) . '/database/migrations/*.sql');
sort($migrationFiles, SORT_STRING);

foreach ($migrationFiles as $file) {
    $migrationFile = basename($file);
    $sha256 = hash_file('sha256', $file);
    $readMigration->execute(['migration_file' => $migrationFile]);
    $appliedHash = $readMigration->fetchColumn();

    if ($appliedHash !== false) {
        if (!hash_equals((string) $appliedHash, $sha256)) {
            throw new RuntimeException(
                "Applied migration {$migrationFile} has changed; create a new additive migration instead."
            );
        }

        echo "Skipped {$migrationFile} (already applied)\n";
        continue;
    }

    $db->exec((string) file_get_contents($file));
    $recordMigration->execute([
        'migration_file' => $migrationFile,
        'sha256' => $sha256,
    ]);
    echo "Migrated {$migrationFile}\n";
}

foreach (glob(dirname(__DIR__) . '/database/seeds/*.sql') as $file) {
    $db->exec(file_get_contents($file));
    echo "Seeded {$file}\n";
}
