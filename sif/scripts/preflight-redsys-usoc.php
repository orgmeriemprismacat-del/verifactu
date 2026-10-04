<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Service\RedsysUsocInvoiceService;
use Prisma\Sif\Service\UsocIntentSnapshotValidator;

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
    'factura_linia_table' => false,
    'fact_rels_table' => false,
    'usoc_financing_case_table' => false,
    'usoc_financing_terms_table' => false,
    'payment_transaction_table' => false,
    'payment_allocation_table' => false,
    'redsys_notifications_table' => false,
    'redsys_payment_intent_table' => false,
    'fiscal_chain_state_seeded' => false,
    'legacy_inscripcions_table' => false,
    'legacy_curs_table' => false,
    'legacy_a_pagar_column' => false,
    'legacy_tipus_desc_column' => false,
    'legacy_valid_desc_column' => false,
    'usoc_intent_snapshot_validator' => false,
    'redsys_usoc_invoice_service' => false,
    'redsys_intent_api_file' => false,
];
$errors = [];

try {
    $sifDb = ConnectionFactory::make($config);
    $checks['sif_database_connectivity'] = true;
    $checks['factura_table'] = tableExists($sifDb, 'factura');
    $checks['factura_linia_table'] = tableExists($sifDb, 'factura_linia');
    $checks['fact_rels_table'] = tableExists($sifDb, 'fact_rels');
    $checks['usoc_financing_case_table'] = tableExists($sifDb, 'usoc_financing_case');
    $checks['usoc_financing_terms_table'] = tableExists($sifDb, 'usoc_financing_terms');
    $checks['payment_transaction_table'] = tableExists($sifDb, 'payment_transaction');
    $checks['payment_allocation_table'] = tableExists($sifDb, 'payment_allocation');
    $checks['redsys_notifications_table'] = tableExists($sifDb, 'redsys_notifications');
    $checks['redsys_payment_intent_table'] = tableExists($sifDb, 'redsys_payment_intent');
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
    $checks['legacy_a_pagar_column'] = columnExists($legacyDb, 'inscripcions', 'A_PAGAR');
    $checks['legacy_tipus_desc_column'] = columnExists($legacyDb, 'inscripcions', 'TIPUS_DESC');
    $checks['legacy_valid_desc_column'] = columnExists($legacyDb, 'inscripcions', 'VALID_DESC');
} catch (\Throwable $exception) {
    $errors['legacy_database'] = $exception->getMessage();
}


$checks['usoc_intent_snapshot_validator'] = class_exists(UsocIntentSnapshotValidator::class);
$checks['redsys_usoc_invoice_service'] = class_exists(RedsysUsocInvoiceService::class);
$checks['redsys_intent_api_file'] =
    is_file(dirname(__DIR__) . '/public/api/redsys/intents/create.php');

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


function columnExists(\PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?'
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() === 1;
}
