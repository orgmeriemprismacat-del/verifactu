<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\CommercialEntitlementRepository;
use Prisma\Sif\Service\GiftEntitlementIssuerService;
use Prisma\Sif\Service\HistoricalGiftEntitlementBackfillService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script is CLI-only.\n");
    exit(2);
}

$baseDir = dirname(__DIR__);
$config = require $baseDir . '/config/sif.php';
$internalApi = (array) ($config['internal_api'] ?? []);
$roles = array_values(array_filter(array_map(
    static fn (mixed $role): string => strtoupper(trim((string) $role)),
    (array) (($config['gift_redemption'] ?? [])['manage_roles'] ?? [])
)));

$checks = [
    'internal_api_key_id_configured' => trim((string) ($internalApi['key_id'] ?? '')) !== '',
    'internal_api_secret_strong' => strlen((string) ($internalApi['secret'] ?? '')) >= 32,
    'gift_redemption_signed_path_exact' =>
        (string) ($internalApi['gift_redemption_signed_path'] ?? '')
        === '/api/gifts/redemption/redeem.php',
    'gift_redemption_manage_roles_configured' => $roles !== [],
    'gift_redemption_endpoint_present' =>
        is_file($baseDir . '/public/api/gifts/redemption/redeem.php'),
    'gift_redemption_recovery_cli_present' =>
        is_file($baseDir . '/scripts/retry-gift-redemption.php'),
    'historical_gift_preview_present' =>
        is_file($baseDir . '/scripts/preview-historical-gift-entitlements.php'),
    'historical_gift_backfill_present' =>
        is_file($baseDir . '/scripts/process-historical-gift-entitlement.php'),
    'sif_database_connectivity' => false,
    'legacy_database_connectivity' => false,
    'commercial_operation_table' => false,
    'commercial_entitlement_table' => false,
    'commercial_entitlement_event_table' => false,
    'enrollment_fund_movement_table' => false,
    'internal_api_request_table' => false,
    'fact_rels_table' => false,
    'payment_transaction_table' => false,
    'payment_allocation_table' => false,
    'legacy_regal_table' => false,
    'legacy_inscripcions_table' => false,
    'historical_paid_unused_gifts_covered' => false,
    'historical_gift_blockers_absent' => false,
];

$errors = [];
$inventory = null;
$sifDb = null;
$legacyDb = null;

try {
    $sifDb = ConnectionFactory::make($config);
    $checks['sif_database_connectivity'] = true;

    foreach ([
        'commercial_operation',
        'commercial_entitlement',
        'commercial_entitlement_event',
        'enrollment_fund_movement',
        'internal_api_request',
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

if ($sifDb instanceof PDO && $legacyDb instanceof PDO
    && $checks['commercial_operation_table']
    && $checks['commercial_entitlement_table']
    && $checks['commercial_entitlement_event_table']
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

        $counts = (array) ($inventory['counts'] ?? []);
        $checks['historical_paid_unused_gifts_covered'] =
            (int) ($counts['ready_backfill'] ?? -1) === 0;
        $checks['historical_gift_blockers_absent'] =
            (int) ($counts['blocked'] ?? -1) === 0;
    } catch (Throwable $exception) {
        $errors['historical_gift_inventory'] = $exception->getMessage();
    }
}

$failed = array_keys(array_filter(
    $checks,
    static fn (bool $ok): bool => !$ok
));

$result = [
    'ok' => $failed === [],
    'scope' => 'uc018_gift_redemption_preflight',
    'production_authorized' => false,
    'environment' => (string) ($config['env'] ?? 'local'),
    'checks' => $checks,
];

if (is_array($inventory)) {
    $result['historical_inventory'] = [
        'counts' => $inventory['counts'] ?? [],
        'items' => $inventory['items'] ?? [],
    ];
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

exit($failed === [] ? 0 : 1);

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
