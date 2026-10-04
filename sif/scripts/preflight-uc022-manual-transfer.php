<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Service\GeneratedInvoiceLegacyPaymentSyncService;
use Prisma\Sif\Service\ManualTransferCommandService;
use Prisma\Sif\Service\ManualTransferLegacyProjectionService;
use Prisma\Sif\Service\ManualTransferNotificationService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
$environment = strtolower(trim((string) ($config['env'] ?? 'local')));

$checks = [
    'environment_is_test_or_preproduction' => in_array($environment, ['test', 'preproduction'], true),
    'sif_database_connectivity' => false,
    'factura_table' => false,
    'payment_transaction_table' => false,
    'payment_allocation_table' => false,
    'payment_action_event_table' => false,
    'operational_event_table' => false,
    'sif_audit_event_table' => false,
    'internal_api_request_table' => false,
    'notification_outbox_table' => false,
    'internal_api_key_id' => false,
    'internal_api_secret' => false,
    'manual_transfer_signed_path' => false,
    'manual_transfer_roles' => false,
    'legacy_database_configured' => false,
    'legacy_database_connectivity' => false,
    'legacy_factures_table' => false,
    'legacy_inscripcions_table' => false,
    'legacy_intranet_database_configured' => false,
    'legacy_intranet_database_connectivity' => false,
    'legacy_entitats_table' => false,
    'legacy_entitats_resp_table' => false,
    'manual_transfer_endpoint_file' => false,
    'manual_transfer_command_service' => false,
    'manual_transfer_projection_service' => false,
    'manual_transfer_sync_service' => false,
    'manual_transfer_notification_service' => false,
];

$errors = [];

try {
    $sifDb = ConnectionFactory::make($config);
    $checks['sif_database_connectivity'] = (int) $sifDb->query('SELECT 1')->fetchColumn() === 1;

    foreach ([
        'factura',
        'payment_transaction',
        'payment_allocation',
        'payment_action_event',
        'operational_event',
        'sif_audit_event',
        'internal_api_request',
        'notification_outbox',
    ] as $table) {
        $checks[$table . '_table'] = tableExists($sifDb, $table);
    }
} catch (Throwable $exception) {
    $errors['sif_database'] = $exception->getMessage();
}

$internalApi = $config['internal_api'] ?? [];
$checks['internal_api_key_id'] = trim((string) ($internalApi['key_id'] ?? '')) !== '';
$checks['internal_api_secret'] = trim((string) ($internalApi['secret'] ?? '')) !== '';
$checks['manual_transfer_signed_path'] =
    trim((string) ($internalApi['manual_transfer_signed_path'] ?? ''))
        === '/api/payments/manual-transfer.php';

$roles = array_values(array_filter(array_map(
    static fn (mixed $role): string => strtoupper(trim((string) $role)),
    (array) (($config['payments']['manual_transfer_roles'] ?? []))
)));
$checks['manual_transfer_roles'] = $roles !== [];

$legacy = $config['legacy_db'] ?? [];
$checks['legacy_database_configured'] = trim((string) ($legacy['dsn'] ?? '')) !== '';
if ($checks['legacy_database_configured']) {
    try {
        $legacyDb = ConnectionFactory::makeLegacy($config);
        $checks['legacy_database_connectivity'] =
            (int) $legacyDb->query('SELECT 1')->fetchColumn() === 1;
        $checks['legacy_factures_table'] = tableExists($legacyDb, 'factures');
        $checks['legacy_inscripcions_table'] = tableExists($legacyDb, 'inscripcions');
    } catch (Throwable $exception) {
        $errors['legacy_database'] = $exception->getMessage();
    }
}

$legacyIntranet = $config['legacy_intranet_db'] ?? [];
$checks['legacy_intranet_database_configured'] =
    trim((string) ($legacyIntranet['dsn'] ?? '')) !== '';
if ($checks['legacy_intranet_database_configured']) {
    try {
        $legacyIntranetDb = ConnectionFactory::makeLegacyIntranet($config);
        $checks['legacy_intranet_database_connectivity'] =
            (int) $legacyIntranetDb->query('SELECT 1')->fetchColumn() === 1;
        $checks['legacy_entitats_table'] = tableExists($legacyIntranetDb, 'entitats');
        $checks['legacy_entitats_resp_table'] = tableExists($legacyIntranetDb, 'entitats_resp');
    } catch (Throwable $exception) {
        $errors['legacy_intranet_database'] = $exception->getMessage();
    }
}

$baseDir = dirname(__DIR__);
$checks['manual_transfer_endpoint_file'] =
    is_file($baseDir . '/public/api/payments/manual-transfer.php');
$checks['manual_transfer_command_service'] = class_exists(ManualTransferCommandService::class);
$checks['manual_transfer_projection_service'] = class_exists(ManualTransferLegacyProjectionService::class);
$checks['manual_transfer_sync_service'] = class_exists(GeneratedInvoiceLegacyPaymentSyncService::class);
$checks['manual_transfer_notification_service'] = class_exists(ManualTransferNotificationService::class);

$failed = array_keys(array_filter(
    $checks,
    static fn (bool $ok): bool => !$ok
));

$result = [
    'ok' => $failed === [],
    'scope' => 'uc-022-manual-transfer-preflight',
    'environment' => $environment,
    'executed_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'php_version' => PHP_VERSION,
    'checks' => $checks,
    'required_intranet_env' => [
        'SIF_INTERNAL_API_BASE_URL',
        'SIF_INTERNAL_API_KEY_ID',
        'SIF_INTERNAL_API_SECRET',
        'SIF_INTERNAL_MANUAL_TRANSFER_SIGNED_PATH',
        'SIF_MANUAL_TRANSFER_ROLES',
    ],
    'required_sif_env' => [
        'SIF_INTERNAL_API_KEY_ID',
        'SIF_INTERNAL_API_SECRET',
        'SIF_INTERNAL_MANUAL_TRANSFER_SIGNED_PATH',
        'SIF_MANUAL_TRANSFER_ROLES',
        'SIF_LEGACY_DB_DSN',
        'SIF_LEGACY_DB_USER',
        'SIF_LEGACY_DB_PASSWORD',
        'SIF_LEGACY_INTRANET_DB_DSN',
        'SIF_LEGACY_INTRANET_DB_USER',
        'SIF_LEGACY_INTRANET_DB_PASSWORD',
    ],
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

exit($result['ok'] ? 0 : 1);

function tableExists(PDO $db, string $table): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?'
    );
    $stmt->execute([$table]);

    return (int) $stmt->fetchColumn() === 1;
}
