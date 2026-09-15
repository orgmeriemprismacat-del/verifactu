<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;

$baseDir = dirname(__DIR__);
$config = require $baseDir . '/config/sif.php';
$checks = [
    'environment_not_production' => ($config['env'] ?? 'local') !== 'production',
    'openssl_extension' => extension_loaded('openssl'),
    'pdo_mysql_extension' => extension_loaded('pdo_mysql'),
    'redsys_key_configured' => (string) ($config['redsys']['merchant_key'] ?? '') !== '',
    'worker_script_present' => is_file($baseDir . '/scripts/process-redsys-callback-queue.php'),
    'database_connectivity' => false,
    'redsys_payment_intent' => false,
    'redsys_notifications' => false,
    'redsys_callback_queue' => false,
    'normalized_notification_columns' => false,
];
$errors = [];

try {
    $db = ConnectionFactory::make($config);
    $checks['database_connectivity'] = true;
    foreach (['redsys_payment_intent', 'redsys_notifications', 'redsys_callback_queue'] as $table) {
        $stmt = $db->query('SHOW TABLES LIKE ' . $db->quote($table));
        $checks[$table] = $stmt !== false && $stmt->fetchColumn() !== false;
    }

    $stmt = $db->query("SHOW COLUMNS FROM redsys_notifications WHERE Field IN ('CURRENCY_CODE', 'TERMINAL', 'SIGNATURE_VERSION', 'PAYLOAD_HASH')");
    $checks['normalized_notification_columns'] = $stmt !== false && count($stmt->fetchAll()) === 4;
} catch (Throwable $exception) {
    $errors['database'] = $exception->getMessage();
}

$failed = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));
$result = ['ok' => $failed === [], 'environment' => (string) ($config['env'] ?? 'local'), 'checks' => $checks];
if ($failed !== []) {
    $result['failed'] = $failed;
}
if ($errors !== []) {
    $result['errors'] = $errors;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit($result['ok'] ? 0 : 1);
