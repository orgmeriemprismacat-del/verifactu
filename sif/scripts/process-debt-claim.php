<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\DebtClaimCaseRepository;
use Prisma\Sif\Repository\DebtSnapshotRepository;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Service\DebtClaimCoordinator;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing debt claim processing with SIF_ENV=production.\n");
    exit(1);
}

$payload = parseArgs(array_slice($argv, 1));
if (!isset($payload['action']) || (!isset($payload['uuid_factura']) && !isset($payload['num_visible']))) {
    usage();
}

try {
    $db = ConnectionFactory::make($config);
    $u = new UuidGenerator();
    $service = new DebtClaimCoordinator(
        $db,
        new TransactionRunner($db),
        new DebtSnapshotRepository(),
        new DebtClaimCaseRepository($u),
        new NotificationOutboxRepository($u),
        new OperationalEventRepository($u),
        ['CLI_DEBT_CLAIM'],
        ['CLI_DEBT_CLAIM']
    );
    $requestId = 'CLI-CLAIM-' . bin2hex(random_bytes(8));
    $actor = [
        'actor_type' => 'SYSTEM',
        'actor_id' => 'cli-process-debt-claim',
        'roles' => ['CLI_DEBT_CLAIM'],
        'request_id' => $requestId,
    ];
    $payload['request_id'] = $payload['request_id'] ?? $requestId;
    $payload['correlation_id'] = $payload['correlation_id'] ?? $requestId;
    $payload['idempotency_key'] = $payload['idempotency_key']
        ?? 'CLI|DEBT_CLAIM|' . hash('sha256', json_encode($payload));
    $payload['reason_code'] = $payload['reason_code'] ?? 'CLI_CONTROLLED_EXECUTION';

    $action = strtoupper((string) $payload['action']);
    $result = $action === 'RECONCILE_AFTER_PAYMENT'
        ? $service->reconcileAfterPayment($actor, $payload)
        : $service->recordNotice($actor, $payload);

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(0);
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'code' => $e->getCode()], JSON_PRETTY_PRINT), PHP_EOL;
    exit(1);
}

function parseArgs(array $args): array
{
    $out = [];
    foreach ($args as $arg) {
        $arg = (string) $arg;
        foreach ([
            '--uuid-factura=' => 'uuid_factura',
            '--num-visible=' => 'num_visible',
            '--action=' => 'action',
            '--idempotency-key=' => 'idempotency_key',
            '--reason-code=' => 'reason_code',
            '--request-id=' => 'request_id',
            '--correlation-id=' => 'correlation_id',
            '--notes=' => 'notes',
        ] as $prefix => $key) {
            if (str_starts_with($arg, $prefix)) {
                $out[$key] = trim(substr($arg, strlen($prefix)));
                continue 2;
            }
        }
    }
    return $out;
}

function usage(): void
{
    fwrite(
        STDERR,
        "Usage: php sif/scripts/process-debt-claim.php (--uuid-factura=UUID|--num-visible=NUM) --action=FINAL_REMINDER|FIRST_CLAIM|FINAL_CLAIM|RECONCILE_AFTER_PAYMENT [--idempotency-key=KEY] [--reason-code=CODE]\n"
    );
    exit(1);
}
