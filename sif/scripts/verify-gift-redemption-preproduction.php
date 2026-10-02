<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\CommercialEntitlementRepository;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Service\GiftEnrollmentStager;
use Prisma\Sif\Service\GiftRedemptionNotificationBundleService;
use Prisma\Sif\Service\GiftRedemptionOrchestrator;
use Prisma\Sif\Service\GiftRedemptionService;
use Prisma\Sif\Service\GiftRedemptionTrustedContextResolver;
use Prisma\Sif\Service\LegacyGiftUsageReconciler;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$baseDir = dirname(__DIR__);
$config = require $baseDir . '/config/sif.php';
$environment = (string) ($config['env'] ?? 'local');
$execute = in_array('--execute', array_slice($argv, 1), true);

$result = [
    'ok' => false,
    'scope' => 'uc-018-gift-redemption-preproduction-verification',
    'production_authorized' => false,
    'environment' => $environment,
    'mode' => $execute ? 'execute' : 'dry-run',
    'executed_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'checks' => [],
];

if (!in_array($environment, ['test', 'preproduction'], true)) {
    $result['checks']['environment_is_test_or_preproduction'] = false;
    $result['failed'] = ['environment_is_test_or_preproduction'];
    output($result, 1);
}
$result['checks']['environment_is_test_or_preproduction'] = true;

$preflight = runJsonScript(
    [PHP_BINARY, $baseDir . '/scripts/preflight-gift-redemption.php'],
    $baseDir
);
$result['preflight'] = $preflight['json'];
$result['checks']['preflight_exit_zero'] = $preflight['exit_code'] === 0;
$result['checks']['preflight_ok'] = ($preflight['json']['ok'] ?? false) === true;

if (!$execute) {
    $result['checks']['dry_run_does_not_execute_redemption'] = true;
    $failed = failedChecks($result['checks']);
    $result['ok'] = $failed === [];
    if ($failed !== []) {
        $result['failed'] = $failed;
    }
    $result['execute_requirements'] = [
        'flag' => '--execute',
        'enrollment_id_source' => 'SIF_GIFT_REDEMPTION_TEST_ENROLLMENT_ID',
        'gift_code_source' => 'SIF_GIFT_REDEMPTION_TEST_CODE',
        'gift_code_on_cli_forbidden' => true,
    ];
    output($result, $result['ok'] ? 0 : 1);
}

$enrollmentIdRaw = trim((string) (getenv(
    'SIF_GIFT_REDEMPTION_TEST_ENROLLMENT_ID'
) ?: ''));
$giftCode = trim((string) (getenv('SIF_GIFT_REDEMPTION_TEST_CODE') ?: ''));

$result['checks']['execute_enrollment_id_from_environment'] =
    ctype_digit($enrollmentIdRaw) && (int) $enrollmentIdRaw > 0;
$result['checks']['execute_gift_code_from_environment'] =
    $giftCode !== '' && strlen($giftCode) <= 200;

if (!$result['checks']['execute_enrollment_id_from_environment']
    || !$result['checks']['execute_gift_code_from_environment']
) {
    $result['failed'] = failedChecks($result['checks']);
    output($result, 1);
}

$enrollmentId = (int) $enrollmentIdRaw;
$sifDb = ConnectionFactory::make($config);
$legacyDb = ConnectionFactory::makeLegacy($config);

$invoiceCountBefore = scalarInt($sifDb, 'SELECT COUNT(*) FROM factura');
$chargeCountBefore = scalarInt(
    $sifDb,
    "SELECT COUNT(*) FROM payment_transaction WHERE TIPUS_MOVIMENT='CHARGE'"
);

$entitlements = new CommercialEntitlementRepository(new UuidGenerator());
$orchestrator = new GiftRedemptionOrchestrator(
    new GiftRedemptionTrustedContextResolver($entitlements),
    new GiftEnrollmentStager(new UuidGenerator(), $entitlements),
    new GiftRedemptionService(
        $entitlements,
        new EnrollmentFundMovementRepository(new UuidGenerator())
    ),
    new LegacyGiftUsageReconciler()
);

$first = $orchestrator->execute(
    $sifDb,
    $legacyDb,
    $enrollmentId,
    $giftCode,
    'WEB',
    'UC018-PREPROD-FIRST-' . bin2hex(random_bytes(6)),
    'uc018-preproduction-verifier'
);
$firstBundle = (new GiftRedemptionNotificationBundleService(
    new NotificationOutboxRepository(new UuidGenerator())
))->enqueue(
    $sifDb,
    $legacyDb,
    $enrollmentId,
    $first
);

$second = $orchestrator->execute(
    $sifDb,
    $legacyDb,
    $enrollmentId,
    $giftCode,
    'WEB',
    'UC018-PREPROD-REPLAY-' . bin2hex(random_bytes(6)),
    'uc018-preproduction-verifier'
);
$secondBundle = (new GiftRedemptionNotificationBundleService(
    new NotificationOutboxRepository(new UuidGenerator())
))->enqueue(
    $sifDb,
    $legacyDb,
    $enrollmentId,
    $second
);

$stage = (array) ($first['stage'] ?? []);
$redemption = (array) ($first['redemption'] ?? []);
$legacyReconciliation = (array) ($first['legacy_reconciliation'] ?? []);
$replayStage = (array) ($second['stage'] ?? []);
$replayRedemption = (array) ($second['redemption'] ?? []);

$uuidOperation = trim((string) ($stage['uuid_operation'] ?? ''));
$uuidEntitlement = trim((string) ($stage['uuid_entitlement'] ?? ''));

$result['checks']['first_redemption_consumed'] =
    strtoupper((string) ($redemption['status'] ?? '')) === 'CONSUMED';
$result['checks']['legacy_reconciled'] =
    strtoupper((string) ($legacyReconciliation['status'] ?? '')) === 'RECONCILED';
$result['checks']['destination_operation_identity_present'] = $uuidOperation !== '';
$result['checks']['entitlement_identity_present'] = $uuidEntitlement !== '';
$result['checks']['replay_same_operation'] =
    $uuidOperation !== ''
    && $uuidOperation === (string) ($replayStage['uuid_operation'] ?? '');
$result['checks']['replay_reused'] =
    ($replayStage['idempotency_reused'] ?? false) === true
    && ($replayRedemption['idempotency_reused'] ?? false) === true;
$result['checks']['notification_bundle_reused'] =
    (string) ($firstBundle['uuid_notification'] ?? '') !== ''
    && (string) ($firstBundle['uuid_notification'] ?? '')
        === (string) ($secondBundle['uuid_notification'] ?? '')
    && ($secondBundle['idempotency_reused'] ?? false) === true;

if ($uuidOperation !== '' && $uuidEntitlement !== '') {
    $result['checks']['one_destination_operation'] = queryCount(
        $sifDb,
        "SELECT COUNT(*) FROM commercial_operation
         WHERE UUID_OPERATION = ?
           AND SOURCE_TYPE='INSCRIPCIO'
           AND SOURCE_ID = ?",
        [$uuidOperation, (string) $enrollmentId]
    ) === 1;

    $result['checks']['one_compensation_allocation'] = queryCount(
        $sifDb,
        "SELECT COUNT(*) FROM enrollment_fund_movement
         WHERE UUID_OPERATION = ?
           AND MOVEMENT_TYPE='COMPENSATION_ALLOCATION'",
        [$uuidOperation]
    ) === 1;

    $result['checks']['one_consume_event'] = queryCount(
        $sifDb,
        "SELECT COUNT(*) FROM commercial_entitlement_event
         WHERE UUID_ENTITLEMENT = ?
           AND ACTION='CONSUME'",
        [$uuidEntitlement]
    ) === 1;

    $entitlementState = one(
        $sifDb,
        'SELECT STATUS, CONSUMED_UUID_OPERATION
         FROM commercial_entitlement
         WHERE UUID_ENTITLEMENT = ?',
        [$uuidEntitlement]
    );
    $result['checks']['entitlement_consumed_by_destination'] =
        is_array($entitlementState)
        && strtoupper((string) ($entitlementState['STATUS'] ?? '')) === 'CONSUMED'
        && (string) ($entitlementState['CONSUMED_UUID_OPERATION'] ?? '')
            === $uuidOperation;
} else {
    $result['checks']['one_destination_operation'] = false;
    $result['checks']['one_compensation_allocation'] = false;
    $result['checks']['one_consume_event'] = false;
    $result['checks']['entitlement_consumed_by_destination'] = false;
}

$result['checks']['legacy_usat_matches_enrollment'] = (int) scalar(
    $legacyDb,
    'SELECT USAT FROM regal WHERE CODI = ?',
    [$giftCode]
) === $enrollmentId;

$result['checks']['no_new_invoice'] =
    scalarInt($sifDb, 'SELECT COUNT(*) FROM factura') === $invoiceCountBefore;
$result['checks']['no_new_charge'] =
    scalarInt(
        $sifDb,
        "SELECT COUNT(*) FROM payment_transaction WHERE TIPUS_MOVIMENT='CHARGE'"
    ) === $chargeCountBefore;

$result['evidence'] = [
    'enrollment_id' => $enrollmentId,
    'uuid_operation' => $uuidOperation,
    'uuid_entitlement' => $uuidEntitlement,
    'uuid_notification' => (string) ($firstBundle['uuid_notification'] ?? ''),
    'invoice_count_before' => $invoiceCountBefore,
    'invoice_count_after' => scalarInt($sifDb, 'SELECT COUNT(*) FROM factura'),
    'charge_count_before' => $chargeCountBefore,
    'charge_count_after' => scalarInt(
        $sifDb,
        "SELECT COUNT(*) FROM payment_transaction WHERE TIPUS_MOVIMENT='CHARGE'"
    ),
    'smtp_executed' => false,
];

$failed = failedChecks($result['checks']);
$result['ok'] = $failed === [];
if ($failed !== []) {
    $result['failed'] = $failed;
}

output($result, $result['ok'] ? 0 : 1);

function failedChecks(array $checks): array
{
    return array_keys(array_filter(
        $checks,
        static fn ($value): bool => $value !== true
    ));
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
                || str_contains($normalized, 'gift_code')
                || $normalized === 'code'
            ) {
                continue;
            }
            $clean[$key] = $walk($child);
        }

        return $clean;
    };

    return $walk($value);
}

function scalarInt(\PDO $db, string $sql): int
{
    return (int) $db->query($sql)->fetchColumn();
}

function scalar(\PDO $db, string $sql, array $params): mixed
{
    $statement = $db->prepare($sql);
    $statement->execute($params);

    return $statement->fetchColumn();
}

function queryCount(\PDO $db, string $sql, array $params): int
{
    return (int) scalar($db, $sql, $params);
}

function one(\PDO $db, string $sql, array $params): ?array
{
    $statement = $db->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch(\PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function output(array $result, int $exitCode): never
{
    echo json_encode(
        sanitizeEvidence($result),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ), PHP_EOL;
    exit($exitCode);
}
