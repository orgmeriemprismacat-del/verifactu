<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\CommercialEntitlementRepository;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Service\GiftEnrollmentStager;
use Prisma\Sif\Service\GiftRedemptionOrchestrator;
use Prisma\Sif\Service\GiftRedemptionService;
use Prisma\Sif\Service\GiftRedemptionTrustedContextResolver;
use Prisma\Sif\Service\LegacyGiftUsageReconciler;
use Prisma\Sif\Tests\Support\TestDatabase;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$barrierDir = (string) ($argv[1] ?? '');
$worker = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($argv[2] ?? 'worker'))
    ?: 'worker';
$enrollmentId = isset($argv[3]) ? (int) $argv[3] : 0;
$giftCode = trim((string) ($argv[4] ?? ''));

if ($barrierDir === '' || $enrollmentId < 1 || $giftCode === '') {
    fwrite(STDERR, "Invalid worker arguments\n");
    exit(2);
}

try {
    $db = TestDatabase::connect();
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

    if (!is_dir($barrierDir)
        && !mkdir($barrierDir, 0700, true)
        && !is_dir($barrierDir)
    ) {
        throw new RuntimeException('Could not create gift concurrency barrier');
    }

    file_put_contents($barrierDir . '/ready-' . $worker, (string) getmypid());

    $deadline = microtime(true) + 10;
    while (!is_file($barrierDir . '/go')) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Gift concurrency barrier timeout');
        }
        usleep(10000);
    }

    $result = $orchestrator->execute(
        $db,
        $db,
        $enrollmentId,
        $giftCode,
        'WEB',
        'UC018-CONCURRENT-' . strtoupper($worker),
        'gift-concurrency-' . strtolower($worker)
    );

    echo json_encode(
        [
            'stage_operation' => (string) $result['stage']['uuid_operation'],
            'stage_reused' => (bool) $result['stage']['idempotency_reused'],
            'redemption_status' => (string) $result['redemption']['status'],
            'fund_movement_uuid' => (string) $result['redemption']['fund_movement_uuid'],
            'redemption_reused' => (bool) $result['redemption']['idempotency_reused'],
            'legacy_status' => (string) $result['legacy_reconciliation']['status'],
            'legacy_reused' => (bool) $result['legacy_reconciliation']['idempotency_reused'],
        ],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class . ': ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
