<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
$environment = strtoupper(trim((string) ($config['env'] ?? 'local')));
$qualifiedEnvironment = in_array(
    $environment,
    ['PROD', 'PRODUCTION', 'PREPROD', 'PREPRODUCTION'],
    true
);

$internalApi = $config['internal_api'] ?? [];
$rectification = $config['rectification'] ?? [];
$issuer = $config['issuer'] ?? [];
$aeat = $config['aeat'] ?? [];
$documents = $config['documents'] ?? [];

$roles = array_values(array_filter(
    array_map(
        static fn (mixed $role): string => trim((string) $role),
        (array) ($rectification['write_roles'] ?? [])
    ),
    static fn (string $role): bool => $role !== ''
));

$issuerNif = strtoupper(trim((string) ($issuer['nif'] ?? '')));
$issuerName = trim((string) ($issuer['name'] ?? ''));
$generatorVersion = trim((string) ($documents['generator_version'] ?? ''));

$checks = [
    'uc005_feature_enabled' => ($rectification['enabled'] ?? false) === true,
    'uc005_write_roles_configured' => $roles !== [],
    'internal_api_key_id_configured' => trim((string) ($internalApi['key_id'] ?? '')) !== '',
    'internal_api_secret_configured' => (string) ($internalApi['secret'] ?? '') !== '',
    'rectification_signed_path_configured' => trim((string) (
        $internalApi['rectification_signed_path'] ?? ''
    )) !== '',
    'issuer_nif_configured' => $issuerNif !== '' && $issuerNif !== 'G00000000',
    'issuer_name_configured' => $issuerName !== '',
    'document_generator_version_configured' => $generatorVersion !== ''
        && mb_strlen($generatorVersion, 'UTF-8') <= 80,
    'aeat_system_name_configured' => !$qualifiedEnvironment
        || trim((string) ($aeat['system_name'] ?? '')) !== '',
    'aeat_system_id_configured' => !$qualifiedEnvironment
        || trim((string) ($aeat['system_id'] ?? '')) !== '',
    'aeat_system_version_configured' => !$qualifiedEnvironment
        || trim((string) ($aeat['system_version'] ?? '')) !== '',
    'aeat_installation_id_configured' => !$qualifiedEnvironment
        || trim((string) ($aeat['installation_id'] ?? '')) !== '',
    'sif_database_connectivity' => false,
    'internal_api_request_table' => false,
    'sif_audit_event_table' => false,
    'operational_event_table' => false,
    'factura_table' => false,
    'factura_linia_table' => false,
    'factura_registres_table' => false,
    'factura_rectificacio_table' => false,
    'factura_registre_control_table' => false,
    'fiscal_sequence_table' => false,
    'fiscal_chain_state_table' => false,
    'fiscal_queue_table' => false,
    'fact_rels_table' => false,
    'document_job_table' => false,
    'factura_documents_table' => false,
    'fiscal_chain_state_seeded' => false,
];

$errors = [];

try {
    $db = ConnectionFactory::make($config);
    $checks['sif_database_connectivity'] = true;

    foreach ([
        'internal_api_request',
        'sif_audit_event',
        'operational_event',
        'factura',
        'factura_linia',
        'factura_registres',
        'factura_rectificacio',
        'factura_registre_control',
        'fiscal_sequence',
        'fiscal_chain_state',
        'fiscal_queue',
        'fact_rels',
        'document_job',
        'factura_documents',
    ] as $table) {
        $checks[$table . '_table'] = tableExists($db, $table);
    }

    $checks['fiscal_chain_state_seeded'] = rowExists(
        $db,
        'SELECT COUNT(*) FROM fiscal_chain_state WHERE ID = 1'
    );
} catch (\Throwable $exception) {
    $errors['sif_database'] = $exception->getMessage();
}

$failed = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));
$result = [
    'ok' => $failed === [],
    'environment' => (string) ($config['env'] ?? 'local'),
    'qualified_environment' => $qualifiedEnvironment,
    'checks' => $checks,
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

function tableExists(\PDO $db, string $table): bool
{
    $statement = $db->prepare(
        'SELECT TABLE_NAME
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $statement->execute([$table]);

    return $statement->fetchColumn() !== false;
}

function rowExists(\PDO $db, string $sql): bool
{
    $statement = $db->query($sql);

    return $statement !== false && (int) $statement->fetchColumn() === 1;
}
