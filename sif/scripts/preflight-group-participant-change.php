<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
$env = strtoupper((string) ($config['env'] ?? 'LOCAL'));
$internal = $config['internal_api'] ?? [];
$group = $config['group_participant_change'] ?? [];

$checks = [
    'environment_not_production' => !in_array($env, ['PROD', 'PRODUCTION'], true),
    'internal_api_key_id_configured' => trim((string) ($internal['key_id'] ?? '')) !== '',
    'internal_api_secret_configured' => trim((string) ($internal['secret'] ?? '')) !== '',
    'signed_path_configured' => trim((string) ($internal['group_participant_change_signed_path'] ?? '')) !== '',
    'preview_roles_configured' => (array) ($group['preview_roles'] ?? []) !== [],
    'manage_roles_configured' => (array) ($group['manage_roles'] ?? []) !== [],
    'sif_database_connectivity' => false,
    'factura_table' => false,
    'factura_linia_table' => false,
    'fact_rels_table' => false,
    'enrollment_fund_movement_table' => false,
    'group_change_execution_table' => false,
    'group_change_step_table' => false,
    'internal_api_request_table' => false,
];

$warnings = [
    'academic_gateway_productive_not_accredited' =>
        'Preview/plan can be enabled, but confirm must remain disabled until a productive GroupParticipantAcademicGatewayInterface implementation is audited.',
];

$errors = [];

try {
    $db = ConnectionFactory::make($config);
    $checks['sif_database_connectivity'] = true;

    foreach ([
        'factura' => 'factura_table',
        'factura_linia' => 'factura_linia_table',
        'fact_rels' => 'fact_rels_table',
        'enrollment_fund_movement' => 'enrollment_fund_movement_table',
        'group_participant_change_execution' => 'group_change_execution_table',
        'group_participant_change_step' => 'group_change_step_table',
        'internal_api_request' => 'internal_api_request_table',
    ] as $table => $check) {
        $checks[$check] = tableExists($db, $table);
    }
} catch (Throwable $exception) {
    $errors['sif_database'] = $exception->getMessage();
}

$failed = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));

$result = [
    'ok' => $failed === [],
    'environment' => $env,
    'scope' => 'UC-016A/UC-016B preview-plan API',
    'checks' => $checks,
    'mutation_confirm_ready' => false,
    'warnings' => $warnings,
];

if ($failed !== []) {
    $result['failed'] = $failed;
}
if ($errors !== []) {
    $result['errors'] = $errors;
}

echo json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
), PHP_EOL;

exit($failed === [] ? 0 : 1);

function tableExists(PDO $db, string $table): bool
{
    $stmt = $db->query('SHOW TABLES LIKE ' . $db->quote($table));

    return $stmt !== false && $stmt->fetchColumn() !== false;
}
