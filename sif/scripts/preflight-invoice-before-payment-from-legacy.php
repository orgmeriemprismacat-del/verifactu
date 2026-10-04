<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
$env = (string) ($config['env'] ?? 'local');
$qualified = in_array(
    strtoupper(trim($env)),
    ['PREPROD', 'PREPRODUCTION', 'PROD', 'PRODUCTION'],
    true
);

$checks = [
    'environment_not_production' => $env !== 'production',
    'sif_database_connectivity' => false,
    'sif_invoice_before_payment_coverage_table' => false,
    'sif_enrollment_payment_flow_lock_table' => false,
    'sif_enrollment_fund_movement_table' => false,
    'legacy_web_database_connectivity' => false,
    'legacy_web_inscripcions_table' => false,
    'legacy_web_curs_table' => false,
    'legacy_intranet_database_connectivity' => false,
    'legacy_intranet_entitats_table' => false,
    'legacy_intranet_entitats_resp_table' => false,
    'official_aeat_snapshot_config' => !$qualified,
];
$errors = [];

try {
    $sifDb = ConnectionFactory::make($config);
    $checks['sif_database_connectivity'] = true;
    $checks['sif_invoice_before_payment_coverage_table'] = tableExists(
        $sifDb,
        'invoice_before_payment_coverage'
    );
    $checks['sif_enrollment_payment_flow_lock_table'] = tableExists(
        $sifDb,
        'enrollment_payment_flow_lock'
    );
    $checks['sif_enrollment_fund_movement_table'] = tableExists(
        $sifDb,
        'enrollment_fund_movement'
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

if ($qualified) {
    $issuer = (array) ($config['issuer'] ?? []);
    $aeat = (array) ($config['aeat'] ?? []);
    $issuerNif = strtoupper(trim((string) ($issuer['nif'] ?? '')));
    $aeatIssuerNif = strtoupper(trim((string) ($aeat['issuer_nif'] ?? '')));

    $checks['official_aeat_snapshot_config'] =
        trim((string) ($issuer['name'] ?? '')) !== ''
        && $issuerNif !== ''
        && $aeatIssuerNif !== ''
        && hash_equals($issuerNif, $aeatIssuerNif)
        && trim((string) ($aeat['system_name'] ?? '')) !== ''
        && preg_match('/^[A-Za-z0-9]{1,2}$/D', trim((string) ($aeat['system_id'] ?? ''))) === 1
        && trim((string) ($aeat['system_version'] ?? '')) !== ''
        && trim((string) ($aeat['installation_id'] ?? '')) !== '';
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
