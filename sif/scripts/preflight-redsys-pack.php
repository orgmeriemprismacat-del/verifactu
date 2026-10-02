<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
$env = (string) ($config['env'] ?? 'local');
$allowProductionWorker = filter_var(
    getenv('SIF_REDSYS_WORKER_ALLOW_PRODUCTION') ?: '0',
    FILTER_VALIDATE_BOOLEAN
);
$callbackUrl = trim((string) (getenv('SIF_REDSYS_CALLBACK_URL') ?: ''));
$intentApiUrl = trim((string) (getenv('SIF_REDSYS_INTENT_API_URL') ?: ''));
$paymentUrl = trim((string) (getenv('SIF_REDSYS_PAYMENT_URL') ?: ''));

$checks = [
    'environment_safe_for_worker' => $env !== 'production' || $allowProductionWorker,
    'redsys_merchant_key_configured' => (string) ($config['redsys']['merchant_key'] ?? '') !== '',
    'internal_api_key_configured' => (string) ($config['internal_api']['key_id'] ?? '') !== '',
    'internal_api_secret_configured' => (string) ($config['internal_api']['secret'] ?? '') !== '',
    'intent_create_roles_configured' => (array) ($config['redsys']['intent_create_roles'] ?? []) !== [],
    'callback_url_secure' => secureUrl($callbackUrl),
    'intent_api_url_secure' => secureUrl($intentApiUrl),
    'redsys_payment_url_allowed' => redsysPaymentUrl($paymentUrl),
    'checkout_actor_roles_configured' => trim((string) (getenv('SIF_REDSYS_INTENT_ACTOR_ROLES') ?: '')) !== '',
    'legacy_db_configured' => (string) ($config['legacy_db']['dsn'] ?? '') !== '',
    'sif_database_connectivity' => false,
    'legacy_database_connectivity' => false,
    'factura_table' => false,
    'factura_linia_table' => false,
    'fact_rels_table' => false,
    'payment_transaction_table' => false,
    'payment_allocation_table' => false,
    'redsys_payment_intent_table' => false,
    'redsys_notifications_table' => false,
    'redsys_callback_queue_table' => false,
    'notification_outbox_table' => false,
    'enrollment_fund_movement_table' => false,
    'callback_endpoint_present' => is_file(dirname(__DIR__) . '/public/api/redsys/callback.php'),
    'intent_create_endpoint_present' => is_file(dirname(__DIR__) . '/public/api/redsys/intents/create.php'),
    'worker_script_present' => is_file(dirname(__DIR__) . '/scripts/process-redsys-callback-queue.php'),
    'preview_script_present' => is_file(dirname(__DIR__) . '/scripts/preview-redsys-pack.php'),
    'process_script_present' => is_file(dirname(__DIR__) . '/scripts/process-redsys-pack.php'),
    'queue_preflight_script_present' => is_file(dirname(__DIR__) . '/scripts/preflight-redsys-callback-queue.php'),
    'verification_script_present' => is_file(dirname(__DIR__) . '/scripts/verify-redsys-pack-preproduction.php'),
    'fiscal_chain_state_seeded' => false,
    'legacy_inscripcions_table' => false,
    'legacy_curs_table' => false,
    'legacy_info_pack_table' => false,
];
$errors = [];

try {
    $sifDb = ConnectionFactory::make($config);
    $checks['sif_database_connectivity'] = true;
    $checks['factura_table'] = tableExists($sifDb, 'factura');
    $checks['factura_linia_table'] = tableExists($sifDb, 'factura_linia');
    $checks['fact_rels_table'] = tableExists($sifDb, 'fact_rels');
    $checks['payment_transaction_table'] = tableExists($sifDb, 'payment_transaction');
    $checks['payment_allocation_table'] = tableExists($sifDb, 'payment_allocation');
    $checks['redsys_payment_intent_table'] = tableExists($sifDb, 'redsys_payment_intent');
    $checks['redsys_notifications_table'] = tableExists($sifDb, 'redsys_notifications');
    $checks['redsys_callback_queue_table'] = tableExists($sifDb, 'redsys_callback_queue');
    $checks['notification_outbox_table'] = tableExists($sifDb, 'notification_outbox');
    $checks['enrollment_fund_movement_table'] = tableExists($sifDb, 'enrollment_fund_movement');
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
    $checks['legacy_info_pack_table'] = tableExists($legacyDb, 'info_pack');
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


function secureUrl(string $url): bool
{
    if ($url === '') {
        return false;
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return false;
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    if ($scheme === 'https') {
        return true;
    }

    $allowLocalHttp = filter_var(
        getenv('SIF_INTERNAL_API_ALLOW_HTTP') ?: '0',
        FILTER_VALIDATE_BOOLEAN
    );

    return $allowLocalHttp
        && $scheme === 'http'
        && in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
}


function redsysPaymentUrl(string $url): bool
{
    return in_array(
        $url,
        [
            'https://sis.redsys.es/sis/realizarPago',
            'https://sis-t.redsys.es:25443/sis/realizarPago',
        ],
        true
    );
}
