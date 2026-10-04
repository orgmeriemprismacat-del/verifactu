<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Prisma\Sif\Tests\Support\TestDatabase;

try {
    foreach (['pdo_mysql', 'openssl', 'mbstring'] as $extension) {
        if (!extension_loaded($extension)) {
            throw new RuntimeException("Missing PHP extension: {$extension}");
        }
    }

    $testLock = TestDatabase::connect();
    $database = (string) $testLock->query('SELECT DATABASE()')->fetchColumn();
    $lockName = 'sif_uc018_tests:' . $database;
    $lock = $testLock->prepare('SELECT GET_LOCK(?, 0)');
    $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('Another UC-018 test suite is using this database.');
    }

    TestDatabase::fresh();
} catch (Throwable $exception) {
    fwrite(STDERR, '[INFRASTRUCTURE FAIL] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

$relativeFiles = [
    'Integration/GiftCodeGenerationBoundaryTest.php',
    'Integration/GiftRedemptionConfirmationBoundaryTest.php',
    'Integration/GiftEnrollmentStagerTest.php',
    'Integration/GiftRedemptionConcurrencyTest.php',
    'Integration/GiftRedemptionEndToEndTest.php',
    'Integration/GiftRedemptionEndpointBoundaryTest.php',
    'Integration/GiftRedemptionLegacyMailBoundaryTest.php',
    'Integration/GiftRedemptionNotificationBundleServiceTest.php',
    'Integration/GiftRedemptionPreproductionBoundaryTest.php',
    'Integration/GiftRedemptionRecoveryCliBoundaryTest.php',
    'Integration/GiftRedemptionServiceTest.php',
    'Integration/GiftRedemptionTrustedContextResolverTest.php',
    'Integration/GiftRedemptionWebClientBoundaryTest.php',
    'Integration/HistoricalGiftEntitlementBackfillServiceTest.php',
    'Integration/HistoricalGiftEntitlementPreflightScriptTest.php',
    'Integration/LegacyGiftUsageReconcilerTest.php',
];

foreach ($relativeFiles as $relativeFile) {
    $file = __DIR__ . '/' . $relativeFile;
    if (!is_file($file)) {
        fwrite(STDERR, '[INFRASTRUCTURE FAIL] Missing UC-018 test file: ' . $relativeFile . PHP_EOL);
        exit(1);
    }
    require_once $file;
}

$classes = array_values(array_filter(
    get_declared_classes(),
    static fn (string $class): bool =>
        str_starts_with($class, 'Prisma\\Sif\\Tests\\')
        && str_ends_with($class, 'Test')
));

sort($classes, SORT_STRING);

$passed = 0;
$failed = 0;

foreach ($classes as $class) {
    $reflection = new ReflectionClass($class);
    if ($reflection->isAbstract()) {
        continue;
    }

    $instance = $reflection->newInstance();
    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if (!str_starts_with($method->getName(), 'test')) {
            continue;
        }

        $name = $class . '::' . $method->getName();
        try {
            $method->invoke($instance);
            echo "[PASS] {$name}\n";
            $passed++;
        } catch (Throwable $exception) {
            echo "[FAIL] {$name}: {$exception->getMessage()}\n";
            $failed++;
        }
    }
}

echo "\nUC-018: {$passed} passed, {$failed} failed\n";
exit($failed > 0 || $passed === 0 ? 1 : 0);
