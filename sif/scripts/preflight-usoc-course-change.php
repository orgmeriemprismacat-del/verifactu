<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Service\UsocCourseChangeDestinationBindingService;
use Prisma\Sif\Service\UsocCourseChangeExecutionPreparationService;
use Prisma\Sif\Service\UsocCourseChangeExecutionService;
use Prisma\Sif\Service\UsocCourseChangeFundPlanService;
use Prisma\Sif\Service\UsocCourseChangePreviewService;
use Prisma\Sif\Service\UsocCourseChangeTargetResolver;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
$env = (string) ($config['env'] ?? 'local');
$repoRoot = dirname(__DIR__, 2);

$checks = [
    'environment_not_production' => $env !== 'production',
    'sif_database_connectivity' => false,
    'usoc_financing_case_table' => false,
    'usoc_lifecycle_execution_table' => false,
    'usoc_lifecycle_execution_allows_course_change' => false,
    'enrollment_fund_movement_table' => false,
    'factura_table' => false,
    'factura_rectificacio_table' => false,
    'payment_transaction_table' => false,
    'payment_allocation_table' => false,
    'operational_event_table' => false,
    'fiscal_chain_state_seeded' => false,
    'legacy_web_database_configured' => false,
    'legacy_web_database_connectivity' => false,
    'legacy_inscripcions_table' => false,
    'legacy_curs_table' => false,
    'legacy_jornades_table' => false,
    'legacy_preu_table' => false,
    'legacy_descomptes_table' => false,
    'legacy_intranet_database_configured' => false,
    'legacy_intranet_database_connectivity' => false,
    'legacy_params_table' => false,
    'internal_api_key_id' => false,
    'internal_api_secret' => false,
    'internal_api_usoc_signed_path' => false,
    'usoc_manage_roles' => false,
    'usoc_entity_billing_name' => false,
    'usoc_entity_billing_nif' => false,
    'target_resolver_service' => false,
    'fund_plan_service' => false,
    'preview_service' => false,
    'preparation_service' => false,
    'destination_binding_service' => false,
    'execution_service' => false,
    'usoc_api_endpoint_file' => false,
    'intranet_preview_endpoint_file' => false,
    'intranet_prepare_endpoint_file' => false,
    'intranet_course_change_endpoint_file' => false,
    'intranet_usoc_client_file' => false,
    'intranet_pricing_resolver_file' => false,
    'intranet_destination_reservation_file' => false,
    'intranet_course_change_ui_file' => false,
];
$errors = [];

try {
    $db = ConnectionFactory::make($config);
    $checks['sif_database_connectivity'] = true;

    foreach ([
        'usoc_financing_case',
        'usoc_lifecycle_execution',
        'enrollment_fund_movement',
        'factura',
        'factura_rectificacio',
        'payment_transaction',
        'payment_allocation',
        'operational_event',
    ] as $table) {
        $checks[$table . '_table'] = tableExists($db, $table);
    }

    if ($checks['usoc_lifecycle_execution_table']) {
        $checks['usoc_lifecycle_execution_allows_course_change'] =
            columnDefinitionContains(
                $db,
                'usoc_lifecycle_execution',
                'OPERATION',
                'COURSE_CHANGE'
            );
    }

    $checks['fiscal_chain_state_seeded'] = rowExists(
        $db,
        'SELECT COUNT(*) FROM fiscal_chain_state WHERE ID = 1'
    );
} catch (Throwable $exception) {
    $errors['sif_database'] = $exception->getMessage();
}

$legacy = $config['legacy_db'] ?? [];
$checks['legacy_web_database_configured'] =
    trim((string) ($legacy['dsn'] ?? '')) !== '';

if ($checks['legacy_web_database_configured']) {
    try {
        $legacyDb = ConnectionFactory::makeLegacy($config);
        $checks['legacy_web_database_connectivity'] =
            (int) $legacyDb->query('SELECT 1')->fetchColumn() === 1;
        foreach (['inscripcions', 'curs', 'jornades', 'preu', 'descomptes'] as $table) {
            $checks['legacy_' . $table . '_table'] = tableExists($legacyDb, $table);
        }
    } catch (Throwable $exception) {
        $errors['legacy_web_database'] = $exception->getMessage();
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
        $checks['legacy_params_table'] = tableExists($legacyIntranetDb, 'params');
    } catch (Throwable $exception) {
        $errors['legacy_intranet_database'] = $exception->getMessage();
    }
}

$internalApi = $config['internal_api'] ?? [];
$checks['internal_api_key_id'] =
    trim((string) ($internalApi['key_id'] ?? '')) !== '';
$checks['internal_api_secret'] =
    trim((string) ($internalApi['secret'] ?? '')) !== '';
$checks['internal_api_usoc_signed_path'] =
    trim((string) ($internalApi['usoc_signed_path'] ?? '')) === '/api/usoc/manage.php';

$usoc = $config['usoc'] ?? [];
$manageRoles = normalizedRoles((array) ($usoc['manage_roles'] ?? []));
$checks['usoc_manage_roles'] = $manageRoles !== [];
$billing = is_array($usoc['entity_billing'] ?? null)
    ? $usoc['entity_billing']
    : [];
$checks['usoc_entity_billing_name'] =
    trim((string) ($billing['name'] ?? '')) !== '';
$checks['usoc_entity_billing_nif'] =
    trim((string) ($billing['nif'] ?? '')) !== '';

$checks['target_resolver_service'] =
    class_exists(UsocCourseChangeTargetResolver::class);
$checks['fund_plan_service'] =
    class_exists(UsocCourseChangeFundPlanService::class);
$checks['preview_service'] =
    class_exists(UsocCourseChangePreviewService::class);
$checks['preparation_service'] =
    class_exists(UsocCourseChangeExecutionPreparationService::class);
$checks['destination_binding_service'] =
    class_exists(UsocCourseChangeDestinationBindingService::class);
$checks['execution_service'] =
    class_exists(UsocCourseChangeExecutionService::class);

$files = [
    'usoc_api_endpoint_file' => 'sif/public/api/usoc/manage.php',
    'intranet_preview_endpoint_file' =>
        'codi-drive/intranet-actual/ajax/alumnes/sifUsocCourseChangePreview.php',
    'intranet_prepare_endpoint_file' =>
        'codi-drive/intranet-actual/ajax/alumnes/sifUsocCourseChangePrepare.php',
    'intranet_course_change_endpoint_file' =>
        'codi-drive/intranet-actual/ajax/alumnes/realitzarCanviCurs_CanviCurs.php',
    'intranet_usoc_client_file' =>
        'codi-drive/intranet-actual/SifInternalUsocClient.php',
    'intranet_pricing_resolver_file' =>
        'codi-drive/intranet-actual/LegacyUsocCourseChangePricingResolver.php',
    'intranet_destination_reservation_file' =>
        'codi-drive/intranet-actual/LegacyUsocCourseChangeDestinationReservationService.php',
    'intranet_course_change_ui_file' =>
        'codi-drive/intranet-actual/js/alumnes-usoc-lifecycle-preview.js',
];

foreach ($files as $check => $relativePath) {
    $checks[$check] = is_file($repoRoot . '/' . $relativePath);
}

$failed = array_keys(array_filter(
    $checks,
    static fn (bool $ok): bool => !$ok
));

$result = [
    'ok' => $failed === [],
    'environment' => $env,
    'checks' => $checks,
    'failed' => $failed,
    'errors' => $errors,
    'required_intranet_env' => [
        'SIF_INTERNAL_USOC_URL',
        'SIF_INTERNAL_USOC_SIGNED_PATH',
        'SIF_INTERNAL_API_KEY_ID',
        'SIF_INTERNAL_API_SECRET',
        'SIF_USOC_MENU_ROLES',
        'SIF_USOC_UI_ENABLED',
    ],
    'required_sif_env' => [
        'SIF_USOC_MANAGE_ROLES',
        'SIF_USOC_ENTITY_NAME',
        'SIF_USOC_ENTITY_NIF',
        'SIF_INTERNAL_USOC_SIGNED_PATH',
        'SIF_INTERNAL_API_KEY_ID',
        'SIF_INTERNAL_API_SECRET',
        'SIF_LEGACY_DB_DSN',
        'SIF_LEGACY_DB_USER',
        'SIF_LEGACY_DB_PASSWORD',
        'SIF_LEGACY_INTRANET_DB_DSN',
        'SIF_LEGACY_INTRANET_DB_USER',
        'SIF_LEGACY_INTRANET_DB_PASSWORD',
    ],
];

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

function columnDefinitionContains(
    PDO $db,
    string $table,
    string $column,
    string $needle
): bool {
    $stmt = $db->prepare(
        'SELECT COLUMN_TYPE
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?'
    );
    $stmt->execute([$table, $column]);
    $definition = $stmt->fetchColumn();

    return is_string($definition)
        && stripos($definition, $needle) !== false;
}

function rowExists(PDO $db, string $sql): bool
{
    $stmt = $db->query($sql);

    return $stmt !== false && (int) $stmt->fetchColumn() === 1;
}

function normalizedRoles(array $roles): array
{
    return array_values(array_filter(array_map(
        static fn (mixed $role): string => strtoupper(trim((string) $role)),
        $roles
    )));
}
