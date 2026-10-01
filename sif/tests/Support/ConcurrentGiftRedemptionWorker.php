<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\CommercialEntitlementRepository;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Service\GiftRedemptionService;
use Prisma\Sif\Tests\Support\TestDatabase;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$barrierDir = (string) ($argv[1] ?? '');
$worker = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($argv[2] ?? 'worker')) ?: 'worker';
$code = (string) ($argv[3] ?? '');
$holder = (string) ($argv[4] ?? '');
$destination = (string) ($argv[5] ?? '');
$idempotencyKey = (string) ($argv[6] ?? '');

if ($barrierDir === ''
    || $code === ''
    || $holder === ''
    || $destination === ''
    || $idempotencyKey === ''
) {
    fwrite(STDERR, "Invalid worker arguments\n");
    exit(2);
}

try {
    $db = TestDatabase::connect();
    $service = new GiftRedemptionService(
        new CommercialEntitlementRepository(new UuidGenerator()),
        new EnrollmentFundMovementRepository(new UuidGenerator())
    );

    if (!is_dir($barrierDir)
        && !mkdir($barrierDir, 0700, true)
        && !is_dir($barrierDir)
    ) {
        throw new RuntimeException('Could not create concurrency barrier');
    }

    file_put_contents($barrierDir . '/ready-' . $worker, (string) getmypid());

    $deadline = microtime(true) + 10;
    while (!is_file($barrierDir . '/go')) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Gift concurrency barrier timeout');
        }
        usleep(10000);
    }

    $result = $service->redeem($db, [
        'code' => $code,
        'holder_party_key' => $holder,
        'destination_operation_uuid' => $destination,
        'idempotency_key' => $idempotencyKey,
        'correlation_id' => 'UC018-CONCURRENT-' . strtoupper($worker),
        'actor_id' => 'gift-concurrency-' . $worker,
    ]);

    echo json_encode(
        ['ok' => true, 'result' => $result],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ), PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'class' => $exception::class,
        'code' => $exception->getCode(),
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(1);
}
