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
$syncLegacy = in_array('--sync-legacy', $args, true);
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
    'scope' => 'uc-014-redsys-course-preproduction-verification',
    'environment' => $environment,
    'executed_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'php_version' => PHP_VERSION,
    'mode' => $execute ? 'execute' : 'dry-run',
    'sync_legacy_requested' => $syncLegacy,
    'checks' => [],
];

if (!in_array($environment, ['test', 'preproduction'], true)) {
    $result['checks']['environment_is_test_or_preproduction'] = false;
    $result['failed'] = ['environment_is_test_or_preproduction'];
    output($result, 1);
}
$result['checks']['environment_is_test_or_preproduction'] = true;

$preflightCourse = runJsonScript(
    [PHP_BINARY, $baseDir . '/scripts/preflight-redsys-course.php'],
    $baseDir
);
$result['preflight_course'] = $preflightCourse['json'];
$result['checks']['preflight_course_exit_zero'] = $preflightCourse['exit_code'] === 0;
$result['checks']['preflight_course_ok'] = ($preflightCourse['json']['ok'] ?? false) === true;

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
    $result['usage'] = 'php sif/scripts/verify-redsys-course-preproduction.php DS_ORDER [--execute] [--sync-legacy]';
    output($result, 1);
}
$result['checks']['ds_order_provided'] = true;
$result['ds_order'] = $dsOrder;

$preview = runJsonScript(
    [PHP_BINARY, $baseDir . '/scripts/preview-redsys-course.php', $dsOrder],
    $baseDir
);
$result['preview'] = $preview['json'];
$result['checks']['preview_exit_zero'] = $preview['exit_code'] === 0;
$result['checks']['preview_ok'] = ($preview['json']['ok'] ?? false) === true;
$result['checks']['preview_is_dry_run'] = ($preview['json']['dry_run'] ?? false) === true;

if ($execute) {
    $command = [PHP_BINARY, $baseDir . '/scripts/process-redsys-course.php', $dsOrder];
    if ($syncLegacy) {
        $command[] = '--sync-legacy';
    }
    $process = runJsonScript($command, $baseDir);
    $result['process'] = $process['json'];
    $result['checks']['process_exit_zero'] = $process['exit_code'] === 0;
    $result['checks']['process_ok'] = ($process['json']['ok'] ?? false) === true;

    $uuidFactura = trim((string) ($process['json']['uuid_factura'] ?? ''));
    $numVisible = trim((string) ($process['json']['num_visible'] ?? ''));
    $result['checks']['process_has_invoice_identity'] = $uuidFactura !== '' && $numVisible !== '';

    if ($syncLegacy) {
        $result['checks']['legacy_sync_executed'] = ($process['json']['legacy_sync_executed'] ?? false) === true;
        $legacyPaymentSync = $process['json']['legacy_payment_sync'] ?? null;
        $result['checks']['legacy_payment_sync_present'] = is_array($legacyPaymentSync);
        $result['checks']['legacy_payment_sync_status_valid'] = is_array($legacyPaymentSync)
            && in_array((string) ($legacyPaymentSync['status'] ?? ''), ['PARTIALLY_PAID', 'PAID'], true)
            && is_numeric($legacyPaymentSync['projected_payment'] ?? null);

        $notificationOutbox = $process['json']['notification_outbox'] ?? null;
        $result['checks']['notification_outbox_present'] = is_array($notificationOutbox);
        $result['checks']['notification_outbox_has_identity'] = is_array($notificationOutbox)
            && trim((string) ($notificationOutbox['uuid_notification'] ?? '')) !== ''
            && trim((string) ($notificationOutbox['status'] ?? '')) !== '';
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

function output(array $result, int $exitCode): never
{
    echo json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ), PHP_EOL;
    exit($exitCode);
}
