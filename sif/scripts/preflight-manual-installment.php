<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\MigrationRunner;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
$env = (string) ($config['env'] ?? 'local');
$internalApi = (array) ($config['internal_api'] ?? []);
$installment = (array) ($config['installment_payment'] ?? []);

$roles = array_values(array_filter(array_map(
    static fn ($role): string => strtoupper(trim((string) $role)),
    (array) ($installment['write_roles'] ?? [])
)));

$checks = [
    'environment_not_production' => $env !== 'production',
    'php_pdo_mysql' => extension_loaded('pdo_mysql'),
    'php_openssl' => extension_loaded('openssl'),
    'internal_api_key_id_configured' => trim((string) ($internalApi['key_id'] ?? '')) !== '',
    'internal_api_secret_configured' => (string) ($internalApi['secret'] ?? '') !== '',
    'installment_signed_path_configured' =>
        trim((string) ($internalApi['installment_payment_signed_path'] ?? '')) !== '',
    'installment_write_roles_configured' => $roles !== [],
    'database_connectivity' => false,
    'schema_verified' => false,
    'factura_table' => false,
    'fact_rels_table' => false,
    'payment_transaction_table' => false,
    'payment_allocation_table' => false,
    'payment_external_receipt_claim_table' => false,
    'internal_api_request_table' => false,
    'sif_audit_event_table' => false,
    'operational_event_table' => false,
    'payment_action_event_table' => false,
    'external_receipt_claim_unique_key' => false,
    'external_receipt_claim_delete_cascade' => false,
];
$errors = [];

try {
    $db = ConnectionFactory::make($config);
    $checks['database_connectivity'] = true;

    $schemaChecks = (new MigrationRunner(dirname(__DIR__) . '/database'))->inspect($db);
    $checks['schema_verified'] = !in_array(false, $schemaChecks, true);

    foreach ([
        'factura',
        'fact_rels',
        'payment_transaction',
        'payment_allocation',
        'payment_external_receipt_claim',
        'internal_api_request',
        'payment_action_event',
        'operational_event',
        'sif_audit_event',
    ] as $table) {
        $checks[$table . '_table'] = tableExists($db, $table);
    }

    if ($checks['payment_external_receipt_claim_table']) {
        $checks['external_receipt_claim_unique_key'] = uniqueIndexExists(
            $db,
            'payment_external_receipt_claim',
            'uq_payment_external_receipt_type_value'
        );
        $checks['external_receipt_claim_delete_cascade'] = cascadeForeignKeyExists(
            $db,
            'payment_external_receipt_claim',
            'fk_payment_external_receipt_payment'
        );
    }
} catch (\Throwable $exception) {
    $errors['database'] = $exception->getMessage();
}

$failed = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));
$result = [
    'ok' => $failed === [],
    'environment' => $env,
    'checks' => $checks,
    'installment_roles' => $roles,
    'installment_signed_path' => (string) ($internalApi['installment_payment_signed_path'] ?? ''),
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
    $stmt = $db->prepare(
        'SELECT TABLE_NAME
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);

    return $stmt->fetchColumn() !== false;
}

function uniqueIndexExists(\PDO $db, string $table, string $index): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*)
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND INDEX_NAME = ?
           AND NON_UNIQUE = 0'
    );
    $stmt->execute([$table, $index]);

    return (int) $stmt->fetchColumn() > 0;
}

function cascadeForeignKeyExists(\PDO $db, string $table, string $constraint): bool
{
    $stmt = $db->prepare(
        'SELECT DELETE_RULE
         FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND CONSTRAINT_NAME = ?'
    );
    $stmt->execute([$table, $constraint]);

    return strtoupper((string) $stmt->fetchColumn()) === 'CASCADE';
}
