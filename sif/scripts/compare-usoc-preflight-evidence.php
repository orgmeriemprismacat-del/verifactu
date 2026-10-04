<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

if (($argc ?? 0) !== 3) {
    fwrite(
        STDERR,
        "Usage: php compare-usoc-preflight-evidence.php <sif.json> <intranet.json>\n"
    );
    exit(2);
}

try {
    $sif = readEvidence((string) $argv[1]);
    $intranet = readEvidence((string) $argv[2]);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(2);
}

$sifSafe = is_array($sif['safe_config'] ?? null) ? $sif['safe_config'] : [];
$intranetSafe = is_array($intranet['safe_config'] ?? null)
    ? $intranet['safe_config']
    : [];

$sifKeyId = trim((string) ($sifSafe['internal_api_key_id'] ?? ''));
$intranetKeyId = trim((string) ($intranetSafe['key_id'] ?? ''));
$sifPath = trim((string) ($sifSafe['internal_api_usoc_signed_path'] ?? ''));
$intranetPath = trim((string) ($intranetSafe['signed_path'] ?? ''));

$manageRoles = normalizedRoles((array) ($sifSafe['usoc_manage_roles'] ?? []));
$menuRoles = normalizedRoles((array) ($intranetSafe['menu_roles'] ?? []));
$rolesNotManaged = array_values(array_diff($menuRoles, $manageRoles));

$checks = [
    'sif_preflight_ok' =>
        ($sif['ok'] ?? false) === true
        && (string) ($sif['scope'] ?? '') === 'sif_course_change',
    'intranet_preflight_ok' =>
        ($intranet['ok'] ?? false) === true
        && (string) ($intranet['scope'] ?? '') === 'intranet_runtime',
    'key_id_matches' =>
        $sifKeyId !== ''
        && $intranetKeyId !== ''
        && hash_equals($sifKeyId, $intranetKeyId),
    'signed_path_matches' =>
        $sifPath !== ''
        && $intranetPath !== ''
        && hash_equals($sifPath, $intranetPath),
    'menu_roles_are_manage_roles' =>
        $menuRoles !== []
        && $manageRoles !== []
        && $rolesNotManaged === [],
    'intranet_ui_enabled' =>
        ($intranetSafe['ui_enabled'] ?? false) === true,
];

$failed = array_keys(array_filter(
    $checks,
    static fn (bool $ok): bool => !$ok
));

$result = [
    'ok' => $failed === [],
    'scope' => 'cross_host_compatibility',
    'checks' => $checks,
    'failed' => $failed,
    'safe_comparison' => [
        'key_id' => $sifKeyId !== '' && $sifKeyId === $intranetKeyId
            ? $sifKeyId
            : null,
        'signed_path' => $sifPath !== '' && $sifPath === $intranetPath
            ? $sifPath
            : null,
        'sif_manage_roles' => $manageRoles,
        'intranet_menu_roles' => $menuRoles,
        'roles_not_managed_by_sif' => $rolesNotManaged,
    ],
];

echo json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
), PHP_EOL;

exit($result['ok'] ? 0 : 1);

function readEvidence(string $path): array
{
    if ($path === '' || !is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Preflight evidence file is not readable: ' . $path);
    }

    $json = file_get_contents($path);
    if ($json === false) {
        throw new RuntimeException('Could not read preflight evidence: ' . $path);
    }

    $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid preflight evidence object: ' . $path);
    }

    return $decoded;
}

function normalizedRoles(array $roles): array
{
    $normalized = array_values(array_unique(array_filter(array_map(
        static fn (mixed $role): string => strtoupper(trim((string) $role)),
        $roles
    ))));
    sort($normalized);

    return $normalized;
}
