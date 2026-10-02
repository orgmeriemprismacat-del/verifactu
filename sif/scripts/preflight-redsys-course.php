<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
$env = (string) ($config['env'] ?? 'local');
$internalApi = (array) ($config['internal_api'] ?? []);
$callbackUrl = trim((string) getenv('SIF_REDSYS_CALLBACK_URL'));
$gatewayUrl = trim((string) getenv('REDSYS_GATEWAY_URL'));
$internalApiBaseUrl = rtrim(trim((string) getenv('SIF_INTERNAL_API_BASE_URL')), '/');
$merchantCode = trim((string) getenv('REDSYS_MERCHANT_CODE'));
$bridgeMerchantKey = trim((string) getenv('REDSYS_MERCHANT_KEY'));
$sifMerchantKey = trim((string) ($config['redsys']['merchant_key'] ?? ''));
$terminal = trim((string) getenv('REDSYS_TERMINAL'));
$courseCutoverEnabled = filter_var(
    getenv('SIF_REDSYS_COURSE_CUTOVER_ENABLED') ?: '0',
    FILTER_VALIDATE_BOOLEAN
);
$legacyDrainConfirmed = filter_var(
    getenv('SIF_REDSYS_COURSE_LEGACY_DRAIN_CONFIRMED') ?: '0',
    FILTER_VALIDATE_BOOLEAN
);
$courseIntentPath = trim((string) ($internalApi['redsys_course_intent_signed_path'] ?? ''));
$courseStatusPath = trim((string) ($internalApi['redsys_course_status_signed_path'] ?? ''));

$checks = [
    'environment_is_test_or_preproduction' => in_array($env, ['test', 'preproduction'], true),
    'redsys_merchant_key_configured' => $sifMerchantKey !== '',
    'bridge_redsys_merchant_code_configured' => $merchantCode !== '',
    'bridge_redsys_merchant_key_configured' => $bridgeMerchantKey !== '',
    'bridge_redsys_terminal_configured' => $terminal !== '',
    'bridge_and_sif_redsys_keys_match' => $bridgeMerchantKey !== ''
        && $sifMerchantKey !== ''
        && hash_equals(hash('sha256', $sifMerchantKey), hash('sha256', $bridgeMerchantKey)),
    'internal_api_base_url_https_configured' => $internalApiBaseUrl !== ''
        && str_starts_with($internalApiBaseUrl, 'https://'),
    'internal_api_key_id_configured' => trim((string) ($internalApi['key_id'] ?? '')) !== '',
    'internal_api_secret_configured' => trim((string) ($internalApi['secret'] ?? '')) !== '',
    'course_intent_signed_path_matches_bridge' => $courseIntentPath === '/api/redsys/course-intent.php',
    'course_status_signed_path_matches_bridge' => $courseStatusPath === '/api/redsys/course-status.php',
    'redsys_callback_url_https_configured' => $callbackUrl !== '' && str_starts_with($callbackUrl, 'https://'),
    'redsys_gateway_url_https_configured' => $gatewayUrl !== '' && str_starts_with($gatewayUrl, 'https://'),
    'cutover_configuration_consistent' => !$courseCutoverEnabled
        || ($callbackUrl !== '' && str_starts_with($callbackUrl, 'https://')),
    'legacy_drain_confirmed_if_cutover' => !$courseCutoverEnabled || $legacyDrainConfirmed,
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
