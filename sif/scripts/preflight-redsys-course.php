<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
$env = (string) ($config['env'] ?? 'local');
$checks = [
    'environment_not_production' => $env !== 'production',
    'redsys_merchant_key_configured' => (string) ($config['redsys']['merchant_key'] ?? '') !== '',
    'legacy_db_configured' => (string) ($config['legacy_db']['dsn'] ?? '') !== '',
    'sif_database_connectivity' => false,
    'legacy_database_connectivity' => false,
    'factura_table' => false,
    'payment_transaction_table' => false,
    'payment_allocation_table' => false,
    'enrollment_fund_movement_table' => false,
    'notification_outbox_table' => false,
    'redsys_payment_intent_table' => false,
    'redsys_notifications_table' => false,
    'redsys_callback_queue_table' => false,
    'callback_endpoint_present' => is_file(dirname(__DIR__) . '/public/api/redsys/callback.php'),
    'course_intent_endpoint_present' => is_file(dirname(__DIR__) . '/public/api/redsys/course-intent.php'),
    'course_status_endpoint_present' => is_file(dirname(__DIR__) . '/public/api/redsys/course-status.php'),
    'worker_script_present' => is_file(dirname(__DIR__) . '/scripts/process-redsys-callback-queue.php'),
    'verification_script_present' => is_file(dirname(__DIR__) . '/scripts/verify-redsys-course-preproduction.php'),
    'fiscal_chain_state_seeded' => false,
    'legacy_inscripcions_table' => false,
    'legacy_curs_table' => false,
];
$errors = [];

try {
    $sifDb = ConnectionFactory::make($config);
    $checks['sif_database_connectivity'] = true;
    $checks['factura_table'] = tableExists($sifDb, 'factura');
    $checks['payment_transaction_table'] = tableExists($sifDb, 'payment_transaction');
    $checks['payment_allocation_table'] = tableExists($sifDb, 'payment_allocation');
    $checks['enrollment_fund_movement_table'] = tableExists($sifDb, 'enrollment_fund_movement');
    $checks['notification_outbox_table'] = tableExists($sifDb, 'notification_outbox');
    $checks['redsys_payment_intent_table'] = tableExists($sifDb, 'redsys_payment_intent');
    $checks['redsys_notifications_table'] = tableExists($sifDb, 'redsys_notifications');
    $checks['redsys_callback_queue_table'] = tableExists($sifDb, 'redsys_callback_queue');
    $checks['fiscal_chain_state_seeded'] = rowExists(
        $sifDb,
        'SELECT COUNT(*) FROM fiscal_chain_state WHERE ID = 1'
    );
} catch (\Throwable $exception) {
    $errors['sif_database'] = $exception->getMessage();
}

try {
    $legacyDb = ConnectionFactory::makeLegacy($config);
    $checks['legacy_database_connectivity'] = true;
    $checks['legacy_inscripcions_table'] = tableExists($legacyDb, 'inscripcions');
    $checks['legacy_curs_table'] = tableExists($legacyDb, 'curs');
} catch (\Throwable $exception) {
    $errors['legacy_database'] = $exception->getMessage();
}

$failed = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));
$result = [
    'ok' => count($failed) === 0,
    'environment' => $env,
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
