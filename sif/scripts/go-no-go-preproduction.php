<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\MigrationRunner;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$baseDir = dirname(__DIR__);
$config = require $baseDir . '/config/sif.php';
$env = (string) ($config['env'] ?? 'local');
$checks = [
    'schema_verified' => false,
    'php_pdo_mysql' => extension_loaded('pdo_mysql'),
    'php_openssl' => extension_loaded('openssl'),
    'environment_not_production' => in_array($env, ['test', 'preproduction'], true),
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
    'redsys_payment_intent_table' => false,
    'redsys_callback_queue_table' => false,
    'factura_documents_table' => false,
    'errors_verifactu_table' => false,
    'fact_rels_table' => false,
    'fiscal_chain_state_seeded' => false,
    'legacy_inscripcions_table' => false,
    'legacy_curs_table' => false,
    'legacy_regal_table' => false,
    'legacy_respGrups_table' => false,
    'redsys_course_circuit_present' => allFilesPresent($baseDir, [
        'src/Service/RedsysCourseInvoiceService.php',
        'scripts/preflight-redsys-course.php',
        'scripts/preview-redsys-course.php',
        'scripts/process-redsys-course.php',
    ]),
    'redsys_async_circuit_present' => allFilesPresent($baseDir, [
        'database/migrations/2026_06_19_000003_create_redsys_callback_queue.sql',
        'database/migrations/2026_06_19_000004_harden_redsys_notifications.sql',
        'src/Service/RedsysCallbackWorker.php',
        'src/Service/RedsysCallbackDispatcher.php',
        'scripts/preflight-redsys-callback-queue.php',
        'scripts/process-redsys-callback-queue.php',
    ]),
    'manual_payment_circuit_present' => allFilesPresent($baseDir, [
        'src/Repository/ManualPaymentInvoiceRepository.php',
        'src/Service/ManualPaymentService.php',
        'scripts/preflight-manual-payment.php',
        'scripts/preview-manual-payment.php',
        'scripts/process-manual-payment.php',
    ]),
    'claim_payment_circuit_present' => allFilesPresent($baseDir, [
        'src/Repository/ManualPaymentInvoiceRepository.php',
        'src/Service/ClaimPaymentPayloadBuilder.php',
        'src/Service/ClaimPaymentService.php',
        'scripts/preflight-claim-payment.php',
        'scripts/preview-claim-payment.php',
        'scripts/process-claim-payment.php',
    ]),
    'manual_installment_circuit_present' => allFilesPresent($baseDir, [
        'src/Service/ManualInstallmentPaymentPayloadBuilder.php',
        'src/Service/ManualInstallmentPaymentService.php',
        'scripts/preview-manual-installment.php',
        'scripts/process-manual-installment.php',
    ]),
    'manual_rectification_circuit_present' => allFilesPresent($baseDir, [
        'src/Repository/RectificationRepository.php',
        'src/Service/ManualRectificationPayloadBuilder.php',
        'src/Service/ManualRectificationService.php',
        'scripts/preview-manual-rectification.php',
        'scripts/process-manual-rectification.php',
    ]),
    'manual_refund_circuit_present' => allFilesPresent($baseDir, [
        'src/Service/ManualRefundPayloadBuilder.php',
        'src/Service/ManualRefundService.php',
        'scripts/preview-manual-refund.php',
        'scripts/process-manual-refund.php',
    ]),
    'manual_invoice_circuit_present' => allFilesPresent($baseDir, [
        'src/Service/ManualInvoicePayloadBuilder.php',
        'src/Service/ManualInvoiceService.php',
        'scripts/preview-manual-invoice.php',
        'scripts/process-manual-invoice.php',
    ]),
    'historical_migration_circuit_present' => allFilesPresent($baseDir, [
        'src/Repository/HistoricalInvoiceMigrationRepository.php',
        'src/Service/HistoricalInvoicePayloadBuilder.php',
        'src/Service/HistoricalInvoiceMigrationService.php',
        'scripts/preview-historical-invoice-migration.php',
        'scripts/process-historical-invoice-migration.php',
    ]),
    'manual_course_circuit_present' => allFilesPresent($baseDir, [
        'src/Service/ManualCourseInvoiceService.php',
        'scripts/preflight-manual-course.php',
        'scripts/preview-manual-course.php',
        'scripts/process-manual-course.php',
    ]),
    'credit_balance_circuit_present' => allFilesPresent($baseDir, [
        'src/Repository/CreditBalanceRepository.php',
        'src/Service/CreditBalancePayloadBuilder.php',
        'src/Service/CreditBalanceService.php',
        'scripts/preview-credit-balance.php',
        'scripts/process-credit-balance.php',
        'scripts/preview-credit-compensation.php',
        'scripts/process-credit-compensation.php',
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
    'redsys_group_circuit_present' => allFilesPresent($baseDir, [
        'src/Repository/LegacyGroupSnapshotRepository.php',
        'src/Service/LegacyGroupInvoicePayloadBuilder.php',
        'src/Service/RedsysGroupInvoiceService.php',
        'scripts/preflight-redsys-group.php',
        'scripts/preview-redsys-group.php',
        'scripts/process-redsys-group.php',
    ]),
    'manual_group_circuit_present' => allFilesPresent($baseDir, [
        'src/Repository/LegacyGroupSnapshotRepository.php',
        'src/Service/LegacyGroupInvoicePayloadBuilder.php',
        'src/Service/ManualGroupInvoicePayloadBuilder.php',
        'src/Service/ManualGroupInvoiceService.php',
        'scripts/preflight-manual-group.php',
        'scripts/preview-manual-group.php',
        'scripts/process-manual-group.php',
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
    'redsys_usoc_circuit_present' => allFilesPresent($baseDir, [
        'src/Service/RedsysUsocInvoiceService.php',
        'scripts/preflight-redsys-usoc.php',
        'scripts/preview-redsys-usoc.php',
        'scripts/process-redsys-usoc.php',
    ]),
    'usoc_entity_circuit_present' => allFilesPresent($baseDir, [
        'src/Service/UsocEntityInvoiceService.php',
        'scripts/preflight-usoc-entity.php',
        'scripts/preview-usoc-entity.php',
        'scripts/process-usoc-entity.php',
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
    $schemaChecks = (new MigrationRunner($baseDir . '/database'))->inspect($sifDb);
    $checks = array_merge($checks, $schemaChecks);
    $checks['schema_verified'] = !in_array(false, $schemaChecks, true);

    foreach ([
        'factura',
        'factura_linia',
        'factura_registres',
        'fiscal_queue',
        'payment_transaction',
        'payment_allocation',
        'redsys_notifications',
        'redsys_payment_intent',
        'redsys_callback_queue',
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
    $checks['legacy_respGrups_table'] = tableExists($legacyDb, 'respGrups');
} catch (\Throwable $exception) {
    $errors['legacy_database'] = $exception->getMessage();
}

$failed = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));
$decision = count($failed) === 0 ? 'GO' : 'NO-GO';
$result = [
    'ok' => $decision === 'GO',
    'scope' => 'technical_preflight_only',
    'production_authorized' => false,
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
    $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);

    return $stmt !== false && $stmt->fetchColumn() !== false;
}

function rowExists(\PDO $db, string $sql): bool
{
    $stmt = $db->query($sql);

    return $stmt !== false && (int) $stmt->fetchColumn() === 1;
}



