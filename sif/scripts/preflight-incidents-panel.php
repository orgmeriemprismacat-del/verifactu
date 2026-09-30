<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\MigrationRunner;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$baseDir = dirname(__DIR__);
$config = require $baseDir . '/config/sif.php';
$incidentConfig = (array) ($config['incidents'] ?? []);
$panelConfig = (array) ($config['panel'] ?? []);
$internalApi = (array) ($config['internal_api'] ?? []);

$readRoles = normalizeRoles((array) ($incidentConfig['read_roles'] ?? []));
$manageRoles = normalizeRoles((array) ($incidentConfig['manage_roles'] ?? []));
$missingManageReadRoles = array_values(array_diff($manageRoles, $readRoles));

$checks = [
    'environment_not_production' => in_array((string) ($config['env'] ?? ''), ['test', 'preproduction'], true),
    'php_pdo_mysql' => extension_loaded('pdo_mysql'),
    'php_openssl' => extension_loaded('openssl'),
    'incident_read_roles_configured' => $readRoles !== [],
    'incident_manage_roles_configured' => $manageRoles !== [],
    'incident_manage_roles_can_read' => $missingManageReadRoles === [],
    'incident_max_results_valid' => (int) ($incidentConfig['max_results'] ?? 0) >= 1
        && (int) ($incidentConfig['max_results'] ?? 0) <= 200,
    'internal_api_key_id_configured' => trim((string) ($internalApi['key_id'] ?? '')) !== '',
    'internal_api_secret_strong' => strlen((string) ($internalApi['secret'] ?? '')) >= 32,
    'internal_incident_signed_path_exact' => (string) ($internalApi['incident_signed_path'] ?? '') === '/api/incidents/manage.php',
    'panel_launch_key_id_configured' => trim((string) ($panelConfig['launch_key_id'] ?? '')) !== '',
    'panel_launch_secret_strong' => strlen((string) ($panelConfig['launch_secret'] ?? '')) >= 32,
    'panel_launch_path_exact' => (string) ($panelConfig['launch_path'] ?? '') === '/sif/incidencies/',
    'panel_clock_skew_valid' => (int) ($panelConfig['max_clock_skew_seconds'] ?? 0) >= 30
        && (int) ($panelConfig['max_clock_skew_seconds'] ?? 0) <= 300,
    'panel_session_name_configured' => trim((string) ($panelConfig['session_name'] ?? '')) !== '',
    'panel_files_present' => allFilesPresent($baseDir, [
        'public/sif/incidencies/index.php',
        'public/sif/incidencies/actions.php',
        'public/sif/incidencies/app.js',
        'public/sif/incidencies/style.css',
        'src/Http/IncidentPanelSession.php',
        'src/Service/PanelLaunchAuthenticator.php',
        'public/api/incidents/manage.php',
    ]),
    'schema_verified' => false,
    'errors_verifactu_table' => false,
    'sif_incident_action_table' => false,
    'internal_api_request_table' => false,
];
$errors = [];

try {
    $db = ConnectionFactory::make($config);
    $schemaChecks = (new MigrationRunner($baseDir . '/database'))->inspect($db);
    $checks['schema_verified'] = !in_array(false, $schemaChecks, true);

    foreach (['errors_verifactu', 'sif_incident_action', 'internal_api_request'] as $table) {
        $checks[$table . '_table'] = tableExists($db, $table);
    }
} catch (\Throwable $exception) {
    $errors['database'] = $exception->getMessage();
}

$failed = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));
$result = [
    'ok' => $failed === [],
    'scope' => 'uc-008-incident-panel-preflight',
    'production_authorized' => false,
    'environment' => (string) ($config['env'] ?? 'local'),
    'checks' => $checks,
];

if ($missingManageReadRoles !== []) {
    $result['missing_manage_read_roles'] = $missingManageReadRoles;
}
if ($failed !== []) {
    $result['failed'] = $failed;
}
if ($errors !== []) {
    $result['errors'] = $errors;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit($failed === [] ? 0 : 1);

function normalizeRoles(array $roles): array
{
    $normalized = [];
    foreach ($roles as $role) {
        $role = strtoupper(trim((string) $role));
        if ($role !== '') {
            $normalized[$role] = true;
        }
    }

    $result = array_keys($normalized);
    sort($result, SORT_STRING);
    return $result;
}

function allFilesPresent(string $baseDir, array $relativePaths): bool
{
    foreach ($relativePaths as $path) {
        if (!is_file($baseDir . '/' . $path)) {
            return false;
        }
    }

    return true;
}

function tableExists(\PDO $db, string $table): bool
{
    $stmt = $db->prepare(
        'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
    );
    $stmt->execute([$table]);

    return $stmt->fetchColumn() !== false;
}
