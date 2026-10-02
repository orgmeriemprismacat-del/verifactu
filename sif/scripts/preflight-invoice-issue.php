<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
$internalApi = $config['internal_api'] ?? [];
$invoiceIssue = $config['invoice_issue'] ?? [];
$issuer = $config['issuer'] ?? [];

$keyId = trim((string) ($internalApi['key_id'] ?? ''));
$secret = (string) ($internalApi['secret'] ?? '');
$signedPath = trim((string) ($internalApi['invoice_issue_signed_path'] ?? ''));
$writeRoles = array_values(array_filter(
    array_map(static fn (mixed $role): string => trim((string) $role), (array) ($invoiceIssue['write_roles'] ?? [])),
    static fn (string $role): bool => $role !== ''
));
$issuerNif = strtoupper(trim((string) ($issuer['nif'] ?? '')));
$issuerName = trim((string) ($issuer['name'] ?? ''));

$checks = [
    'php_openssl' => extension_loaded('openssl'),
    'internal_api_key_id_configured' => $keyId !== '',
    'internal_api_secret_configured' => $secret !== '',
    'invoice_issue_signed_path_configured' => $signedPath !== '',
    'invoice_issue_write_roles_configured' => $writeRoles !== [],
    'issuer_nif_configured' => $issuerNif !== '' && $issuerNif !== 'G00000000',
    'issuer_name_configured' => $issuerName !== '',
    'sif_database_connectivity' => false,
    'internal_api_request_table' => false,
    'sif_audit_event_table' => false,
    'operational_event_table' => false,
    'factura_table' => false,
    'factura_linia_table' => false,
    'factura_registres_table' => false,
    'factura_registre_control_table' => false,
    'fiscal_sequence_table' => false,
    'fiscal_chain_state_table' => false,
    'fiscal_queue_table' => false,
    'fact_rels_table' => false,
    'commercial_operation_table' => false,
    'commercial_operation_line_table' => false,
    'operation_line_invoice_link_table' => false,
    'payment_transaction_table' => false,
    'payment_allocation_table' => false,
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
        'factura_registre_control',
        'fiscal_sequence',
        'fiscal_chain_state',
        'fiscal_queue',
        'fact_rels',
        'commercial_operation',
        'commercial_operation_line',
        'operation_line_invoice_link',
        'payment_transaction',
        'payment_allocation',
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
    'checks' => $checks,
];

if ($failed !== []) {
    $result['failed'] = $failed;
}
if ($errors !== []) {
    $result['errors'] = $errors;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit($failed === [] ? 0 : 1);

function tableExists(\PDO $db, string $table): bool
{
    $stmt = $db->prepare(
        'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);

    return $stmt->fetchColumn() !== false;
}

function rowExists(\PDO $db, string $sql): bool
{
    $stmt = $db->query($sql);

    return $stmt !== false && (int) $stmt->fetchColumn() === 1;
}
