<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$baseDir = dirname(__DIR__);
$baseUrl = trim((string) (getenv('SIF_INTERNAL_API_BASE_URL') ?: ''));
$keyId = trim((string) (getenv('SIF_INTERNAL_API_KEY_ID') ?: ''));
$secret = trim((string) (getenv('SIF_INTERNAL_API_SECRET') ?: ''));
$signedPath = trim((string) (
    getenv('SIF_INTERNAL_MANUAL_TRANSFER_SIGNED_PATH')
        ?: '/api/payments/manual-transfer.php'
));
$rolesRaw = trim((string) (getenv('SIF_MANUAL_TRANSFER_ROLES') ?: ''));

$roles = array_values(array_filter(array_map(
    static fn (string $role): string => strtoupper(trim($role)),
    explode(',', $rolesRaw)
)));

$checks = [
    'base_url_present' => $baseUrl !== '',
    'base_url_https' => false,
    'base_url_has_no_path' => false,
    'internal_api_key_id_present' => $keyId !== '',
    'internal_api_secret_present' => $secret !== '',
    'internal_api_secret_minimum_length' => strlen($secret) >= 32,
    'manual_transfer_signed_path' => $signedPath === '/api/payments/manual-transfer.php',
    'manual_transfer_roles_present' => $roles !== [],
    'session_guard_file' => is_file($baseDir . '/SifPaymentSessionGuard.php'),
    'internal_api_client_file' => is_file($baseDir . '/SifInternalApiClient.php'),
    'manual_transfer_gateway_file' => is_file($baseDir . '/SifManualTransferGateway.php'),
    'csrf_endpoint_file' => is_file($baseDir . '/ajax/alumnes/obtenirTokenPagamentSif.php'),
    'manual_transfer_endpoint_file' => is_file($baseDir . '/ajax/alumnes/registrarTransferenciaSif.php'),
    'payments_js_file' => is_file($baseDir . '/js/alumnes-pagaments.js'),
];

if ($baseUrl !== '') {
    $parts = parse_url($baseUrl);
    if (is_array($parts)) {
        $checks['base_url_https'] =
            strtolower((string) ($parts['scheme'] ?? '')) === 'https';
        $path = (string) ($parts['path'] ?? '');
        $checks['base_url_has_no_path'] = $path === '' || $path === '/';
    }
}

$failed = array_keys(array_filter(
    $checks,
    static fn (bool $ok): bool => !$ok
));

$result = [
    'ok' => $failed === [],
    'scope' => 'uc-022-intranet-preflight',
    'executed_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'php_version' => PHP_VERSION,
    'checks' => $checks,
    'configuration' => [
        'sif_base_url' => $baseUrl !== '' ? $baseUrl : null,
        'signed_path' => $signedPath,
        'manual_transfer_roles' => $roles,
        'key_id_sha256' => $keyId !== '' ? hash('sha256', $keyId) : null,
        'secret_present' => $secret !== '',
        'secret_sha256' => $secret !== '' ? hash('sha256', $secret) : null,
    ],
];

if ($failed !== []) {
    $result['failed'] = $failed;
}

echo json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
), PHP_EOL;

exit($result['ok'] ? 0 : 1);
