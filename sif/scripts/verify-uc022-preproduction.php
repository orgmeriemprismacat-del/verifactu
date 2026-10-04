<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
$environment = strtolower(trim((string) ($config['env'] ?? 'local')));
$numVisible = trim((string) ($argv[1] ?? ''));
$bank = strtoupper(trim((string) (getenv('UC022_VERIFY_BANK') ?: '')));
$eventId = trim((string) (getenv('UC022_VERIFY_BANK_EVENT_ID') ?: ''));

$result = [
    'ok' => false,
    'scope' => 'uc-022-preproduction-evidence',
    'environment' => $environment,
    'executed_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'checks' => [],
];

if (!in_array($environment, ['test', 'preproduction'], true)) {
    $result['checks']['environment_is_test_or_preproduction'] = false;
    output($result, 1);
}
$result['checks']['environment_is_test_or_preproduction'] = true;

if ($numVisible === '' || $bank === '' || $eventId === '') {
    $result['checks']['verification_inputs_present'] = false;
    $result['usage'] =
        'UC022_VERIFY_BANK="BANK" UC022_VERIFY_BANK_EVENT_ID="immutable-id" '
        . 'php sif/scripts/verify-uc022-preproduction.php NUM_VISIBLE';
    output($result, 1);
}
$result['checks']['verification_inputs_present'] = true;

$preflight = runJsonScript(
    [PHP_BINARY, dirname(__DIR__) . '/scripts/preflight-uc022-manual-transfer.php'],
    dirname(__DIR__)
);
$result['preflight'] = $preflight['json'];
$result['checks']['preflight_exit_zero'] = $preflight['exit_code'] === 0;
$result['checks']['preflight_ok'] = ($preflight['json']['ok'] ?? false) === true;

try {
    $sifDb = ConnectionFactory::make($config);
    $legacyDb = ConnectionFactory::makeLegacy($config);

    $invoice = one($sifDb, 'SELECT UUID_FACTURA, NUM_VISIBLE, TOTAL, ESTAT_COBRAMENT
                            FROM factura WHERE NUM_VISIBLE = ?', [$numVisible]);
    $result['checks']['sif_invoice_exists'] = is_array($invoice);

    $legacyInvoice = one(
        $legacyDb,
        'SELECT factura_relacionada FROM factures WHERE num = ? LIMIT 1',
        [$numVisible]
    );
    $result['checks']['legacy_invoice_exists'] = is_array($legacyInvoice);

    $idempotencyKey = 'TRANSFERENCIA|BANK_EVENT_SHA256:'
        . hash('sha256', $bank . "\n" . $eventId);

    $payment = one(
        $sifDb,
        'SELECT UUID_PAYMENT, IDEMPOTENCY_KEY, PROVIDER_REF, IMPORT, ESTAT
         FROM payment_transaction
         WHERE IDEMPOTENCY_KEY = ?
         LIMIT 1',
        [$idempotencyKey]
    );

    $result['checks']['payment_exists'] = is_array($payment);
    $result['checks']['payment_confirmed'] =
        is_array($payment) && (string) ($payment['ESTAT'] ?? '') === 'CONFIRMED';

    $uuidPayment = is_array($payment) ? (string) ($payment['UUID_PAYMENT'] ?? '') : '';
    $uuidFactura = is_array($invoice) ? (string) ($invoice['UUID_FACTURA'] ?? '') : '';

    $allocationCount = 0;
    if ($uuidPayment !== '' && $uuidFactura !== '') {
        $stmt = $sifDb->prepare(
            'SELECT COUNT(*) FROM payment_allocation
             WHERE UUID_PAYMENT = ? AND UUID_FACTURA = ?'
        );
        $stmt->execute([$uuidPayment, $uuidFactura]);
        $allocationCount = (int) $stmt->fetchColumn();
    }
    $result['checks']['single_invoice_allocation_present'] = $allocationCount === 1;

    $auditCounts = [
        'payment_action_event' => countRows(
            $sifDb,
            "SELECT COUNT(*) FROM payment_action_event
             WHERE UUID_PAYMENT = ?
               AND ACTION IN ('CREATE','IDEMPOTENCY_REUSE')",
            [$uuidPayment]
        ),
        'operational_event' => countRows(
            $sifDb,
            "SELECT COUNT(*) FROM operational_event
             WHERE UUID_PAYMENT = ?
               AND OPERATION_TYPE = 'REGISTER_MANUAL_TRANSFER'",
            [$uuidPayment]
        ),
        'sif_audit_event' => countRows(
            $sifDb,
            "SELECT COUNT(*) FROM sif_audit_event
             WHERE RESOURCE_ID = ?
               AND ACTION = 'REGISTER_MANUAL_TRANSFER'",
            [$uuidPayment]
        ),
        'legacy_sync_terminal' => countRows(
            $sifDb,
            "SELECT COUNT(*) FROM payment_action_event
             WHERE UUID_PAYMENT = ?
               AND ACTION = 'SYNC_LEGACY'
               AND RESULT IN ('SUCCEEDED','FAILED')",
            [$uuidPayment]
        ),
    ];

    $result['checks']['payment_action_audit_present'] = $auditCounts['payment_action_event'] >= 1;
    $result['checks']['operational_audit_present'] = $auditCounts['operational_event'] >= 1;
    $result['checks']['sif_audit_present'] = $auditCounts['sif_audit_event'] >= 1;
    $result['checks']['legacy_sync_terminal_present'] = $auditCounts['legacy_sync_terminal'] >= 1;

    $legacyPaymentTotal = null;
    if (is_array($legacyInvoice)) {
        $stmt = $legacyDb->prepare(
            'SELECT COALESCE(SUM(PAGAMENT),0)
             FROM inscripcions
             WHERE FACTURA_RELACIONADA = ?'
        );
        $stmt->execute([(int) $legacyInvoice['factura_relacionada']]);
        $legacyPaymentTotal = number_format((float) $stmt->fetchColumn(), 2, '.', '');
    }

    $result['evidence'] = [
        'num_visible_sha256' => hash('sha256', $numVisible),
        'bank_event_sha256' => hash('sha256', $bank . "\n" . $eventId),
        'uuid_payment' => $uuidPayment !== '' ? $uuidPayment : null,
        'uuid_factura' => $uuidFactura !== '' ? $uuidFactura : null,
        'invoice_payment_status' => is_array($invoice) ? ($invoice['ESTAT_COBRAMENT'] ?? null) : null,
        'legacy_projected_payment_total' => $legacyPaymentTotal,
        'audit_counts' => $auditCounts,
    ];
} catch (Throwable $exception) {
    $result['checks']['verification_query_execution'] = false;
    $result['error'] = $exception->getMessage();
    output($result, 1);
}

$result['checks']['verification_query_execution'] = true;
$failed = array_keys(array_filter(
    $result['checks'],
    static fn ($ok): bool => $ok !== true
));
$result['ok'] = $failed === [];
if ($failed !== []) {
    $result['failed'] = $failed;
}

output($result, $result['ok'] ? 0 : 1);

function one(PDO $db, string $sql, array $params): ?array
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function countRows(PDO $db, string $sql, array $params): int
{
    if (($params[0] ?? '') === '') {
        return 0;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

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
            'json' => ['ok' => false, 'error' => 'Could not start preflight'],
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
            'error' => 'Preflight did not return JSON',
            'stderr_present' => trim((string) $stderr) !== '',
        ];
    }

    return ['exit_code' => $exitCode, 'json' => $json];
}

function output(array $result, int $exitCode): never
{
    echo json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ), PHP_EOL;
    exit($exitCode);
}
