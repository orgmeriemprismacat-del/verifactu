<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Service\NovicePromotionGrantService;
use Prisma\Sif\Service\NovicePromotionCodePreparationService;
use Prisma\Sif\Service\NovicePromotionStudentSummaryService;
use Prisma\Sif\Service\NovicePromotionSecretaryDecisionProjector;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$baseDir = dirname(__DIR__);
$config = require $baseDir . '/config/sif.php';
$env = (string) ($config['env'] ?? 'local');

$novice = (array) ($config['novice_promotion'] ?? []);
$internalApi = (array) ($config['internal_api'] ?? []);
$legacy = (array) ($config['legacy_db'] ?? []);

$wrapKey = trim((string) ($novice['wrapping_key_hex'] ?? ''));
$keyVersion = trim((string) ($novice['key_version'] ?? ''));
$manageRoles = normalizedRoles((array) ($novice['manage_roles'] ?? []));

$checks = [
    'environment_is_test_or_preproduction' => in_array($env, ['test', 'preproduction'], true),
    'php_pdo_mysql' => extension_loaded('pdo_mysql'),
    'php_openssl' => extension_loaded('openssl'),
    'sif_database_connectivity' => false,
    'legacy_database_configured' => trim((string) ($legacy['dsn'] ?? '')) !== '',
    'legacy_database_connectivity' => false,
    'wrapping_key_configured' => $wrapKey !== '',
    'wrapping_key_is_64_hex' => preg_match('/^[a-f0-9]{64}$/iD', $wrapKey) === 1,
    'wrapping_key_version_configured' => $keyVersion !== '',
    'internal_api_key_id_configured' => trim((string) ($internalApi['key_id'] ?? '')) !== '',
    'internal_api_secret_configured' => trim((string) ($internalApi['secret'] ?? '')) !== '',
    'novice_signed_path_correct' =>
        trim((string) ($internalApi['novice_promotion_signed_path'] ?? '')) === '/api/novice-promotion/manage.php',
    'novice_manage_roles_configured' => $manageRoles !== [],
    'api_endpoint_present' => is_file($baseDir . '/public/api/novice-promotion/manage.php'),
    'test_runner_present' => is_file($baseDir . '/tests/run-tests.php'),
    'local_test_runner_present' => is_file($baseDir . '/scripts/test-uc111-local.sh'),
    'grant_service_present' => class_exists(NovicePromotionGrantService::class),
    'code_preparation_service_present' => class_exists(NovicePromotionCodePreparationService::class),
    'student_summary_service_present' => class_exists(NovicePromotionStudentSummaryService::class),
    'secretary_projector_present' => class_exists(NovicePromotionSecretaryDecisionProjector::class),
];

foreach ([
    'commercial_operation',
    'discount_validation',
    'commercial_entitlement',
    'commercial_entitlement_event',
    'novice_promotion_grant',
    'novice_promotion_code_outbox',
    'novice_promotion_application',
] as $table) {
    $checks[$table . '_table'] = false;
}

$errors = [];

try {
    $db = ConnectionFactory::make($config);
    $checks['sif_database_connectivity'] =
        (int) $db->query('SELECT 1')->fetchColumn() === 1;

    foreach ([
        'commercial_operation',
        'discount_validation',
        'commercial_entitlement',
        'commercial_entitlement_event',
        'novice_promotion_grant',
        'novice_promotion_code_outbox',
        'novice_promotion_application',
    ] as $table) {
        $checks[$table . '_table'] = tableExists($db, $table);
    }
} catch (Throwable $exception) {
    $errors['sif_database'] = $exception->getMessage();
}

if ($checks['legacy_database_configured']) {
    try {
        $legacyDb = ConnectionFactory::makeLegacy($config);
        $checks['legacy_database_connectivity'] =
            (int) $legacyDb->query('SELECT 1')->fetchColumn() === 1;
    } catch (Throwable $exception) {
        $errors['legacy_database'] = $exception->getMessage();
    }
}

$failed = array_keys(array_filter(
    $checks,
    static fn (bool $ok): bool => !$ok
));

$result = [
    'ok' => $failed === [],
    'environment' => $env,
    'checks' => $checks,
    'required_intranet_env' => [
        'SIF_NOVICE_PROMOTION_UI_ENABLED',
        'SIF_APP_ROOT',
        'SIF_DB_DSN',
        'SIF_DB_USER',
        'SIF_DB_PASSWORD',
        'SIF_INTERNAL_NOVICE_PROMOTION_URL',
        'SIF_INTERNAL_NOVICE_PROMOTION_SIGNED_PATH',
        'SIF_INTERNAL_API_KEY_ID',
        'SIF_INTERNAL_API_SECRET',
        'INTRANET_ALLOWED_ORIGINS',
    ],
    'required_sif_env' => [
        'SIF_ENV',
        'SIF_DB_DSN',
        'SIF_DB_USER',
        'SIF_DB_PASSWORD',
        'SIF_LEGACY_DB_DSN',
        'SIF_LEGACY_DB_USER',
        'SIF_LEGACY_DB_PASSWORD',
        'SIF_NOVICE_PROMO_WRAP_KEY_HEX',
        'SIF_NOVICE_PROMO_KEY_VERSION',
        'SIF_NOVICE_PROMOTION_MANAGE_ROLES',
        'SIF_INTERNAL_API_KEY_ID',
        'SIF_INTERNAL_API_SECRET',
        'SIF_INTERNAL_NOVICE_PROMOTION_SIGNED_PATH',
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

exit($failed === [] ? 0 : 1);

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

function normalizedRoles(array $roles): array
{
    $normalized = array_values(array_filter(array_map(
        static fn (mixed $role): string => strtoupper(trim((string) $role)),
        $roles
    )));
    sort($normalized, SORT_STRING);

    return array_values(array_unique($normalized));
}
