<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$root = __DIR__;
$url = trim((string) (getenv('SIF_INTERNAL_USOC_URL') ?: ''));
$signedPath = trim((string) (
    getenv('SIF_INTERNAL_USOC_SIGNED_PATH') ?: '/api/usoc/manage.php'
));
$expectedHost = strtolower(trim((string) (getenv('SIF_INTERNAL_USOC_EXPECTED_HOST') ?: '')));
$keyId = trim((string) (getenv('SIF_INTERNAL_API_KEY_ID') ?: ''));
$secret = trim((string) (getenv('SIF_INTERNAL_API_SECRET') ?: ''));
$menuRoles = array_values(array_filter(array_map(
    static fn (string $role): string => strtoupper(trim($role)),
    explode(',', (string) (getenv('SIF_USOC_MENU_ROLES') ?: ''))
)));
$uiEnabled = (string) (getenv('SIF_USOC_UI_ENABLED') ?: '') === '1';

$scheme = $url === '' ? null : strtolower((string) parse_url($url, PHP_URL_SCHEME));
$urlPath = $url === '' ? null : (string) parse_url($url, PHP_URL_PATH);
$urlHost = $url === '' ? null : trim((string) parse_url($url, PHP_URL_HOST));

$checks = [
    'internal_usoc_url_configured' => $url !== '',
    'internal_usoc_url_https' => $scheme === 'https',
    'internal_usoc_url_host' => $urlHost !== '',
    'internal_usoc_expected_host_configured' => $expectedHost !== '',
    'internal_usoc_url_matches_expected_host' =>
        $expectedHost !== '' && strtolower($urlHost) === $expectedHost,
    'internal_usoc_signed_path' => $signedPath === '/api/usoc/manage.php',
    'internal_usoc_url_matches_signed_path' =>
        $urlPath !== null && $urlPath === $signedPath,
    'internal_api_key_id' => $keyId !== '',
    'internal_api_secret' => $secret !== '',
    'usoc_menu_roles' => $menuRoles !== [],
    'usoc_ui_enabled' => $uiEnabled,
    'usoc_client_file' => is_file($root . '/SifInternalUsocClient.php'),
    'usoc_sidebar_file' => is_file($root . '/ajax/mostrarSideBarMenu.php'),
    'usoc_student_view_file' => is_file($root . '/alumnes-mostrar-alumne.php'),
    'usoc_preview_endpoint_file' =>
        is_file($root . '/ajax/alumnes/sifUsocCourseChangePreview.php'),
    'usoc_prepare_endpoint_file' =>
        is_file($root . '/ajax/alumnes/sifUsocCourseChangePrepare.php'),
    'usoc_course_change_endpoint_file' =>
        is_file($root . '/ajax/alumnes/realitzarCanviCurs_CanviCurs.php'),
    'usoc_course_change_ui_file' =>
        is_file($root . '/js/alumnes-usoc-lifecycle-preview.js'),
];

$failed = array_keys(array_filter(
    $checks,
    static fn (bool $ok): bool => !$ok
));

$result = [
    'ok' => $failed === [],
    'scope' => 'intranet_runtime',
    'checks' => $checks,
    'failed' => $failed,
    'safe_config' => [
        'url_host' => $urlHost !== '' ? $urlHost : null,
        'expected_host' => $expectedHost !== '' ? $expectedHost : null,
        'url_scheme' => $scheme !== '' ? $scheme : null,
        'url_path' => $urlPath !== '' ? $urlPath : null,
        'signed_path' => $signedPath !== '' ? $signedPath : null,
        'key_id' => $keyId !== '' ? $keyId : null,
        'menu_roles' => $menuRoles,
        'ui_enabled' => $uiEnabled,
    ],
    'secrets' => [
        'key_id_present' => $keyId !== '',
        'secret_present' => $secret !== '',
    ],
];

echo json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
), PHP_EOL;

exit($result['ok'] ? 0 : 1);
