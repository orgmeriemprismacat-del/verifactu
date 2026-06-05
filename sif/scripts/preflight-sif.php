<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;

$config = require dirname(__DIR__) . '/config/sif.php';
$checks = [
    'database_connectivity' => false,
    'factura_table' => false,
    'factura_linia_table' => false,
    'factura_registres_table' => false,
    'fiscal_queue_table' => false,
    'payment_transaction_table' => false,
    'payment_allocation_table' => false,
    'redsys_notifications_table' => false,
    'factura_documents_table' => false,
    'errors_verifactu_table' => false,
    'fiscal_chain_state_seeded' => false,
];
$errors = [];

try {
    $db = ConnectionFactory::make($config);
    $checks['database_connectivity'] = true;

    foreach ([
        'factura',
        'factura_linia',
        'factura_registres',
        'fiscal_queue',
        'payment_transaction',
        'payment_allocation',
        'redsys_notifications',
        'factura_documents',
        'errors_verifactu',
    ] as $table) {
        $checks[$table . '_table'] = tableExists($db, $table);
    }

    $checks['fiscal_chain_state_seeded'] = rowExists(
        $db,
        'SELECT COUNT(*) FROM fiscal_chain_state WHERE ID = 1'
    );
} catch (\Throwable $exception) {
    $errors['database'] = $exception->getMessage();
}

$failed = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));
$result = [
    'ok' => count($failed) === 0,
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
exit(count($failed) === 0 ? 0 : 1);

function tableExists(\PDO $db, string $table): bool
{
    $stmt = $db->query('SHOW TABLES LIKE ' . $db->quote($table));

    return $stmt !== false && $stmt->fetchColumn() !== false;
}

function rowExists(\PDO $db, string $sql): bool
{
    $stmt = $db->query($sql);

    return $stmt !== false && (int) $stmt->fetchColumn() === 1;
}
