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
}
