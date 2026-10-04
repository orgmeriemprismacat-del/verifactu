<?php

require dirname(__DIR__) . '/src/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$baseDir = dirname(__DIR__);
$config = require $baseDir . '/config/sif.php';
$environment = (string) ($config['env'] ?? 'local');

$args = array_slice($argv, 1);
$execute = in_array('--execute', $args, true);
$diagnosticProcess = in_array('--diagnostic-process', $args, true);
$dsOrder = '';

foreach ($args as $arg) {
    $arg = trim((string) $arg);
    if ($arg === '' || str_starts_with($arg, '--')) {
        continue;
    }
    $dsOrder = $arg;
    break;
}

$result = [
    'ok' => false,
    'scope' => 'uc-015-redsys-pack-preproduction-verification',
    'environment' => $environment,
    'executed_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'php_version' => PHP_VERSION,
    'mode' => $execute ? 'execute-worker' : ($diagnosticProcess ? 'diagnostic-process' : 'dry-run'),
    'checks' => [],
];

if (!in_array($environment, ['test', 'preproduction'], true)) {
    $result['checks']['environment_is_test_or_preproduction'] = false;
    $result['failed'] = ['environment_is_test_or_preproduction'];
    output($result, 1);
}
$result['checks']['environment_is_test_or_preproduction'] = true;

$preflightPack = runJsonScript(
    [PHP_BINARY, $baseDir . '/scripts/preflight-redsys-pack.php'],
    $baseDir
);
$result['preflight_pack'] = $preflightPack['json'];
$result['checks']['preflight_pack_exit_zero'] = $preflightPack['exit_code'] === 0;
$result['checks']['preflight_pack_ok'] = ($preflightPack['json']['ok'] ?? false) === true;

$preflightQueue = runJsonScript(
    [PHP_BINARY, $baseDir . '/scripts/preflight-redsys-callback-queue.php'],
    $baseDir
);
$result['preflight_queue'] = $preflightQueue['json'];
$result['checks']['preflight_queue_exit_zero'] = $preflightQueue['exit_code'] === 0;
$result['checks']['preflight_queue_ok'] = ($preflightQueue['json']['ok'] ?? false) === true;

if ($dsOrder === '') {
    $result['checks']['ds_order_provided'] = false;
    $result['failed'] = array_values(array_unique(array_merge(
        array_keys(array_filter($result['checks'], static fn ($ok): bool => $ok !== true)),
        ['ds_order_provided']
    )));
    $result['usage'] = 'php sif/scripts/verify-redsys-pack-preproduction.php DS_ORDER [--execute|--diagnostic-process]';
    output($result, 1);
}
$result['checks']['ds_order_provided'] = true;
$result['ds_order'] = $dsOrder;

$preview = runJsonScript(
    [PHP_BINARY, $baseDir . '/scripts/preview-redsys-pack.php', $dsOrder],
    $baseDir
);
$previewJson = $preview['json'];
$result['checks']['preview_exit_zero'] = $preview['exit_code'] === 0;
$result['checks']['preview_ok'] = ($previewJson['ok'] ?? false) === true;
$result['checks']['preview_is_dry_run'] = ($previewJson['dry_run'] ?? false) === true;
$result['checks']['preview_has_valid_idpag'] = is_numeric($previewJson['idpag'] ?? null)
    && (int) ($previewJson['idpag'] ?? 0) > 0;

$previewPayload = $previewJson['payload'] ?? null;
$previewTotal = is_array($previewPayload)
    ? ($previewPayload['totals']['total'] ?? null)
    : null;
$previewPaymentAmount = is_array($previewPayload)
    ? ($previewPayload['payment']['amount'] ?? null)
    : null;

$result['checks']['preview_has_pack_totals'] = is_numeric($previewTotal)
    && (float) $previewTotal > 0.0;
$result['checks']['preview_payment_matches_total'] = is_numeric($previewTotal)
    && is_numeric($previewPaymentAmount)
    && money($previewTotal) === money($previewPaymentAmount);

$result['preview'] = [
    'ok' => ($previewJson['ok'] ?? false) === true,
    'dry_run' => ($previewJson['dry_run'] ?? false) === true,
    'ds_order' => (string) ($previewJson['ds_order'] ?? $dsOrder),
    'idpag' => is_numeric($previewJson['idpag'] ?? null) ? (int) $previewJson['idpag'] : null,
    'invoice_total' => is_numeric($previewTotal) ? money($previewTotal) : null,
    'payment_amount' => is_numeric($previewPaymentAmount) ? money($previewPaymentAmount) : null,
];

if ($execute) {
    $workerId = 'uc015-evidence-' . substr(hash('sha256', $dsOrder), 0, 12);
    $worker = runJsonScript(
        [
            PHP_BINARY,
            $baseDir . '/scripts/process-redsys-callback-queue.php',
            '--limit=1',
            '--worker-id=' . $workerId,
            '--ds-order=' . $dsOrder,
        ],
        $baseDir
    );
    $result['worker'] = $worker['json'];
    $result['checks']['worker_exit_zero'] = $worker['exit_code'] === 0;
    $result['checks']['worker_ok'] = ($worker['json']['ok'] ?? false) === true;
    $result['checks']['worker_targeted'] = ($worker['json']['targeted'] ?? false) === true
        && (string) ($worker['json']['ds_order'] ?? '') === $dsOrder;
    $result['checks']['worker_claimed_one'] = (int) ($worker['json']['claimed'] ?? 0) === 1;
    $result['checks']['worker_processed_one'] = (int) ($worker['json']['processed'] ?? 0) === 1;

    $evidence = runJsonScript(
        [PHP_BINARY, $baseDir . '/scripts/verify-redsys-pack-evidence.php', $dsOrder],
        $baseDir
    );
    $result['evidence'] = $evidence['json'];
    $result['checks']['evidence_exit_zero'] = $evidence['exit_code'] === 0;
    $result['checks']['evidence_ok'] = ($evidence['json']['ok'] ?? false) === true;

    foreach (($evidence['json']['checks'] ?? []) as $name => $ok) {
        $result['checks']['evidence_' . $name] = $ok === true;
    }
}

if ($diagnosticProcess) {
    if ($execute) {
        $result['checks']['diagnostic_process_not_combined_with_execute'] = false;
    } else {
        $process = runJsonScript(
            [PHP_BINARY, $baseDir . '/scripts/process-redsys-pack.php', $dsOrder],
            $baseDir
        );
        $result['diagnostic_process'] = $process['json'];
        $result['checks']['diagnostic_process_exit_zero'] = $process['exit_code'] === 0;
        $result['checks']['diagnostic_process_ok'] = ($process['json']['ok'] ?? false) === true;
    }
}

$failed = array_keys(array_filter(
    $result['checks'],
    static fn ($ok): bool => $ok !== true
));
$result['ok'] = $failed === [];

if ($failed !== []) {
    $result['failed'] = $failed;
}

output($result, $result['ok'] ? 0 : 1);

function runJsonScript(array $command, string $cwd): array
{
    $pipes = [];
    $process = proc_open(
        $command,
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        $cwd,
        getenv(),
        ['bypass_shell' => true]
    );

    if (!is_resource($process)) {
        return [
            'exit_code' => 127,
            'json' => ['ok' => false, 'error' => 'Could not start verification command'],
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
            'error' => 'Verification command did not return JSON',
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
        'raw_payload',
        'raw_payload_json',
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
            ) {
                continue;
            }
            $clean[$key] = $walk($child);
        }

        return $clean;
    };

    return $walk($value);
}

function money(mixed $value): string
{
    return is_numeric($value)
        ? number_format((float) $value, 2, '.', '')
        : '';
}

function output(array $result, int $exitCode): never
{
    echo json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ), PHP_EOL;
    exit($exitCode);
}
