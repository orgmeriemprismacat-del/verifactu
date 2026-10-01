<?php

require dirname(__DIR__) . '/src/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$baseDir = dirname(__DIR__);
$config = require $baseDir . '/config/sif.php';
$environment = (string) ($config['env'] ?? 'local');

$result = [
    'ok' => false,
    'scope' => 'uc-008-preproduction-verification',
    'production_authorized' => false,
    'environment' => $environment,
    'executed_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'php_version' => PHP_VERSION,
    'checks' => [],
];

if (!in_array($environment, ['test', 'preproduction'], true)) {
    $result['checks']['environment_not_production'] = false;
    $result['failed'] = ['environment_not_production'];
    output($result, 1);
}
$result['checks']['environment_not_production'] = true;

$preflight = runJsonScript($baseDir . '/scripts/preflight-incidents-panel.php');
$result['preflight'] = $preflight['json'];
$result['checks']['preflight_exit_zero'] = $preflight['exit_code'] === 0;
$result['checks']['preflight_ok'] = ($preflight['json']['ok'] ?? false) === true;

$e2e = runJsonScript($baseDir . '/scripts/e2e-incidents-panel.php');
$result['e2e'] = $e2e['json'];
$result['checks']['e2e_exit_zero'] = $e2e['exit_code'] === 0;
$result['checks']['e2e_ok'] = ($e2e['json']['ok'] ?? false) === true;

$failed = array_keys(array_filter(
    $result['checks'],
    static fn ($ok): bool => $ok !== true
));
$result['ok'] = $failed === [];
if ($failed !== []) {
    $result['failed'] = $failed;
}

output($result, $result['ok'] ? 0 : 1);

function runJsonScript(string $script): array
{
    if (!is_file($script)) {
        return [
            'exit_code' => 127,
            'json' => [
                'ok' => false,
                'error' => 'Required verification script is missing',
            ],
        ];
    }

    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, $script],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        dirname($script, 2),
        getenv(),
        ['bypass_shell' => true]
    );

    if (!is_resource($process)) {
        return [
            'exit_code' => 127,
            'json' => [
                'ok' => false,
                'error' => 'Could not start verification script',
            ],
        ];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    $json = json_decode(trim((string) $stdout), true);
    if (!is_array($json)) {
        $json = [
            'ok' => false,
            'error' => 'Verification script did not return JSON',
        ];
        if (trim((string) $stderr) !== '') {
            $json['stderr_present'] = true;
        }
    }

    return [
        'exit_code' => $exitCode,
        'json' => sanitizeEvidence($json),
    ];
}

function sanitizeEvidence(array $value): array
{
    $forbiddenKeys = [
        'secret',
        'password',
        'signature',
        'merchant_key',
        'certificate_password',
    ];

    $walk = static function ($item) use (&$walk, $forbiddenKeys) {
        if (!is_array($item)) {
            return $item;
        }

        $clean = [];
        foreach ($item as $key => $child) {
            $normalized = strtolower((string) $key);
            if (in_array($normalized, $forbiddenKeys, true)
                || str_contains($normalized, 'secret')
                || str_contains($normalized, 'password')
                || str_contains($normalized, 'signature')
                || str_contains($normalized, 'merchant_key')
                || str_contains($normalized, 'certificate_password')
            ) {
                continue;
            }
            $clean[$key] = $walk($child);
        }
        return $clean;
    };

    return $walk($value);
}

function output(array $result, int $exitCode): never
{
    echo json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ), PHP_EOL;
    exit($exitCode);
}
