<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
$env = strtolower(trim((string) ($config['env'] ?? 'local')));
$internalApi = (array) ($config['internal_api'] ?? []);
$claimPayments = (array) ($config['claim_payments'] ?? []);

$checks = [
    'environment_not_production' => !in_array($env, ['production', 'prod'], true),
    'internal_api_key_configured' => trim((string) ($internalApi['key_id'] ?? '')) !== '',
    'internal_api_secret_configured' => trim((string) ($internalApi['secret'] ?? '')) !== '',
    'claim_payment_signed_path_configured' =>
        trim((string) ($internalApi['claim_payment_signed_path'] ?? '')) !== '',
    'claim_payment_manage_roles_configured' =>
        array_values(array_filter(array_map(
            static fn (mixed $role): string => trim((string) $role),
            (array) ($claimPayments['manage_roles'] ?? [])
        ))) !== [],
    'sif_database_connectivity' => false,
    'factura_table' => false,
    'fact_rels_table' => false,
    'payment_transaction_table' => false,
    'payment_allocation_table' => false,
    'payment_action_event_table' => false,
    'internal_api_request_table' => false,
    'payment_payload_hash_version_column' => false,
    'fiscal_chain_state_seeded' => false,
    'legacy_database_connectivity' => false,
    'legacy_inscripcions_table' => false,
    'legacy_claim_payment_columns' => false,
];
$errors = [];

try {
    $sifDb = ConnectionFactory::make($config);
    $checks['sif_database_connectivity'] = true;

    foreach ([
        'factura',
        'fact_rels',
        'payment_transaction',
        'payment_allocation',
        'payment_action_event',
        'internal_api_request',
    ] as $table) {
        $checks[$table . '_table'] = tableExists($sifDb, $table);
    }

    $checks['payment_payload_hash_version_column'] = columnExists(
        $sifDb,
        'payment_transaction',
        'PAYLOAD_HASH_VERSION'
    );
    $checks['fiscal_chain_state_seeded'] = rowExists(
        $sifDb,
        'SELECT COUNT(*) FROM fiscal_chain_state WHERE ID = 1'
    );
} catch (\Throwable $exception) {
    $errors['sif_database'] = safeError($exception);
}

try {
    $legacyDb = ConnectionFactory::makeLegacy($config);
    $checks['legacy_database_connectivity'] = true;
    $checks['legacy_inscripcions_table'] = tableExists($legacyDb, 'inscripcions');

    if ($checks['legacy_inscripcions_table']) {
        $requiredLegacyColumns = [
            'ID',
            'IDPAG',
            'A_PAGAR',
            'PAGAMENT',
            'DATA PAG',
            'INSC CURS',
            'OBSERVACIONS',
        ];
        $available = tableColumns($legacyDb, 'inscripcions');
        $checks['legacy_claim_payment_columns'] =
            array_diff($requiredLegacyColumns, $available) === [];
    }
} catch (\Throwable $exception) {
    $errors['legacy_database'] = safeError($exception);
}

$failed = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));
$result = [
    'ok' => $failed === [],
    'environment' => $env,
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
    $stmt = $db->query('SHOW TABLES LIKE ' . $db->quote($table));

    return $stmt !== false && $stmt->fetchColumn() !== false;
}

function columnExists(\PDO $db, string $table, string $column): bool
{
    return in_array($column, tableColumns($db, $table), true);
}

function tableColumns(\PDO $db, string $table): array
{
    if (preg_match('/^[A-Za-z0-9_]+$/D', $table) !== 1) {
        throw new \InvalidArgumentException('Invalid table name');
    }

    $stmt = $db->query('SHOW COLUMNS FROM ' . $table);
    if ($stmt === false) {
        return [];
    }

    $columns = [];
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $field = trim((string) ($row['Field'] ?? ''));
        if ($field !== '') {
            $columns[] = $field;
        }
    }

    return $columns;
}

function rowExists(\PDO $db, string $sql): bool
{
    $stmt = $db->query($sql);

    return $stmt !== false && (int) $stmt->fetchColumn() === 1;
}

function safeError(\Throwable $exception): string
{
    $message = trim($exception->getMessage());

    // Do not echo DSNs, usernames, passwords, secrets or driver details from
    // exception messages in validation evidence.
    return $message === '' ? $exception::class : $exception::class . ': configuration/connectivity check failed';
}
