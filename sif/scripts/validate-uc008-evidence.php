<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$args = $argv;
array_shift($args);

if (count($args) !== 3) {
    fwrite(STDERR, "Usage: php sif/scripts/validate-uc008-evidence.php <preproduction.json> <menu.json> <manager-e2e.json>\n");
    exit(2);
}

[$preproductionPath, $menuPath, $managerPath] = $args;

$result = [
    'ok' => false,
    'scope' => 'uc-008-evidence-validation',
    'production_authorized' => false,
    'validated_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'inputs' => [
        'preproduction_sha256' => fileSha256($preproductionPath),
        'menu_sha256' => fileSha256($menuPath),
        'manager_e2e_sha256' => fileSha256($managerPath),
    ],
    'checks' => [],
];

$preproduction = readJson($preproductionPath, 'preproduction');
$menu = readJson($menuPath, 'menu');
$manager = readJson($managerPath, 'manager-e2e');

$result['checks']['preproduction_json_valid'] = $preproduction !== null;
$result['checks']['menu_json_valid'] = $menu !== null;
$result['checks']['manager_e2e_json_valid'] = $manager !== null;
$result['checks']['preproduction_sha256_valid'] = isSha256($result['inputs']['preproduction_sha256']);
$result['checks']['menu_sha256_valid'] = isSha256($result['inputs']['menu_sha256']);
$result['checks']['manager_e2e_sha256_valid'] = isSha256($result['inputs']['manager_e2e_sha256']);

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


if ($manager !== null) {
    $managerChecks = is_array($manager['checks'] ?? null) ? $manager['checks'] : [];

    $result['checks']['manager_e2e_ok'] = ($manager['ok'] ?? false) === true;
    $result['checks']['manager_e2e_scope_valid'] =
        ($manager['scope'] ?? '') === 'uc-008-manager-e2e-evidence';
    $result['checks']['manager_e2e_environment_valid'] =
        ($manager['environment'] ?? '') === 'preproduction';
    $result['checks']['manager_e2e_read_only'] = ($manager['read_only'] ?? false) === true;
    $result['checks']['manager_e2e_does_not_authorize_production'] =
        ($manager['production_authorized'] ?? null) === false;
    $result['checks']['manager_e2e_manage_roles_configured'] =
        ($managerChecks['manage_roles_configured'] ?? false) === true;
    $result['checks']['manager_e2e_incident_exists'] =
        ($managerChecks['incident_exists'] ?? false) === true;
    $result['checks']['manager_e2e_synthetic_incident'] =
        ($managerChecks['synthetic_manager_incident'] ?? false) === true;
    $result['checks']['manager_e2e_incident_resolved'] =
        ($managerChecks['incident_resolved'] ?? false) === true;
    $result['checks']['manager_e2e_assignee_present'] =
        ($managerChecks['assignee_present'] ?? false) === true;
    $result['checks']['manager_e2e_resolved_at_present'] =
        ($managerChecks['resolved_at_present'] ?? false) === true;
    $result['checks']['manager_e2e_resolution_notes_present'] =
        ($managerChecks['resolution_notes_present'] ?? false) === true;
    $result['checks']['manager_e2e_closure_criteria_present'] =
        ($managerChecks['closure_criteria_present'] ?? false) === true;
    $result['checks']['manager_e2e_assign_present'] =
        ($managerChecks['assign_present'] ?? false) === true;
    $result['checks']['manager_e2e_assign_manager_role'] =
        ($managerChecks['assign_manager_role'] ?? false) === true;
    $result['checks']['manager_e2e_assign_actor_present'] =
        ($managerChecks['assign_actor_present'] ?? false) === true;
    $result['checks']['manager_e2e_assign_correlation_matches'] =
        ($managerChecks['assign_correlation_matches'] ?? false) === true;
    $result['checks']['manager_e2e_add_evidence_present'] =
        ($managerChecks['add_evidence_present'] ?? false) === true;
    $result['checks']['manager_e2e_add_evidence_manager_role'] =
        ($managerChecks['add_evidence_manager_role'] ?? false) === true;
    $result['checks']['manager_e2e_add_evidence_actor_present'] =
        ($managerChecks['add_evidence_actor_present'] ?? false) === true;
    $result['checks']['manager_e2e_add_evidence_correlation_matches'] =
        ($managerChecks['add_evidence_correlation_matches'] ?? false) === true;
    $result['checks']['manager_e2e_add_evidence_payload_present'] =
        ($managerChecks['add_evidence_payload_present'] ?? false) === true;
    $result['checks']['manager_e2e_resolve_present'] =
        ($managerChecks['resolve_present'] ?? false) === true;
    $result['checks']['manager_e2e_resolve_manager_role'] =
        ($managerChecks['resolve_manager_role'] ?? false) === true;
    $result['checks']['manager_e2e_resolve_actor_present'] =
        ($managerChecks['resolve_actor_present'] ?? false) === true;
    $result['checks']['manager_e2e_resolve_correlation_matches'] =
        ($managerChecks['resolve_correlation_matches'] ?? false) === true;
    $result['checks']['manager_e2e_resolve_evidence_payload_present'] =
        ($managerChecks['resolve_evidence_payload_present'] ?? false) === true;
    $result['checks']['manager_e2e_database_query_ok'] =
        ($managerChecks['database_query_ok'] ?? false) === true;
    $result['checks']['manager_e2e_no_secrets'] = !containsForbiddenKey($manager);
} else {
    foreach ([
        'manager_e2e_ok',
        'manager_e2e_scope_valid',
        'manager_e2e_environment_valid',
        'manager_e2e_read_only',
        'manager_e2e_does_not_authorize_production',
        'manager_e2e_manage_roles_configured',
        'manager_e2e_incident_exists',
        'manager_e2e_synthetic_incident',
        'manager_e2e_incident_resolved',
        'manager_e2e_assignee_present',
        'manager_e2e_resolved_at_present',
        'manager_e2e_resolution_notes_present',
        'manager_e2e_closure_criteria_present',
        'manager_e2e_assign_present',
        'manager_e2e_assign_manager_role',
        'manager_e2e_assign_actor_present',
        'manager_e2e_assign_correlation_matches',
        'manager_e2e_add_evidence_present',
        'manager_e2e_add_evidence_manager_role',
        'manager_e2e_add_evidence_actor_present',
        'manager_e2e_add_evidence_correlation_matches',
        'manager_e2e_add_evidence_payload_present',
        'manager_e2e_resolve_present',
        'manager_e2e_resolve_manager_role',
        'manager_e2e_resolve_actor_present',
        'manager_e2e_resolve_correlation_matches',
        'manager_e2e_resolve_evidence_payload_present',
        'manager_e2e_database_query_ok',
        'manager_e2e_no_secrets',
    ] as $check) {
        $result['checks'][$check] = false;
    }
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


function fileSha256(string $path): ?string
{
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $hash = hash_file('sha256', $path);
    return is_string($hash) && preg_match('/^[a-f0-9]{64}$/D', $hash) === 1
        ? $hash
        : null;
}


function isSha256(mixed $value): bool
{
    return is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) === 1;
}
