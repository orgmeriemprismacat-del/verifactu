<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\MigrationRunner;
use Prisma\Sif\Service\RuntimeVersionInspector;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$baseDir = dirname(__DIR__);
$config = require $baseDir . '/config/sif.php';
$governance = (array) ($config['version_governance'] ?? []);
$panel = (array) ($config['panel'] ?? []);
$checks = [
    'read_roles_configured' => (array) ($governance['read_roles'] ?? []) !== [],
    'manage_roles_configured' => (array) ($governance['manage_roles'] ?? []) !== [],
    'activation_enabled' => (bool) ($governance['activation_enabled'] ?? false),
    'panel_launch_key_id_configured' => trim((string) ($panel['launch_key_id'] ?? '')) !== '',
    'panel_launch_secret_configured' => trim((string) ($panel['launch_secret'] ?? '')) !== '',
    'version_launch_path_valid' => str_starts_with(
        trim((string) ($panel['version_launch_path'] ?? '')),
        '/sif/'
    ),
    'version_session_ttl_valid' => (int) ($panel['version_session_ttl_seconds'] ?? 0) >= 300
        && (int) ($panel['version_session_ttl_seconds'] ?? 0) <= 28800,
    'declaration_storage_private' => false,
    'release_manifest_external' => false,
    'sif_version_table' => false,
    'sif_declaration_table' => false,
    'sif_version_state_table' => false,
    'sif_version_activation_table' => false,
    'backup_restore_evidence_table' => false,
    'runtime_complete' => false,
];

$declarationRootConfig = trim((string) ($governance['declaration_root'] ?? ''));
$declarationRoot = $declarationRootConfig === '' ? false : realpath($declarationRootConfig);
$publicRoot = realpath($baseDir . '/public');
$releaseRoot = realpath($baseDir);
$manifestPathConfig = trim((string) ($governance['release_manifest_path'] ?? ''));
$manifestPath = $manifestPathConfig === '' ? false : realpath($manifestPathConfig);
if ($manifestPath !== false && is_file($manifestPath)) {
    $checks['release_manifest_external'] = $manifestPath !== $baseDir
        && !str_starts_with($manifestPath, $baseDir . DIRECTORY_SEPARATOR);
}
if ($declarationRoot !== false && is_dir($declarationRoot)) {
    $outsidePublic = $publicRoot === false
        || ($declarationRoot !== $publicRoot
            && !str_starts_with($declarationRoot, $publicRoot . DIRECTORY_SEPARATOR));
    $outsideRelease = $releaseRoot === false
        || ($declarationRoot !== $releaseRoot
            && !str_starts_with($declarationRoot, $releaseRoot . DIRECTORY_SEPARATOR));
    $checks['declaration_storage_private'] = $outsidePublic && $outsideRelease;
}

$runtime = null;
$errors = [];

try {
    $db = ConnectionFactory::make($config);
    foreach ([
        'sif_version',
        'sif_declaration',
        'sif_version_state',
        'sif_version_activation',
        'backup_restore_evidence',
    ] as $table) {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        $checks[$table . '_table'] = (int) $stmt->fetchColumn() === 1;
    }

    $runtime = (new RuntimeVersionInspector(
        $baseDir,
        new MigrationRunner($baseDir . '/database')
    ))->inspect($db, $config);
    $checks['runtime_complete'] = ($runtime['complete'] ?? false) === true;
} catch (\Throwable $exception) {
    $errors['runtime'] = $exception->getMessage();
}

$failed = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));
$result = [
    'ok' => $failed === [],
    'scope' => 'uc-010-technical-preflight-only',
    'production_authorized' => false,
    'environment' => (string) ($config['env'] ?? 'local'),
    'checks' => $checks,
    'failed' => $failed,
    'runtime' => $runtime,
    'errors' => $errors,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit($result['ok'] ? 0 : 1);
