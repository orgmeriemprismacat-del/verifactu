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
$checks = [
    'read_roles_configured' => (array) ($governance['read_roles'] ?? []) !== [],
    'manage_roles_configured' => (array) ($governance['manage_roles'] ?? []) !== [],
    'activation_enabled' => (bool) ($governance['activation_enabled'] ?? false),
    'declaration_storage_private' => false,
    'sif_version_table' => false,
    'sif_declaration_table' => false,
    'sif_version_state_table' => false,
    'sif_version_activation_table' => false,
    'backup_restore_evidence_table' => false,
    'runtime_complete' => false,
];

$declarationRoot = realpath((string) ($governance['declaration_root'] ?? ''));
$publicRoot = realpath($baseDir . '/public');
if ($declarationRoot !== false && is_dir($declarationRoot)) {
    $checks['declaration_storage_private'] = $publicRoot === false
        || ($declarationRoot !== $publicRoot
            && !str_starts_with($declarationRoot, $publicRoot . DIRECTORY_SEPARATOR));
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
