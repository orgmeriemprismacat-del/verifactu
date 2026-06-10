<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$baseDir = dirname(__DIR__);
$config = require $baseDir . '/config/sif.php';
$env = (string) ($config['env'] ?? 'local');
$checks = [
    'environment_not_production' => $env !== 'production',
    'core_preflight_script_present' => is_file($baseDir . '/scripts/preflight-sif.php'),
    'tests_runner_present' => is_file($baseDir . '/tests/run-tests.php'),
    'migrations_runner_present' => is_file($baseDir . '/scripts/run-migrations.php'),
    'sif_database_configured' => (string) ($config['db']['dsn'] ?? '') !== '',
    'legacy_database_configured' => (string) ($config['legacy_db']['dsn'] ?? '') !== '',
    'redsys_merchant_key_configured' => (string) ($config['redsys']['merchant_key'] ?? '') !== '',
    'sif_database_connectivity' => false,
    'legacy_database_connectivity' => false,
    'factura_table' => false,
    'factura_linia_table' => false,
    'factura_registres_table' => false,
    'fiscal_queue_table' => false,
    'payment_transaction_table' => false,
    'payment_allocation_table' => false,
    'redsys_notifications_table' => false,
    'factura_documents_table' => false,
    'errors_verifactu_table' => false,
    'fact_rels_table' => false,
    'fiscal_chain_state_seeded' => false,
    'legacy_inscripcions_table' => false,
    'legacy_curs_table' => false,
    'legacy_regal_table' => false,
    'redsys_course_circuit_present' => allFilesPresent($baseDir, [
        'src/Service/RedsysCourseInvoiceService.php',
        'scripts/preflight-redsys-course.php',
        'scripts/preview-redsys-course.php',
        'scripts/process-redsys-course.php',
    ]),
    'manual_payment_circuit_present' => allFilesPresent($baseDir, [
        'src/Repository/ManualPaymentInvoiceRepository.php',
        'src/Service/ManualPaymentService.php',
        'scripts/preflight-manual-payment.php',
        'scripts/preview-manual-payment.php',
        'scripts/process-manual-payment.php',
    ]),
    'manual_course_circuit_present' => allFilesPresent($baseDir, [
        'src/Service/ManualCourseInvoiceService.php',
        'scripts/preflight-manual-course.php',
        'scripts/preview-manual-course.php',
        'scripts/process-manual-course.php',
    ]),
    'redsys_pack_circuit_present' => allFilesPresent($baseDir, [
        'src/Service/RedsysPackInvoiceService.php',
        'scripts/preflight-redsys-pack.php',
        'scripts/preview-redsys-pack.php',
        'scripts/process-redsys-pack.php',
    ]),
    'manual_pack_circuit_present' => allFilesPresent($baseDir, [
        'src/Service/ManualPackInvoiceService.php',
        'scripts/preflight-manual-pack.php',
        'scripts/preview-manual-pack.php',
        'scripts/process-manual-pack.php',
    ]),
    'manual_gift_circuit_present' => allFilesPresent($baseDir, [
        'src/Service/ManualGiftInvoiceService.php',
        'scripts/preflight-manual-gift.php',
        'scripts/preview-manual-gift.php',
        'scripts/process-manual-gift.php',
    ]),
    'redsys_gift_circuit_present' => allFilesPresent($baseDir, [
        'src/Service/RedsysGiftInvoiceService.php',
        'scripts/preflight-redsys-gift.php',
        'scripts/preview-redsys-gift.php',
        'scripts/process-redsys-gift.php',
    ]),
    'legacy_sync_present' => allFilesPresent($baseDir, [
        'src/Repository/LegacySyncRepository.php',
        'src/Service/LegacySyncService.php',
    ]),
    'documents_incidents_present' => allFilesPresent($baseDir, [
        'src/Repository/DocumentRepository.php',
        'src/Repository/IncidentRepository.php',
    ]),
];
$errors = [];

try {
    $sifDb = ConnectionFactory::make($config);
    $checks['sif_database_connectivity'] = true;

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
        'fact_rels',
    ] as $table) {
        $checks[$table . '_table'] = tableExists($sifDb, $table);
    }

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
    $checks['legacy_regal_table'] = tableExists($legacyDb, 'regal');
} catch (\Throwable $exception) {
    $errors['legacy_database'] = $exception->getMessage();
}

$failed = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));
$decision = count($failed) === 0 ? 'GO' : 'NO-GO';
$result = [
    'ok' => $decision === 'GO',
    'go_no_go_decision' => $decision,
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
exit($decision === 'GO' ? 0 : 1);

function allFilesPresent(string $baseDir, array $relativePaths): bool
{
    foreach ($relativePaths as $path) {
        if (!is_file($baseDir . '/' . $path)) {
            return false;
        }
    }

    return true;
}

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
