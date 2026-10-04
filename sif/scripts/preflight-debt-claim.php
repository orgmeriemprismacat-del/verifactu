<?php
declare(strict_types=1);

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
    'sif_database_connectivity' => false,
    'factura_table' => false,
    'payment_transaction_table' => false,
    'payment_allocation_table' => false,
    'debt_claim_case_table' => false,
    'debt_claim_event_table' => false,
    'notification_outbox_table' => false,
    'operational_event_table' => false,
];

try {
    $db = ConnectionFactory::make($config);
    $checks['sif_database_connectivity'] = true;
    foreach (array_keys($checks) as $key) {
        if (!str_ends_with($key, '_table')) {
            continue;
        }
        $table = substr($key, 0, -6);
        $checks[$key] = tableExists($db, $table);
    }
} catch (\Throwable $e) {
    $error = $e->getMessage();
}

$failed = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));
$result = [
    'ok' => $failed === [],
    'environment' => $env,
    'checks' => $checks,
    'failed' => $failed,
];
if (isset($error)) {
    $result['error'] = $error;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit($failed === [] ? 0 : 1);

function tableExists(\PDO $db, string $table): bool
{
    $stmt = $db->query('SHOW TABLES LIKE ' . $db->quote($table));
    return $stmt !== false && $stmt->fetchColumn() !== false;
}
