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
    'sif_database_connectivity' => false,
    'sif_invoice_before_payment_coverage_table' => false,
    'legacy_web_database_connectivity' => false,
    'legacy_web_inscripcions_table' => false,
    'legacy_web_curs_table' => false,
    'legacy_intranet_database_connectivity' => false,
    'legacy_intranet_entitats_table' => false,
    'legacy_intranet_entitats_resp_table' => false,
];
$errors = [];

try {
    $sifDb = ConnectionFactory::make($config);
    $checks['sif_database_connectivity'] = true;
    $checks['sif_invoice_before_payment_coverage_table'] = tableExists(
        $sifDb,
        'invoice_before_payment_coverage'
    );
} catch (\Throwable $exception) {
    $errors['sif_database'] = $exception->getMessage();
}

try {
    $legacyWebDb = ConnectionFactory::makeLegacy($config);
    $checks['legacy_web_database_connectivity'] = true;
    $checks['legacy_web_inscripcions_table'] = tableExists($legacyWebDb, 'inscripcions');
    $checks['legacy_web_curs_table'] = tableExists($legacyWebDb, 'curs');
} catch (\Throwable $exception) {
    $errors['legacy_web_database'] = $exception->getMessage();
}

try {
    $legacyIntranetDb = ConnectionFactory::makeLegacyIntranet($config);
    $checks['legacy_intranet_database_connectivity'] = true;
    $checks['legacy_intranet_entitats_table'] = tableExists($legacyIntranetDb, 'entitats');
    $checks['legacy_intranet_entitats_resp_table'] = tableExists($legacyIntranetDb, 'entitats_resp');
} catch (\Throwable $exception) {
    $errors['legacy_intranet_database'] = $exception->getMessage();
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
