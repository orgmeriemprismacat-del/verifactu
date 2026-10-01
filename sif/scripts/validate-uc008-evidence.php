<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$args = $argv;
array_shift($args);

if (count($args) !== 2) {
    fwrite(STDERR, "Usage: php sif/scripts/validate-uc008-evidence.php <preproduction.json> <menu.json>\n");
    exit(2);
}

[$preproductionPath, $menuPath] = $args;

$result = [
    'ok' => false,
    'scope' => 'uc-008-evidence-validation',
    'production_authorized' => false,
    'checks' => [],
];

$preproduction = readJson($preproductionPath, 'preproduction');
$menu = readJson($menuPath, 'menu');

$result['checks']['preproduction_json_valid'] = $preproduction !== null;
$result['checks']['menu_json_valid'] = $menu !== null;

if ($preproduction !== null) {
    $result['checks']['preproduction_ok'] = ($preproduction['ok'] ?? false) === true;
    $result['checks']['preproduction_preflight_ok'] =
        ($preproduction['checks']['preflight_ok'] ?? false) === true;
    $result['checks']['preproduction_e2e_ok'] =
        ($preproduction['checks']['e2e_ok'] ?? false) === true;
    $result['checks']['preproduction_scope_valid'] =
        ($preproduction['scope'] ?? '') === 'uc-008-preproduction-verification';
    $result['checks']['preproduction_environment_valid'] =
        ($preproduction['environment'] ?? '') === 'preproduction';
    $result['checks']['preproduction_does_not_authorize_production'] =
        ($preproduction['production_authorized'] ?? null) === false;
    $result['checks']['preproduction_no_secrets'] = !containsForbiddenKey($preproduction);
} else {
    $result['checks']['preproduction_ok'] = false;
    $result['checks']['preproduction_preflight_ok'] = false;
    $result['checks']['preproduction_e2e_ok'] = false;
    $result['checks']['preproduction_scope_valid'] = false;
    $result['checks']['preproduction_environment_valid'] = false;
    $result['checks']['preproduction_does_not_authorize_production'] = false;
    $result['checks']['preproduction_no_secrets'] = false;
}

if ($menu !== null) {
    $existingCount = (int) ($menu['existing_target_count'] ?? -1);
    $status = (string) ($menu['status'] ?? '');

    $result['checks']['menu_ok'] = ($menu['ok'] ?? false) === true;
    $result['checks']['menu_scope_valid'] =
        ($menu['scope'] ?? '') === 'uc-008-intranet-menu-discovery';
    $result['checks']['menu_read_only'] = ($menu['read_only'] ?? false) === true;
    $result['checks']['menu_target_url_valid'] =
        ($menu['target_url'] ?? '') === '/sif-verifactu.php';
    $result['checks']['menu_unique_target'] = $existingCount === 1;
    $result['checks']['menu_already_present'] = $status === 'ALREADY_PRESENT';
    $result['checks']['menu_no_secrets'] = !containsForbiddenKey($menu);
} else {
    $result['checks']['menu_ok'] = false;
    $result['checks']['menu_scope_valid'] = false;
    $result['checks']['menu_read_only'] = false;
    $result['checks']['menu_target_url_valid'] = false;
    $result['checks']['menu_unique_target'] = false;
    $result['checks']['menu_already_present'] = false;
    $result['checks']['menu_no_secrets'] = false;
}

$failed = array_keys(array_filter(
    $result['checks'],
    static fn ($value): bool => $value !== true
));

$result['ok'] = $failed === [];
if ($failed !== []) {
    $result['failed'] = $failed;
}

echo json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
), PHP_EOL;

exit($result['ok'] ? 0 : 1);

function readJson(string $path, string $label): ?array
{
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $raw = file_get_contents($path);
    if (!is_string($raw)) {
        return null;
    }

    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }

    return is_array($decoded) ? $decoded : null;
}

function containsForbiddenKey(array $value): bool
{
    foreach ($value as $key => $child) {
        $normalized = strtolower((string) $key);
        if (
            str_contains($normalized, 'secret')
            || str_contains($normalized, 'password')
            || str_contains($normalized, 'signature')
            || str_contains($normalized, 'merchant_key')
            || str_contains($normalized, 'certificate_password')
        ) {
            return true;
        }

        if (is_array($child) && containsForbiddenKey($child)) {
            return true;
        }
    }

    return false;
}
