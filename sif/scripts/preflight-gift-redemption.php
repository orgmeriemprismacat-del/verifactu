<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\CommercialEntitlementRepository;
use Prisma\Sif\Service\GiftEntitlementIssuerService;
use Prisma\Sif\Service\HistoricalGiftEntitlementBackfillService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$baseDir = dirname(__DIR__);
$repoRoot = dirname($baseDir);
$config = require $baseDir . '/config/sif.php';
$internalApi = (array) ($config['internal_api'] ?? []);
$giftRoles = normalizeRoles(
    (array) (($config['gift_redemption'] ?? [])['manage_roles'] ?? [])
);

$checks = [
    'internal_api_key_id_configured' =>
        trim((string) ($internalApi['key_id'] ?? '')) !== '',
    'internal_api_secret_strong' =>
        strlen((string) ($internalApi['secret'] ?? '')) >= 32,
    'internal_api_clock_skew_valid' =>
        (int) ($internalApi['max_clock_skew_seconds'] ?? 0) > 0
        && (int) ($internalApi['max_clock_skew_seconds'] ?? 0) <= 900,
    'gift_redemption_signed_path_exact' =>
        (string) ($internalApi['gift_redemption_signed_path'] ?? '')
        === '/api/gifts/redemption/redeem.php',
    'gift_redemption_manage_roles_configured' => $giftRoles !== [],
    'gift_redemption_endpoint_present' =>
        is_file($baseDir . '/public/api/gifts/redemption/redeem.php'),
    'gift_redemption_orchestrator_present' =>
        is_file($baseDir . '/src/Service/GiftRedemptionOrchestrator.php'),
    'gift_redemption_recovery_cli_present' =>
        is_file($baseDir . '/scripts/retry-gift-redemption.php'),
    'historical_gift_preflight_present' =>
        is_file($baseDir . '/scripts/preflight-historical-gift-entitlements.php'),
    'historical_gift_inventory_present' =>
        is_file($baseDir . '/scripts/inventory-historical-gift-entitlements.php'),
    'historical_gift_processor_present' =>
        is_file($baseDir . '/scripts/process-historical-gift-entitlement-backfill.php'),
    'web_client_present' =>
        is_file($repoRoot . '/codi-drive/web-actual/inc/SifGiftRedemptionClient.php'),
    'legacy_writer_present' =>
        is_file($repoRoot . '/codi-drive/web-actual/ajax/enviarInscripcioBescanvia.php'),
    'sif_database_connectivity' => false,
    'legacy_database_connectivity' => false,
    'commercial_operation_table' => false,
    'commercial_operation_party_table' => false,
    'commercial_entitlement_table' => false,
    'commercial_entitlement_event_table' => false,
    'enrollment_fund_movement_table' => false,
    'internal_api_request_table' => false,
    'factura_table' => false,
    'fact_rels_table' => false,
    'payment_transaction_table' => false,
    'payment_allocation_table' => false,
    'legacy_regal_table' => false,
    'legacy_inscripcions_table' => false,
    'historical_unused_gifts_covered' => false,
];

$errors = [];
$inventorySummary = null;
$sifDb = null;
$legacyDb = null;

try {
    $sifDb = ConnectionFactory::make($config);
    $checks['sif_database_connectivity'] = true;

    foreach ([
        'commercial_operation',
        'commercial_operation_party',
        'commercial_entitlement',
        'commercial_entitlement_event',
        'enrollment_fund_movement',
        'internal_api_request',
        'factura',
        'fact_rels',
        'payment_transaction',
        'payment_allocation',
    ] as $table) {
        $checks[$table . '_table'] = tableExists($sifDb, $table);
    }
} catch (Throwable $exception) {
    $errors['sif_database'] = $exception->getMessage();
}

try {
    $legacyDb = ConnectionFactory::makeLegacy($config);
    $checks['legacy_database_connectivity'] = true;
    $checks['legacy_regal_table'] = tableExists($legacyDb, 'regal');
    $checks['legacy_inscripcions_table'] = tableExists($legacyDb, 'inscripcions');
} catch (Throwable $exception) {
    $errors['legacy_database'] = $exception->getMessage();
}

if ($sifDb instanceof PDO
    && $legacyDb instanceof PDO
    && $checks['commercial_entitlement_table']
    && $checks['factura_table']
    && $checks['fact_rels_table']
    && $checks['payment_transaction_table']
    && $checks['payment_allocation_table']
    && $checks['legacy_regal_table']
) {
    try {
        $entitlements = new CommercialEntitlementRepository(new UuidGenerator());
        $inventory = (new HistoricalGiftEntitlementBackfillService(
            $entitlements,
            new GiftEntitlementIssuerService(new UuidGenerator(), $entitlements)
        ))->inventory($sifDb, $legacyDb);

        $inventorySummary = (array) ($inventory['summary'] ?? []);
        $checks['historical_unused_gifts_covered'] =
            (int) ($inventorySummary['blocking_unused'] ?? -1) === 0;
    } catch (Throwable $exception) {
        $errors['historical_gift_inventory'] = $exception->getMessage();
    }
}

$failed = array_keys(array_filter(
    $checks,
    static fn (bool $ok): bool => !$ok
));
$decision = $failed === [] ? 'GO' : 'NO-GO';

$result = [
    'ok' => $decision === 'GO',
    'scope' => 'uc018_gift_redemption_preflight',
    'production_authorized' => false,
    'go_no_go_decision' => $decision,
    'environment' => (string) ($config['env'] ?? 'local'),
    'checks' => $checks,
];

if (is_array($inventorySummary)) {
    $result['historical_gift_inventory_summary'] = $inventorySummary;
}
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
exit($decision === 'GO' ? 0 : 1);

function normalizeRoles(array $roles): array
{
    $result = [];
    foreach ($roles as $role) {
        $role = strtoupper(trim((string) $role));
        if ($role !== '') {
            $result[$role] = true;
        }
    }

    $roles = array_keys($result);
    sort($roles, SORT_STRING);

    return $roles;
}

function tableExists(PDO $db, string $table): bool
{
    $statement = $db->prepare(
        'SELECT TABLE_NAME
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $statement->execute([$table]);

    return $statement->fetchColumn() !== false;
}
