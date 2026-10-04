<?php

require __DIR__ . '/bootstrap.php';

use Prisma\Sif\Tests\Support\TestDatabase;

try {
    foreach (['pdo_mysql', 'openssl', 'mbstring'] as $extension) {
        if (!extension_loaded($extension)) {
            throw new RuntimeException("Missing PHP extension: {$extension}");
        }
    }

    $testLock = TestDatabase::connect();
    $lockName = 'sif_uc009_tests:' . $testLock->query('SELECT DATABASE()')->fetchColumn();
    $lock = $testLock->prepare('SELECT GET_LOCK(?, 0)');
    $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('Another UC-009 test suite is using this database.');
    }

    TestDatabase::fresh();
} catch (Throwable $exception) {
    fwrite(STDERR, '[INFRASTRUCTURE FAIL] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

$classes = [
    Prisma\Sif\Tests\Database\SifSchemaTest::class,
    Prisma\Sif\Tests\Integration\AeatEvidenceReconciliationServiceTest::class,
    Prisma\Sif\Tests\Integration\AeatIntranetUiContractTest::class,
    Prisma\Sif\Tests\Integration\AeatOperationsReadRepositoryTest::class,
    Prisma\Sif\Tests\Integration\AeatReviewReconciliationServiceTest::class,
    Prisma\Sif\Tests\Integration\AeatWorkerPreflightScriptTest::class,
    Prisma\Sif\Tests\Integration\AeatWorkflowTest::class,
    Prisma\Sif\Tests\Integration\FiscalQueueMetricsRepositoryTest::class,
    Prisma\Sif\Tests\Integration\FiscalQueueProcessorTest::class,
    Prisma\Sif\Tests\Integration\InternalApiAuthenticatorTest::class,
    Prisma\Sif\Tests\Integration\PayloadIdempotencyFlowTest::class,
    Prisma\Sif\Tests\Unit\AeatIntegrityTest::class,
    Prisma\Sif\Tests\Unit\AeatPreflightTest::class,
    Prisma\Sif\Tests\Unit\AeatProtocolTest::class,
    Prisma\Sif\Tests\Unit\AeatSecurityTest::class,
];

$passed = 0;
$failed = 0;

foreach ($classes as $class) {
    if (!class_exists($class)) {
        echo "[FAIL] {$class}: test class not found\n";
        $failed++;
        continue;
    }

    $reflection = new ReflectionClass($class);
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

echo "\nUC-009: {$passed} passed, {$failed} failed\n";
exit($failed > 0 || $passed === 0 ? 1 : 0);
