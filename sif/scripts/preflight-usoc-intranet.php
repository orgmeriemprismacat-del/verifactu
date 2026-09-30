<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

$checks = [
    'sif_database' => false,
    'usoc_financing_case_table' => false,
    'usoc_validation_decision_table' => false,
    'internal_api_key_id' => false,
    'internal_api_secret' => false,
    'internal_api_usoc_signed_path' => false,
    'usoc_read_or_manage_roles' => false,
    'usoc_manage_roles' => false,
    'legacy_database_configured' => false,
];

try {
    $db = ConnectionFactory::make($config);
    $checks['sif_database'] = true;
    $checks['usoc_financing_case_table'] = tableExists($db, 'usoc_financing_case');
    $checks['usoc_validation_decision_table'] = tableExists($db, 'usoc_validation_decision');
} catch (Throwable $exception) {
    $databaseError = $exception->getMessage();
}

$internalApi = $config['internal_api'] ?? [];
$checks['internal_api_key_id'] = trim((string) ($internalApi['key_id'] ?? '')) !== '';
$checks['internal_api_secret'] = trim((string) ($internalApi['secret'] ?? '')) !== '';
$checks['internal_api_usoc_signed_path'] =
    trim((string) ($internalApi['usoc_signed_path'] ?? '')) === '/api/usoc/manage.php';

$usoc = $config['usoc'] ?? [];
$readRoles = normalizedRoles((array) ($usoc['read_roles'] ?? []));
$manageRoles = normalizedRoles((array) ($usoc['manage_roles'] ?? []));
$checks['usoc_read_or_manage_roles'] = $readRoles !== [] || $manageRoles !== [];
$checks['usoc_manage_roles'] = $manageRoles !== [];

$legacy = $config['legacy_db'] ?? [];
$checks['legacy_database_configured'] =
    trim((string) ($legacy['dsn'] ?? '')) !== ''
    && trim((string) ($legacy['user'] ?? '')) !== '';

$ok = !in_array(false, $checks, true);

echo json_encode([
    'ok' => $ok,
    'checks' => $checks,
    'database_error' => $databaseError ?? null,
    'required_intranet_env' => [
        'SIF_INTERNAL_USOC_URL',
        'SIF_INTERNAL_USOC_SIGNED_PATH',
        'SIF_INTERNAL_API_KEY_ID',
        'SIF_INTERNAL_API_SECRET',
        'SIF_USOC_MENU_ROLES',
        'SIF_USOC_UI_ENABLED',
    ],
    'required_sif_env' => [
        'SIF_USOC_READ_ROLES',
        'SIF_USOC_MANAGE_ROLES',
        'SIF_INTERNAL_USOC_SIGNED_PATH',
        'SIF_INTERNAL_API_KEY_ID',
        'SIF_INTERNAL_API_SECRET',
        'SIF_LEGACY_DB_DSN',
        'SIF_LEGACY_DB_USER',
        'SIF_LEGACY_DB_PASSWORD',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;

exit($ok ? 0 : 1);

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
    return array_values(array_filter(array_map(
        static fn (mixed $role): string => strtoupper(trim((string) $role)),
        $roles
    )));
}
