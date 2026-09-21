<?php

namespace Prisma\Sif\Database;

final class MigrationRunner
{
    public function __construct(private string $databaseDir) {}

    public function files(): array
    {
        $files = glob($this->databaseDir . '/migrations/*.sql') ?: [];
        sort($files, SORT_STRING);
        if ($files === []) {
            throw new \RuntimeException('No migration files found.');
        }
        return $files;
    }

    public function migrate(\PDO $db): array
    {
        $db->exec('CREATE TABLE IF NOT EXISTS sif_schema_migration (
            MIGRATION_FILE VARCHAR(255) NOT NULL PRIMARY KEY,
            SHA256 CHAR(64) NOT NULL,
            APPLIED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $applied = $db->query('SELECT MIGRATION_FILE, SHA256 FROM sif_schema_migration')->fetchAll(\PDO::FETCH_KEY_PAIR);
        $files = $this->files();
        // Validate the entire ledger before running any pending DDL.
        foreach ($applied as $name => $hash) {
            $path = $this->databaseDir . '/migrations/' . $name;
            if (!in_array($path, $files, true) || !hash_equals($hash, hash_file('sha256', $path))) {
                throw new \RuntimeException("Applied migration {$name} is missing or has changed; create a new additive migration instead.");
            }
        }
        $record = $db->prepare('INSERT INTO sif_schema_migration (MIGRATION_FILE, SHA256) VALUES (?, ?)');
        $messages = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (isset($applied[$name])) {
                $messages[] = "Skipped {$name} (already applied)";
                continue;
            }
            // MySQL DDL commits implicitly: never mark a partially failed file as applied.
            $db->exec((string) file_get_contents($file));
            $record->execute([$name, hash_file('sha256', $file)]);
            $messages[] = "Migrated {$name}";
        }
        $this->seed($db);
        return $messages;
    }

    public function seed(\PDO $db): void
    {
        $files = glob($this->databaseDir . '/seeds/*.sql') ?: [];
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            $db->exec((string) file_get_contents($file));
        }
    }

    /** Schema names come exclusively from checked-in SQL, never from user input. */
    public function expectedSchema(): array
    {
        $schema = [];
        foreach ($this->files() as $file) {
            $sql = (string) file_get_contents($file);
            preg_match_all('/CREATE TABLE IF NOT EXISTS\s+(\w+)\s*\((.*?)\)\s*ENGINE=/si', $sql, $tables, PREG_SET_ORDER);
            foreach ($tables as $table) {
                preg_match_all('/^\s*`?(\w+)`?\s+(?:BIGINT|INT|TINYINT|CHAR|VARCHAR|DECIMAL|DATE|DATETIME|JSON|TEXT|LONGTEXT)\b/mi', $table[2], $columns);
                $schema[$table[1]] = $columns[1];
            }
            preg_match_all('/ALTER TABLE\s+(\w+)\s+(.*?);/si', $sql, $alters, PREG_SET_ORDER);
            foreach ($alters as $alter) {
                preg_match_all('/ADD COLUMN\s+(\w+)/i', $alter[2], $columns);
                $schema[$alter[1]] = array_merge($schema[$alter[1]] ?? [], $columns[1]);
            }
        }
        return $schema;
    }

    /** Read-only verification of every migration hash, table and declared column. */
    public function inspect(\PDO $db): array
    {
        $checks = [];
        $tables = $db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchAll(\PDO::FETCH_COLUMN);
        $ledger = in_array('sif_schema_migration', $tables, true)
            ? $db->query('SELECT MIGRATION_FILE, SHA256 FROM sif_schema_migration')->fetchAll(\PDO::FETCH_KEY_PAIR) : [];
        $expectedFiles = [];
        foreach ($this->files() as $file) {
            $name = basename($file);
            $expectedFiles[] = $name;
            $checks['migration:' . $name] = isset($ledger[$name]) && hash_equals(hash_file('sha256', $file), $ledger[$name]);
        }
        $checks['migration_ledger_no_unknown_files'] = array_diff(array_keys($ledger), $expectedFiles) === [];
        $columns = $db->query('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()')->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_COLUMN);
        foreach ($this->expectedSchema() as $table => $expectedColumns) {
            $checks[$table . '_table'] = in_array($table, $tables, true);
            $checks[$table . '_columns'] = array_diff($expectedColumns, $columns[$table] ?? []) === [];
        }
        return $checks;
    }
}

