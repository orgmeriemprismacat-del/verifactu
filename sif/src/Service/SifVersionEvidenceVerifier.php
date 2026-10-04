<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\BackupRestoreEvidenceRepository;

final class SifVersionEvidenceVerifier
{
    public function __construct(
        private RuntimeVersionInspector $runtimeInspector,
        private array $config,
        private ?BackupRestoreEvidenceRepository $backupEvidence = null
    ) {
        $this->backupEvidence ??= new BackupRestoreEvidenceRepository();
    }

    public function verify(\PDO $db, string $uuidVersion): array
    {
        $uuidVersion = strtolower(trim($uuidVersion));
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $uuidVersion) !== 1) {
            throw SifException::validation('Invalid SIF version UUID');
        }

        $version = $this->one($db, 'SELECT * FROM sif_version WHERE UUID_VERSION = ? LIMIT 1', [$uuidVersion]);
        if ($version === null) {
            throw SifException::notFound('SIF version not found');
        }

        $state = $this->one($db, 'SELECT * FROM sif_version_state WHERE ID = 1', []);
        $activation = $this->one(
            $db,
            'SELECT * FROM sif_version_activation WHERE UUID_VERSION = ? ORDER BY CREATED_AT DESC, ID DESC LIMIT 1',
            [$uuidVersion]
        );

        $declaration = null;
        if (is_array($activation) && trim((string) ($activation['UUID_DECLARATION'] ?? '')) !== '') {
            $declaration = $this->one(
                $db,
                'SELECT * FROM sif_declaration WHERE UUID_DECLARATION = ? LIMIT 1',
                [(string) $activation['UUID_DECLARATION']]
            );
        }

        $backup = null;
        $backupUuid = is_array($activation)
            ? trim((string) ($activation['UUID_BACKUP_EVIDENCE'] ?? ''))
            : '';
        if ($backupUuid !== '') {
            $backup = $this->backupEvidence->findByUuid($db, $backupUuid);
        }

        $runtime = $this->runtimeInspector->inspect($db, $this->config);
        $declarationFileOk = $this->declarationFileMatches($declaration);

        $activeCount = (int) $db->query(
            "SELECT COUNT(*) FROM sif_version WHERE STATUS = 'ACTIVE'"
        )->fetchColumn();

        $auditCount = $this->count($db,
            "SELECT COUNT(*) FROM sif_audit_event
             WHERE ACTION = 'VERSION_ACTIVATED'
               AND RESULT = 'SUCCESS'
               AND RESOURCE_TYPE = 'SIF_VERSION'
               AND RESOURCE_ID = ?",
            [$uuidVersion]
        );
        $operationalCount = $this->count($db,
            "SELECT COUNT(*) FROM operational_event
             WHERE OPERATION_TYPE = 'VERSION_ACTIVATED'
               AND STATUS = 'RECORDED'
               AND SOURCE_TYPE = 'SIF_VERSION'
               AND SOURCE_ID = ?",
            [$uuidVersion]
        );

        $snapshot = [];
        if (is_array($activation)) {
            $decoded = json_decode((string) ($activation['RUNTIME_EVIDENCE_JSON'] ?? ''), true);
            $snapshot = is_array($decoded) ? $decoded : [];
        }

        $backupRequired = (bool) ($this->config['version_governance']['require_backup_evidence'] ?? true);
        $backupAcceptable = $backupUuid === ''
            ? !$backupRequired
            : is_array($backup)
                && $this->backupEvidence->isAcceptable(
                    $backup,
                    (string) ($runtime['environment'] ?? '')
                );

        $checks = [
            'version_active' => strtoupper((string) ($version['STATUS'] ?? '')) === 'ACTIVE',
            'singleton_points_to_version' => is_array($state)
                && (string) ($state['ACTIVE_UUID_VERSION'] ?? '') === $uuidVersion,
            'single_active_version' => $activeCount === 1,
            'activation_present' => is_array($activation),
            'activation_status' => is_array($activation)
                && strtoupper((string) ($activation['STATUS'] ?? '')) === 'ACTIVATED',
            'declaration_present' => is_array($declaration),
            'declaration_approved' => is_array($declaration)
                && strtoupper((string) ($declaration['STATUS'] ?? '')) === 'APPROVED',
            'declaration_file_integrity' => $declarationFileOk,
            'runtime_complete' => ($runtime['complete'] ?? false) === true,
            'version_git_matches_runtime' => $this->same($version['GIT_REVISION'] ?? null, $runtime['git_revision'] ?? null),
            'version_artifact_matches_runtime' => $this->same($version['ARTIFACT_HASH'] ?? null, $runtime['artifact_hash'] ?? null),
            'version_config_matches_runtime' => $this->same($version['CONFIG_HASH'] ?? null, $runtime['config_hash'] ?? null),
            'version_database_matches_runtime' => $this->same($version['DATABASE_VERSION'] ?? null, $runtime['database_version'] ?? null),
            'activation_runtime_matches_version' => is_array($activation)
                && $this->same($activation['RUNTIME_GIT_REVISION'] ?? null, $version['GIT_REVISION'] ?? null)
                && $this->same($activation['RUNTIME_ARTIFACT_HASH'] ?? null, $version['ARTIFACT_HASH'] ?? null)
                && $this->same($activation['RUNTIME_CONFIG_HASH'] ?? null, $version['CONFIG_HASH'] ?? null)
                && $this->same($activation['RUNTIME_DATABASE_VERSION'] ?? null, $version['DATABASE_VERSION'] ?? null),
            'activation_snapshot_declaration_hash_matches' => is_array($declaration)
                && $this->same(
                    $snapshot['declaration']['document_hash'] ?? null,
                    $declaration['DOCUMENT_HASH'] ?? null
                ),
            'backup_evidence_required' => !$backupRequired || $backupUuid !== '',
            'backup_evidence_acceptable' => $backupAcceptable,
            'audit_activation_present' => $auditCount > 0,
            'operational_activation_present' => $operationalCount > 0,
        ];

        $failed = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));

        return [
            'ok' => $failed === [],
            'uuid_version' => $uuidVersion,
            'environment' => (string) ($runtime['environment'] ?? ''),
            'checked_at' => (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))
                ->format(DATE_ATOM),
            'checks' => $checks,
            'failed' => $failed,
            'version' => [
                'version_code' => $version['VERSION_CODE'] ?? null,
                'status' => $version['STATUS'] ?? null,
                'git_revision' => $version['GIT_REVISION'] ?? null,
                'artifact_hash' => $version['ARTIFACT_HASH'] ?? null,
                'config_hash' => $version['CONFIG_HASH'] ?? null,
                'database_version' => $version['DATABASE_VERSION'] ?? null,
            ],
            'activation' => is_array($activation) ? [
                'uuid_activation' => $activation['UUID_ACTIVATION'] ?? null,
                'status' => $activation['STATUS'] ?? null,
                'uuid_declaration' => $activation['UUID_DECLARATION'] ?? null,
                'uuid_backup_evidence' => $activation['UUID_BACKUP_EVIDENCE'] ?? null,
                'created_at' => $activation['CREATED_AT'] ?? null,
            ] : null,
            'declaration' => is_array($declaration) ? [
                'uuid_declaration' => $declaration['UUID_DECLARATION'] ?? null,
                'declaration_version' => $declaration['DECLARATION_VERSION'] ?? null,
                'document_hash' => $declaration['DOCUMENT_HASH'] ?? null,
                'status' => $declaration['STATUS'] ?? null,
                'file_integrity' => $declarationFileOk,
            ] : null,
            'backup_required' => $backupRequired,
            'backup' => $this->backupSummary($backup),
            'runtime' => [
                'complete' => $runtime['complete'] ?? false,
                'schema_verified' => $runtime['schema_verified'] ?? false,
                'git_revision' => $runtime['git_revision'] ?? null,
                'artifact_hash' => $runtime['artifact_hash'] ?? null,
                'config_hash' => $runtime['config_hash'] ?? null,
                'database_version' => $runtime['database_version'] ?? null,
                'manifest_file_count' => $runtime['manifest']['file_count'] ?? 0,
                'manifest_verified_count' => $runtime['manifest']['verified_count'] ?? 0,
            ],
            'trace' => [
                'audit_activation_count' => $auditCount,
                'operational_activation_count' => $operationalCount,
            ],
            'production_authorized' => false,
        ];
    }

    private function declarationFileMatches(?array $declaration): bool
    {
        if ($declaration === null) {
            return false;
        }

        $rootConfig = trim((string) ($this->config['version_governance']['declaration_root'] ?? ''));
        if ($rootConfig === '') {
            return false;
        }

        $root = realpath($rootConfig);
        if ($root === false || !is_dir($root)) {
            return false;
        }

        $releaseRoot = realpath(dirname(__DIR__, 2));
        if ($releaseRoot !== false
            && ($root === $releaseRoot || str_starts_with($root, $releaseRoot . DIRECTORY_SEPARATOR))
        ) {
            return false;
        }

        $storageKey = str_replace('\\', '/', trim((string) ($declaration['STORAGE_KEY'] ?? '')));
        if ($storageKey === '' || str_starts_with($storageKey, '/') || str_contains('/' . $storageKey . '/', '/../')) {
            return false;
        }

        $file = realpath($root . '/' . $storageKey);
        if ($file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
            return false;
        }

        $expected = strtolower(trim((string) ($declaration['DOCUMENT_HASH'] ?? '')));
        $actual = hash_file('sha256', $file);
        return is_string($actual)
            && preg_match('/^[0-9a-f]{64}$/D', $expected) === 1
            && hash_equals($expected, strtolower($actual));
    }

    private function backupSummary(?array $backup): ?array
    {
        if ($backup === null) {
            return null;
        }

        return array_intersect_key($backup, array_fill_keys([
            'UUID_EVIDENCE',
            'OPERATION_TYPE',
            'ENVIRONMENT',
            'STATUS',
            'INTEGRITY_RESULT',
            'RPO_MINUTES',
            'RTO_MINUTES',
            'STARTED_AT',
            'FINISHED_AT',
        ], true));
    }

    private function one(\PDO $db, string $sql, array $params): ?array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function count(\PDO $db, string $sql, array $params): int
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function same(mixed $left, mixed $right): bool
    {
        $left = trim((string) ($left ?? ''));
        $right = trim((string) ($right ?? ''));
        return $left !== '' && $right !== '' && hash_equals(strtolower($left), strtolower($right));
    }
}
