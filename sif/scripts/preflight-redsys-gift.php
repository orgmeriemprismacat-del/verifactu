<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
$env = (string) ($config['env'] ?? 'local');
$root = dirname(__DIR__);
$bridgeMerchantCode = trim((string) getenv('REDSYS_MERCHANT_CODE'));
$bridgeMerchantKey = trim((string) getenv('REDSYS_MERCHANT_KEY'));
$bridgeTerminal = trim((string) getenv('REDSYS_TERMINAL'));
$gatewayUrl = trim((string) getenv('REDSYS_GATEWAY_URL'));
$callbackUrl = trim((string) getenv('SIF_REDSYS_CALLBACK_URL'));
$internalBase = trim((string) getenv('SIF_INTERNAL_API_BASE_URL'));
$internalKeyId = trim((string) getenv('SIF_INTERNAL_API_KEY_ID'));
$internalSecret = trim((string) getenv('SIF_INTERNAL_API_SECRET'));
$giftCutoverEnabled = filter_var(
    getenv('SIF_REDSYS_GIFT_CUTOVER_ENABLED') ?: '0',
    FILTER_VALIDATE_BOOLEAN
);
$legacyDrainConfirmed = filter_var(
    getenv('SIF_REDSYS_GIFT_LEGACY_DRAIN_CONFIRMED') ?: '0',
    FILTER_VALIDATE_BOOLEAN
);
$checks = [
    'environment_is_test_or_preproduction' => in_array($env, ['test', 'preproduction', 'preprod'], true),
    'bridge_redsys_merchant_code_configured' => $bridgeMerchantCode !== '',
    'sif_redsys_merchant_code_configured' => trim((string) ($config['redsys']['merchant_code'] ?? '')) !== '',
    'bridge_and_sif_redsys_merchant_codes_match' => $bridgeMerchantCode !== ''
        && hash_equals($bridgeMerchantCode, trim((string) ($config['redsys']['merchant_code'] ?? ''))),
    'bridge_redsys_merchant_key_configured' => $bridgeMerchantKey !== '',
    'bridge_redsys_terminal_configured' => preg_match('/^[0-9]{1,3}$/D', $bridgeTerminal) === 1,
    'redsys_gateway_url_https_configured' => str_starts_with($gatewayUrl, 'https://'),
    'redsys_callback_url_https_configured' => str_starts_with($callbackUrl, 'https://'),
    'internal_api_base_url_https_configured' => str_starts_with($internalBase, 'https://'),
    'internal_api_key_id_configured' => $internalKeyId !== '',
    'internal_api_secret_configured' => $internalSecret !== '',
    'gift_intent_signed_path_matches_bridge' => (string) (
        $config['internal_api']['redsys_gift_intent_signed_path'] ?? ''
    ) === '/api/redsys/gift-intent.php',
    'gift_status_signed_path_matches_bridge' => (string) (
        $config['internal_api']['redsys_gift_status_signed_path'] ?? ''
    ) === '/api/redsys/gift-status.php',
    'cutover_configuration_consistent' => !$legacyDrainConfirmed || $giftCutoverEnabled,
    'legacy_drain_confirmed_if_cutover' => !$giftCutoverEnabled || $legacyDrainConfirmed,
    'environment_not_production' => $env !== 'production',
    'redsys_merchant_key_configured' => (string) ($config['redsys']['merchant_key'] ?? '') !== '',
    'aeat_issuer_name_present' => trim((string) ($config['issuer']['name'] ?? '')) !== '',
    'aeat_issuer_nif_present' => trim((string) ($config['issuer']['nif'] ?? '')) !== '',
    'aeat_issuer_nif_matches_registration_config' =>
        trim((string) ($config['issuer']['nif'] ?? '')) !== ''
        && hash_equals(
            trim((string) ($config['issuer']['nif'] ?? '')),
            trim((string) ($config['aeat']['issuer_nif'] ?? ''))
        ),
    'aeat_system_name_present' => trim((string) ($config['aeat']['system_name'] ?? '')) !== '',
    'aeat_system_id_valid' => preg_match(
        '/^[A-Za-z0-9]{1,2}$/D',
        trim((string) ($config['aeat']['system_id'] ?? ''))
    ) === 1,
    'aeat_system_version_present' => trim((string) ($config['aeat']['system_version'] ?? '')) !== '',
    'aeat_installation_id_present' => trim((string) ($config['aeat']['installation_id'] ?? '')) !== '',
    'aeat_producer_name_present' => trim((string) ($config['aeat']['producer_name'] ?? '')) !== '',
    'aeat_producer_nif_present' => trim((string) ($config['aeat']['producer_nif'] ?? '')) !== '',
    'aeat_gift_tax_code_valid' => preg_match(
        '/^[0-9]{2}$/D',
        trim((string) ($config['aeat']['gift_tax_code'] ?? ''))
    ) === 1,
    'aeat_gift_regime_key_valid' => preg_match(
        '/^[0-9]{2}$/D',
        trim((string) ($config['aeat']['gift_regime_key'] ?? ''))
    ) === 1,
    'aeat_gift_exemption_code_valid' => in_array(
        strtoupper(trim((string) ($config['aeat']['gift_exemption_code'] ?? ''))),
        ['E1', 'E2', 'E3', 'E4', 'E5', 'E6'],
        true
    ),
    'legacy_db_configured' => (string) ($config['legacy_db']['dsn'] ?? '') !== '',
    'sif_database_connectivity' => false,
    'legacy_database_connectivity' => false,
    'factura_table' => false,
    'factura_linia_table' => false,
    'fact_rels_table' => false,
    'payment_transaction_table' => false,
    'payment_allocation_table' => false,
    'notification_outbox_table' => false,
    'redsys_notifications_table' => false,
    'redsys_payment_intent_table' => false,
    'redsys_callback_queue_table' => false,
    'commercial_operation_table' => false,
    'commercial_entitlement_table' => false,
    'commercial_entitlement_event_table' => false,
    'callback_endpoint_present' => is_file($root . '/public/api/redsys/callback.php'),
    'gift_intent_endpoint_present' => is_file($root . '/public/api/redsys/gift-intent.php'),
    'gift_status_endpoint_present' => is_file($root . '/public/api/redsys/gift-status.php'),
    'worker_script_present' => is_file($root . '/scripts/process-redsys-callback-queue.php'),
    'gift_intent_service_present' => is_file($root . '/src/Service/RedsysGiftPaymentIntentService.php'),
    'gift_entitlement_service_present' => is_file($root . '/src/Service/GiftEntitlementIssuerService.php'),
    'gift_aeat_enricher_present' => is_file($root . '/src/Service/GiftAeatInvoicePayloadEnricher.php'),
    'fiscal_chain_state_seeded' => false,
    'fiscal_chain_official_compatible' => false,
    'legacy_regal_table' => false,
    'legacy_regal_observacions_column' => false,
    'legacy_gift_codes_unique' => false,
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
    $checks['notification_outbox_table'] = tableExists($sifDb, 'notification_outbox');
    $checks['redsys_notifications_table'] = tableExists($sifDb, 'redsys_notifications');
    $checks['redsys_payment_intent_table'] = tableExists($sifDb, 'redsys_payment_intent');
    $checks['redsys_callback_queue_table'] = tableExists($sifDb, 'redsys_callback_queue');
    $checks['commercial_operation_table'] = tableExists($sifDb, 'commercial_operation');
    $checks['commercial_entitlement_table'] = tableExists($sifDb, 'commercial_entitlement');
    $checks['commercial_entitlement_event_table'] = tableExists($sifDb, 'commercial_entitlement_event');
    $checks['fiscal_chain_state_seeded'] = rowExists(
        $sifDb,
        'SELECT COUNT(*) FROM fiscal_chain_state WHERE ID = 1'
    );
    $checks['fiscal_chain_official_compatible'] = officialChainCompatible($sifDb);
} catch (\Throwable $exception) {
    $errors['sif_database'] = $exception->getMessage();
}

try {
    $legacyDb = ConnectionFactory::makeLegacy($config);
    $checks['legacy_database_connectivity'] = true;
    $checks['legacy_regal_table'] = tableExists($legacyDb, 'regal');
    if ($checks['legacy_regal_table']) {
        $checks['legacy_regal_observacions_column'] = columnExists(
            $legacyDb,
            'regal',
            'OBSERVACIONS'
        );
        $checks['legacy_gift_codes_unique'] = duplicateGiftCodeCount($legacyDb) === 0;
    }
} catch (\Throwable $exception) {
    $errors['legacy_database'] = $exception->getMessage();
}

$failed = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));
$result = [
    'ok' => count($failed) === 0,
    'environment' => $env,
    'cutover_enabled' => $giftCutoverEnabled,
    'legacy_drain_confirmed' => $legacyDrainConfirmed,
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
    $stmt = $db->query(
        'SHOW COLUMNS FROM ' . $table . ' LIKE ' . $db->quote($column)
    );

    return $stmt !== false && $stmt->fetchColumn() !== false;
}

function duplicateGiftCodeCount(\PDO $db): int
{
    $stmt = $db->query(
        "SELECT COUNT(*)
         FROM (
             SELECT CODI
             FROM regal
             WHERE CODI IS NOT NULL AND TRIM(CODI) <> ''
             GROUP BY CODI
             HAVING COUNT(*) > 1
         ) AS duplicated_gift_codes"
    );

    return $stmt === false ? -1 : (int) $stmt->fetchColumn();
}


function officialChainCompatible(\PDO $db): bool
{
    $state = $db->query(
        'SELECT LAST_FISCAL_ORDER FROM fiscal_chain_state WHERE ID = 1'
    );
    if ($state === false) {
        return false;
    }

    $lastOrder = (int) $state->fetchColumn();
    if ($lastOrder === 0) {
        return true;
    }

    $stmt = $db->prepare(
        'SELECT PAYLOAD_JSON FROM factura_registres WHERE FISCAL_ORDER = ? LIMIT 1'
    );
    $stmt->execute([$lastOrder]);
    $json = $stmt->fetchColumn();
    if (!is_string($json) || trim($json) === '') {
        return false;
    }

    $payload = json_decode($json, true);

    return is_array($payload)
        && isset($payload['aeat'])
        && is_array($payload['aeat'])
        && ($payload['aeat']['type'] ?? '') === 'RegistroAlta';
}
