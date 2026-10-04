<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\RedsysCallbackQueueRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Service\RedsysGiftPaymentStatusService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$baseDir = dirname(__DIR__);
$config = require $baseDir . '/config/sif.php';
$environment = (string) ($config['env'] ?? 'local');
$dsOrder = trim((string) ($argv[1] ?? ''));

$result = [
    'ok' => false,
    'scope' => 'uc-017-redsys-gift-preproduction-verification',
    'environment' => $environment,
    'executed_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'checks' => [],
];

if (!in_array($environment, ['test', 'preproduction', 'preprod'], true)) {
    $result['checks']['environment_is_test_or_preproduction'] = false;
    $result['failed'] = ['environment_is_test_or_preproduction'];
    output($result, 1);
}
$result['checks']['environment_is_test_or_preproduction'] = true;

$preflightGift = runJsonScript(
    [PHP_BINARY, $baseDir . '/scripts/preflight-redsys-gift.php'],
    $baseDir
);
$result['preflight_gift'] = $preflightGift['json'];
$result['checks']['preflight_gift_exit_zero'] = $preflightGift['exit_code'] === 0;
$result['checks']['preflight_gift_ok'] = ($preflightGift['json']['ok'] ?? false) === true;

$preflightQueue = runJsonScript(
    [PHP_BINARY, $baseDir . '/scripts/preflight-redsys-callback-queue.php'],
    $baseDir
);
$result['preflight_queue'] = $preflightQueue['json'];
$result['checks']['preflight_queue_exit_zero'] = $preflightQueue['exit_code'] === 0;
$result['checks']['preflight_queue_ok'] = ($preflightQueue['json']['ok'] ?? false) === true;

if ($dsOrder === '') {
    $result['checks']['ds_order_provided'] = false;
    $result['usage'] = 'php sif/scripts/verify-redsys-gift-preproduction.php DS_ORDER';
    finish($result);
}
$result['checks']['ds_order_provided'] = true;
$result['ds_order'] = $dsOrder;

try {
    $db = ConnectionFactory::make($config);
    $intents = new RedsysPaymentIntentRepository();
    $notifications = new RedsysNotificationRepository();
    $queue = new RedsysCallbackQueueRepository(new UuidGenerator());

    $intent = $intents->findByDsOrder($db, $dsOrder);
    $result['checks']['gift_intent_present'] = is_array($intent);
    $result['checks']['gift_intent_source_type_regal'] = is_array($intent)
        && strtoupper((string) ($intent['SOURCE_TYPE'] ?? '')) === 'REGAL';
    $result['checks']['gift_intent_snapshot_present'] = is_array($intent)
        && trim((string) ($intent['SNAPSHOT_JSON'] ?? '')) !== '';

    $snapshot = is_array($intent)
        ? json_decode((string) ($intent['SNAPSHOT_JSON'] ?? ''), true)
        : null;
    $gift = is_array($snapshot) ? ($snapshot['gift'] ?? null) : null;
    $giftId = is_array($gift) && is_numeric($gift['ID'] ?? null)
        ? (int) $gift['ID']
        : 0;
    $result['checks']['gift_snapshot_identity_present'] = $giftId > 0;
    $result['checks']['gift_snapshot_amount_matches_intent'] = is_array($gift)
        && is_numeric($gift['IMPORT'] ?? null)
        && is_array($intent)
        && money($gift['IMPORT']) === money($intent['EXPECTED_AMOUNT'] ?? null);

    $notification = $notifications->findByDsOrder($db, $dsOrder);
    $result['checks']['validated_notification_present'] = is_array($notification)
        && strtoupper((string) ($notification['STATUS'] ?? '')) === 'VALIDATED';

    $statusService = new RedsysGiftPaymentStatusService($intents, $notifications, $queue);
    $paymentStatus = $statusService->status($db, $dsOrder);
    $result['payment_status'] = $paymentStatus;
    $result['checks']['worker_processed'] = ($paymentStatus['status'] ?? '') === 'CONFIRMED';
    $result['checks']['invoice_identity_present'] =
        trim((string) ($paymentStatus['uuid_factura'] ?? '')) !== '';
    $result['checks']['payment_identity_present'] =
        trim((string) ($paymentStatus['uuid_payment'] ?? '')) !== '';

    $operation = null;
    if ($giftId > 0) {
        $stmt = $db->prepare(
            "SELECT UUID_OPERATION, UUID_FACTURA, UUID_PAYMENT, STATUS
             FROM commercial_operation
             WHERE OPERATION_TYPE='GIFT_PURCHASE' AND SOURCE_TYPE='REGAL' AND SOURCE_ID=?
             LIMIT 1"
        );
        $stmt->execute([(string) $giftId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $operation = is_array($row) ? $row : null;
    }

    $result['checks']['gift_purchase_operation_present'] = is_array($operation);
    $result['checks']['gift_purchase_links_invoice_payment'] = is_array($operation)
        && (string) ($operation['UUID_FACTURA'] ?? '') === (string) ($paymentStatus['uuid_factura'] ?? '')
        && (string) ($operation['UUID_PAYMENT'] ?? '') === (string) ($paymentStatus['uuid_payment'] ?? '');

    $entitlement = null;
    if (is_array($operation)) {
        $stmt = $db->prepare(
            "SELECT UUID_ENTITLEMENT, STATUS, HOLDER_PARTY_KEY
             FROM commercial_entitlement
             WHERE ENTITLEMENT_TYPE='GIFT' AND ORIGIN_UUID_OPERATION=?
             LIMIT 1"
        );
        $stmt->execute([(string) $operation['UUID_OPERATION']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $entitlement = is_array($row) ? $row : null;
    }

    $result['checks']['gift_entitlement_present'] = is_array($entitlement);
    $result['checks']['gift_entitlement_active'] = is_array($entitlement)
        && strtoupper((string) ($entitlement['STATUS'] ?? '')) === 'ACTIVE';
    $result['checks']['gift_entitlement_holder_present'] = is_array($entitlement)
        && trim((string) ($entitlement['HOLDER_PARTY_KEY'] ?? '')) !== '';

    $result['evidence'] = [
        'gift_id' => $giftId > 0 ? $giftId : null,
        'queue_status' => $paymentStatus['queue_status'] ?? null,
        'uuid_factura' => $paymentStatus['uuid_factura'] ?? null,
        'uuid_payment' => $paymentStatus['uuid_payment'] ?? null,
        'uuid_operation' => $operation['UUID_OPERATION'] ?? null,
        'uuid_entitlement' => $entitlement['UUID_ENTITLEMENT'] ?? null,
    ];
} catch (Throwable $exception) {
    $result['checks']['verification_query_completed'] = false;
    $result['error'] = $exception->getMessage();
}

finish($result);

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
        return ['exit_code' => 127, 'json' => ['ok' => false]];
    }

    fclose($pipes[0]);
    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    $json = json_decode(trim($stdout), true);
    if (!is_array($json)) {
        $json = ['ok' => false, 'invalid_json' => true];
        if (trim($stderr) !== '') {
            $json['stderr_present'] = true;
        }
    }

    return ['exit_code' => $exitCode, 'json' => sanitizeEvidence($json)];
}

function sanitizeEvidence(array $value): array
{
    $walk = static function ($item) use (&$walk) {
        if (!is_array($item)) {
            return $item;
        }

        $clean = [];
        foreach ($item as $key => $child) {
            $normalized = strtolower((string) $key);
            if (str_contains($normalized, 'secret')
                || str_contains($normalized, 'password')
                || str_contains($normalized, 'signature')
                || str_contains($normalized, 'raw_payload')
                || str_contains($normalized, 'merchant_key')
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
    return is_numeric($value) ? number_format((float) $value, 2, '.', '') : '';
}

function finish(array $result): never
{
    $failed = array_keys(array_filter(
        $result['checks'],
        static fn ($ok): bool => $ok !== true
    ));
    $result['ok'] = $failed === [];
    if ($failed !== []) {
        $result['failed'] = $failed;
    }

    output($result, $result['ok'] ? 0 : 1);
}

function output(array $result, int $exitCode): never
{
    echo json_encode(
        sanitizeEvidence($result),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ), PHP_EOL;
    exit($exitCode);
}
