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
$config = require $baseDir . '/config/sif.php';
$environment = (string) ($config['env'] ?? 'local');
$giftRoles = normalizeRoles((array) (($config['gift_redemption'] ?? [])['manage_roles'] ?? []));
$internalApi = (array) ($config['internal_api'] ?? []);

$checks = [
    'environment_is_test_or_preproduction' => in_array(
        $environment,
        ['test', 'preproduction'],
        true
    ),
    'sif_database_configured' => trim((string) (($config['db'] ?? [])['dsn'] ?? '')) !== '',
    'legacy_database_configured' => trim((string) (($config['legacy_db'] ?? [])['dsn'] ?? '')) !== '',
    'internal_api_key_configured' => trim((string) ($internalApi['key_id'] ?? '')) !== '',
    'internal_api_secret_strong' => strlen((string) ($internalApi['secret'] ?? '')) >= 32,
    'gift_manage_roles_configured' => $giftRoles !== [],
    'gift_redeem_signed_path_configured' => trim(
        (string) ($internalApi['gift_redemption_signed_path'] ?? '')
    ) !== '',
    'gift_notification_signed_path_configured' => trim(
        (string) ($internalApi['gift_redemption_notification_signed_path'] ?? '')
    ) !== '',
    'gift_redemption_circuit_present' => allFilesPresent($baseDir, [
        'src/Repository/CommercialEntitlementRepository.php',
        'src/Repository/EnrollmentFundMovementRepository.php',
        'src/Service/GiftEntitlementIssuerService.php',
        'src/Service/GiftRedemptionTrustedContextResolver.php',
        'src/Service/GiftEnrollmentStager.php',
        'src/Service/GiftRedemptionService.php',
        'src/Service/GiftRedemptionOrchestrator.php',
        'src/Service/LegacyGiftUsageReconciler.php',
        'public/api/gifts/redemption/redeem.php',
    ]),
    'gift_notification_governance_present' => allFilesPresent($baseDir, [
        'src/Repository/NotificationOutboxRepository.php',
        'src/Service/GiftRedemptionNotificationBundleService.php',
        'src/Service/NotificationOutboxDeliveryService.php',
        'public/api/gifts/redemption/notifications.php',
    ]),
    'gift_concurrency_evidence_present' => allFilesPresent($baseDir, [
        'tests/Integration/GiftRedemptionConcurrencyTest.php',
        'tests/Support/ConcurrentGiftRedemptionWorker.php',
    ]),
    'historical_gift_preflight_present' => is_file(
        $baseDir . '/scripts/preflight-historical-gift-entitlements.php'
    ),
    'sif_database_connectivity' => false,
    'legacy_database_connectivity' => false,
    'commercial_operation_table' => false,
    'commercial_operation_party_table' => false,
    'commercial_entitlement_table' => false,
    'commercial_entitlement_event_table' => false,
    'enrollment_fund_movement_table' => false,
    'payment_transaction_table' => false,
    'notification_outbox_table' => false,
    'notification_delivery_attempt_table' => false,
    'internal_api_request_table' => false,
    'legacy_regal_table' => false,
    'legacy_inscripcions_table' => false,
    'historical_unused_gifts_covered' => false,
];
$errors = [];
$giftInventorySummary = null;
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
        'payment_transaction',
        'notification_outbox',
        'notification_delivery_attempt',
        'internal_api_request',
    ] as $table) {
        $checks[$table . '_table'] = tableExists($sifDb, $table);
    }
} catch (\Throwable $exception) {
    $errors['sif_database'] = $exception->getMessage();
}

try {
    $legacyDb = ConnectionFactory::makeLegacy($config);
    $checks['legacy_database_connectivity'] = true;
    $checks['legacy_regal_table'] = tableExists($legacyDb, 'regal');
    $checks['legacy_inscripcions_table'] = tableExists($legacyDb, 'inscripcions');
} catch (\Throwable $exception) {
    $errors['legacy_database'] = $exception->getMessage();
}

if ($sifDb instanceof \PDO
    && $legacyDb instanceof \PDO
    && $checks['commercial_entitlement_table']
    && $checks['payment_transaction_table']
    && $checks['legacy_regal_table']
) {
    try {
        $entitlements = new CommercialEntitlementRepository(new UuidGenerator());
        $inventory = (new HistoricalGiftEntitlementBackfillService(
            $entitlements,
            new GiftEntitlementIssuerService(new UuidGenerator(), $entitlements)
        ))->inventory($sifDb, $legacyDb);
        $giftInventorySummary = (array) ($inventory['summary'] ?? []);
        $checks['historical_unused_gifts_covered'] =
            (int) ($giftInventorySummary['blocking_unused'] ?? -1) === 0;
    } catch (\Throwable $exception) {
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
    'scope' => 'uc-018-gift-redemption-preflight',
    'production_authorized' => false,
    'read_only' => true,
    'go_no_go_decision' => $decision,
    'environment' => $environment,
    'checks' => $checks,
];

if ($giftInventorySummary !== null) {
    $result['historical_gift_inventory_summary'] = $giftInventorySummary;
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
    $normalized = [];
    foreach ($roles as $role) {
        $role = strtoupper(trim((string) $role));
        if ($role !== '') {
            $normalized[$role] = true;
        }
    }

    $roles = array_keys($normalized);
    sort($roles, SORT_STRING);

    return $roles;
}

function allFilesPresent(string $baseDir, array $paths): bool
{
    foreach ($paths as $path) {
        if (!is_file($baseDir . '/' . $path)) {
            return false;
        }
    }

    return true;
}

function tableExists(\PDO $db, string $table): bool
{
    $statement = $db->prepare(
        'SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $statement->execute([$table]);

    return $statement->fetchColumn() !== false;
}
