<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Database\MigrationRunner;

final class RuntimeVersionInspector
{
    public function __construct(
        private string $baseDir,
        private MigrationRunner $migrationRunner,
        private ?ReleaseManifestVerifier $manifestVerifier = null,
        private ?RuntimeConfigFingerprint $configFingerprint = null
    ) {
        $this->manifestVerifier ??= new ReleaseManifestVerifier();
        $this->configFingerprint ??= new RuntimeConfigFingerprint();
    }

    public function inspect(\PDO $db, array $config): array
    {
        $governance = (array) ($config['version_governance'] ?? []);
        $revision = strtolower(trim((string) ($governance['runtime_git_revision'] ?? '')));
        $manifestPath = trim((string) ($governance['release_manifest_path'] ?? ''));

        $manifest = [
            'ok' => false,
            'artifact_hash' => null,
            'file_count' => 0,
            'verified_count' => 0,
            'mismatches' => ['manifest' => 'NOT_VERIFIED'],
        ];
        $errors = [];

        try {
            $manifest = $this->manifestVerifier->verify($this->baseDir, $manifestPath);
        } catch (\Throwable $exception) {
            $errors['release_manifest'] = $exception->getMessage();
        }

        try {
            $schemaChecks = $this->migrationRunner->inspect($db);
            $schemaChecks = array_merge($schemaChecks, $this->uc010DatabaseHardeningChecks($db));
            $schemaVerified = !in_array(false, $schemaChecks, true);
        } catch (\Throwable $exception) {
            $schemaChecks = [];
            $schemaVerified = false;
            $errors['schema'] = $exception->getMessage();
        }

        $migrationFiles = $this->migrationRunner->files();
        $databaseVersion = basename((string) end($migrationFiles));
        if (strlen($databaseVersion) > 80) {
            $databaseVersion = substr($databaseVersion, 0, 80);
        }

        $revisionValid = preg_match('/^[0-9a-f]{40}$/D', $revision) === 1;
        $configHash = $this->configFingerprint->hash($config);

        return [
            'complete' => $revisionValid && ($manifest['ok'] ?? false) === true && $schemaVerified,
            'environment' => (string) ($config['env'] ?? 'local'),
            'git_revision' => $revisionValid ? $revision : null,
            'artifact_hash' => $manifest['artifact_hash'] ?? null,
            'config_hash' => $configHash,
            'database_version' => $databaseVersion,
            'schema_verified' => $schemaVerified,
            'schema_checks' => $schemaChecks,
            'manifest' => $manifest,
            'errors' => $errors,
        ];
    }

    private function uc010DatabaseHardeningChecks(\PDO $db): array
    {
        $checks = [
            'uc010_single_active_unique_index' => false,
            'uc010_version_status_check' => false,
            'uc010_activation_status_check' => false,
            'uc010_state_singleton_check' => false,
            'uc010_trigger_activation_no_update' => false,
            'uc010_trigger_activation_no_delete' => false,
            'uc010_trigger_state_no_delete' => false,
        ];

        $index = $db->prepare(
            'SELECT COUNT(*)
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?
               AND NON_UNIQUE = 0'
        );
        $index->execute(['sif_version', 'uq_sif_version_single_active']);
        $checks['uc010_single_active_unique_index'] = (int) $index->fetchColumn() > 0;

        $constraint = $db->prepare(
            'SELECT COUNT(*)
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = ?'
        );
        foreach ([
            ['uc010_version_status_check', 'sif_version', 'chk_sif_version_status_uc010'],
            ['uc010_activation_status_check', 'sif_version_activation', 'chk_sif_version_activation_status_uc010'],
            ['uc010_state_singleton_check', 'sif_version_state', 'chk_sif_version_state_singleton_uc010'],
        ] as [$key, $table, $name]) {
            $constraint->execute([$table, $name, 'CHECK']);
            $checks[$key] = (int) $constraint->fetchColumn() > 0;
        }

        $trigger = $db->prepare(
            'SELECT ACTION_TIMING, EVENT_MANIPULATION
             FROM information_schema.TRIGGERS
             WHERE TRIGGER_SCHEMA = DATABASE()
               AND EVENT_OBJECT_TABLE = ?
               AND TRIGGER_NAME = ?
             LIMIT 1'
        );
        foreach ([
            ['uc010_trigger_activation_no_update', 'sif_version_activation', 'trg_sif_version_activation_no_update', 'UPDATE'],
            ['uc010_trigger_activation_no_delete', 'sif_version_activation', 'trg_sif_version_activation_no_delete', 'DELETE'],
            ['uc010_trigger_state_no_delete', 'sif_version_state', 'trg_sif_version_state_no_delete', 'DELETE'],
        ] as [$key, $table, $name, $event]) {
            $trigger->execute([$table, $name]);
            $row = $trigger->fetch(\PDO::FETCH_ASSOC);
            $checks[$key] = is_array($row)
                && strtoupper((string) ($row['ACTION_TIMING'] ?? '')) === 'BEFORE'
                && strtoupper((string) ($row['EVENT_MANIPULATION'] ?? '')) === $event;
        }

        return $checks;
    }
}
