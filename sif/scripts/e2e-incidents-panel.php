<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Domain\UuidGenerator;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$baseDir = dirname(__DIR__);
$config = require $baseDir . '/config/sif.php';
$env = (string) ($config['env'] ?? 'local');
$panel = (array) ($config['panel'] ?? []);
$incidents = (array) ($config['incidents'] ?? []);

$result = [
    'ok' => false,
    'scope' => 'uc-008-incident-panel-e2e-read-only',
    'production_authorized' => false,
    'environment' => $env,
    'checks' => [],
];

if (!in_array($env, ['test', 'preproduction'], true)) {
    $result['checks']['environment_not_production'] = false;
    $result['failed'] = ['environment_not_production'];
    output($result, 1);
}
$result['checks']['environment_not_production'] = true;

$panelUrl = trim((string) (getenv('SIF_E2E_INCIDENT_PANEL_URL') ?: ''));
$expectedHost = strtolower(trim((string) (getenv('SIF_E2E_INCIDENT_EXPECTED_HOST') ?: '')));
$productionHost = strtolower(trim((string) (getenv('SIF_PRODUCTION_HOST') ?: 'pay.prisma.cat')));
$panelHost = urlHost($panelUrl);
$actorId = trim((string) (getenv('SIF_E2E_INCIDENT_ACTOR_ID') ?: 'uc008-e2e-reader'));
$readRole = strtoupper(trim((string) (getenv('SIF_E2E_INCIDENT_READ_ROLE') ?: '')));

if ($readRole === '') {
    $readRoles = normalizeRoles((array) ($incidents['read_roles'] ?? []));
    $manageRoles = normalizeRoles((array) ($incidents['manage_roles'] ?? []));
    foreach ($readRoles as $candidate) {
        if (!in_array($candidate, $manageRoles, true)) {
            $readRole = $candidate;
            break;
        }
    }
}

$result['checks']['panel_url_configured'] = $panelUrl !== '';
$result['checks']['panel_url_https'] = isHttpsUrl($panelUrl);
$result['checks']['panel_expected_host_configured'] =
    $env !== 'preproduction' || $expectedHost !== '';
$result['checks']['panel_host_matches_expected'] =
    $env !== 'preproduction'
    || (
        $panelHost !== ''
        && $expectedHost !== ''
        && hash_equals($expectedHost, $panelHost)
    );
$result['checks']['panel_host_not_production'] =
    $env !== 'preproduction'
    || (
        $panelHost !== ''
        && $productionHost !== ''
        && !hash_equals($productionHost, $panelHost)
    );
$result['checks']['read_only_role_available'] = $readRole !== '';
$result['checks']['launch_key_configured'] = trim((string) ($panel['launch_key_id'] ?? '')) !== '';
$result['checks']['launch_secret_configured'] = strlen((string) ($panel['launch_secret'] ?? '')) >= 32;
$result['checks']['launch_path_exact'] = (string) ($panel['launch_path'] ?? '') === '/sif/incidencies/';

$failed = failedChecks($result['checks']);
if ($failed !== []) {
    $result['failed'] = $failed;
    output($result, 1);
}

$timestamp = (string) time();
$requestId = (new UuidGenerator())->generate();
$roles = [$readRole];
$canonicalRoles = implode(',', $roles);
$path = (string) $panel['launch_path'];
$canonical = implode("\n", [
    'SIF_PANEL_LAUNCH',
    'POST',
    $path,
    $timestamp,
    strtolower($requestId),
    $actorId,
    $canonicalRoles,
]);

$form = http_build_query([
    'key_id' => (string) $panel['launch_key_id'],
    'timestamp' => $timestamp,
    'request_id' => $requestId,
    'actor_id' => $actorId,
    'roles' => $canonicalRoles,
    'signature' => hash_hmac('sha256', $canonical, (string) $panel['launch_secret']),
], '', '&', PHP_QUERY_RFC3986);

try {
    $launch = request($panelUrl, 'POST', [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: text/html',
    ], $form);

    $result['checks']['launch_returns_303'] = $launch['status'] === 303;
    $cookie = firstCookie($launch['headers']);
    $result['checks']['session_cookie_received'] = $cookie !== '';

    if (!$result['checks']['launch_returns_303'] || !$result['checks']['session_cookie_received']) {
        $result['failed'] = failedChecks($result['checks']);
        output($result, 1);
    }

    $page = request($panelUrl, 'GET', [
        'Accept: text/html',
        'Cookie: ' . $cookie,
    ]);
    $result['checks']['authenticated_panel_returns_200'] = $page['status'] === 200;
    $csrf = extractAttribute($page['body'], 'data-csrf');
    $canManage = extractAttribute($page['body'], 'data-can-manage');
    $result['checks']['csrf_exposed_to_same_origin_ui'] = $csrf !== '';
    $result['checks']['read_only_actor_has_no_manage_controls'] = $canManage === '0';

    $actionsUrl = rtrim($panelUrl, '/') . '/actions.php';

    $summary = requestJson($actionsUrl, $cookie, $csrf, ['action' => 'summary']);
    $result['checks']['summary_returns_200'] = $summary['status'] === 200;
    $result['checks']['summary_ok'] = ($summary['json']['ok'] ?? false) === true;

    $list = requestJson($actionsUrl, $cookie, $csrf, [
        'action' => 'list',
        'filters' => [],
        'limit' => 1,
    ]);
    $result['checks']['list_returns_200'] = $list['status'] === 200;
    $result['checks']['list_ok'] = ($list['json']['ok'] ?? false) === true;

    $logout = requestJson($actionsUrl, $cookie, $csrf, ['action' => 'logout']);
    $result['checks']['logout_returns_200'] = $logout['status'] === 200;
    $result['checks']['logout_ok'] = ($logout['json']['ok'] ?? false) === true;

    $afterLogout = requestJson($actionsUrl, $cookie, $csrf, ['action' => 'summary']);
    $result['checks']['session_invalid_after_logout'] = in_array($afterLogout['status'], [401, 403], true);
} catch (Throwable $exception) {
    $result['errors'] = ['http' => $exception->getMessage()];
}

$failed = failedChecks($result['checks']);
$result['ok'] = $failed === [] && !isset($result['errors']);
if ($failed !== []) {
    $result['failed'] = $failed;
}

output($result, $result['ok'] ? 0 : 1);

function requestJson(string $url, string $cookie, string $csrf, array $payload): array
{
    $response = request($url, 'POST', [
        'Content-Type: application/json',
        'Accept: application/json',
        'Cookie: ' . $cookie,
        'X-CSRF-Token: ' . $csrf,
    ], json_encode($payload, JSON_THROW_ON_ERROR));

    $json = json_decode($response['body'], true);
    $response['json'] = is_array($json) ? $json : [];
    return $response;
}

function request(string $url, string $method, array $headers, ?string $body = null): array
{
    $options = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'timeout' => 15,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
        ],
    ];
    if ($body !== null) {
        $options['http']['content'] = $body;
    }

    $context = stream_context_create($options);
    $responseBody = file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];

    if ($responseBody === false && $responseHeaders === []) {
        throw new RuntimeException('Could not reach incident panel endpoint');
    }

    return [
        'status' => httpStatus($responseHeaders),
        'headers' => $responseHeaders,
        'body' => $responseBody === false ? '' : $responseBody,
    ];
}

function httpStatus(array $headers): int
{
    foreach ($headers as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/i', (string) $header, $matches) === 1) {
            return (int) $matches[1];
        }
    }
    return 0;
}

function firstCookie(array $headers): string
{
    foreach ($headers as $header) {
        if (stripos((string) $header, 'Set-Cookie:') === 0) {
            $value = trim(substr((string) $header, strlen('Set-Cookie:')));
            return trim(explode(';', $value, 2)[0] ?? '');
        }
    }
    return '';
}

function extractAttribute(string $html, string $attribute): string
{
    $pattern = '/\b' . preg_quote($attribute, '/') . '="([^"]*)"/i';
    if (preg_match($pattern, $html, $matches) !== 1) {
        return '';
    }

    return html_entity_decode((string) $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function normalizeRoles(array $roles): array
{
    $normalized = [];
    foreach ($roles as $role) {
        $role = strtoupper(trim((string) $role));
        if ($role !== '') {
            $normalized[$role] = true;
        }
    }
    $result = array_keys($normalized);
    sort($result, SORT_STRING);
    return $result;
}

function isHttpsUrl(string $url): bool
{
    if ($url === '') {
        return false;
    }
    $parts = parse_url($url);
    return is_array($parts) && strtolower((string) ($parts['scheme'] ?? '')) === 'https';
}

function urlHost(string $url): string
{
    if ($url === '') {
        return '';
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return '';
    }

    return strtolower(trim((string) ($parts['host'] ?? '')));
}

function failedChecks(array $checks): array
{
    return array_keys(array_filter($checks, static fn ($ok): bool => $ok !== true));
}

function output(array $result, int $exitCode): never
{
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit($exitCode);
}
