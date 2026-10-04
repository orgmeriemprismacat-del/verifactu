<?php

require __DIR__ . '/bootstrap.php';

try {
    foreach (['pdo_mysql', 'openssl', 'mbstring'] as $extension) {
        if (!extension_loaded($extension)) {
            throw new RuntimeException("Missing PHP extension: {$extension}");
        }
    }

    $testLock = \Prisma\Sif\Tests\Support\TestDatabase::connect();
    $lockName = 'sif_tests_uc005:' . $testLock->query('SELECT DATABASE()')->fetchColumn();
    $lock = $testLock->prepare('SELECT GET_LOCK(?, 0)');
    $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('Another UC-005 test suite is using this database.');
    }

    \Prisma\Sif\Tests\Support\TestDatabase::fresh();
} catch (Throwable $exception) {
    fwrite(STDERR, '[INFRASTRUCTURE FAIL] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

$files = [
    __DIR__ . '/Integration/IssueInvoiceTest.php',
    __DIR__ . '/Integration/ManualRectificationServiceTest.php',
    __DIR__ . '/Integration/ManualRectificationAtomicityTest.php',
    __DIR__ . '/Integration/ManualRectificationFiscalTest.php',
    __DIR__ . '/Integration/ManualRectificationAeatMappingTest.php',
    __DIR__ . '/Integration/RectificationCommandServiceTest.php',
    __DIR__ . '/Integration/RectificationHttpEndpointTest.php',
    __DIR__ . '/Integration/RectificationIntranetProxyContractTest.php',
    __DIR__ . '/Integration/FiscalCorrectionDecisionResolverTest.php',
    __DIR__ . '/Unit/ManualRectificationPayloadBuilderTest.php',
    __DIR__ . '/Unit/FiscalCorrectionDecisionGuardTest.php',
    __DIR__ . '/Unit/InternalRectificationScopeResolverTest.php',
    __DIR__ . '/Unit/AeatRectificationProtocolTest.php',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, '[TEST FILE MISSING] ' . $file . PHP_EOL);
        exit(1);
    }

    require_once $file;
}

$classes = [
    \Prisma\Sif\Tests\Integration\ManualRectificationServiceTest::class,
    \Prisma\Sif\Tests\Integration\ManualRectificationAtomicityTest::class,
    \Prisma\Sif\Tests\Integration\ManualRectificationFiscalTest::class,
    \Prisma\Sif\Tests\Integration\ManualRectificationAeatMappingTest::class,
    \Prisma\Sif\Tests\Integration\RectificationCommandServiceTest::class,
    \Prisma\Sif\Tests\Integration\RectificationHttpEndpointTest::class,
    \Prisma\Sif\Tests\Integration\RectificationIntranetProxyContractTest::class,
    \Prisma\Sif\Tests\Integration\FiscalCorrectionDecisionResolverTest::class,
    \Prisma\Sif\Tests\Unit\ManualRectificationPayloadBuilderTest::class,
    \Prisma\Sif\Tests\Unit\FiscalCorrectionDecisionGuardTest::class,
    \Prisma\Sif\Tests\Unit\InternalRectificationScopeResolverTest::class,
    \Prisma\Sif\Tests\Unit\AeatRectificationProtocolTest::class,
];

$passed = 0;
$failed = 0;

foreach ($classes as $class) {
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

echo "\nUC-005: {$passed} passed, {$failed} failed\n";
exit($failed > 0 || $passed === 0 ? 1 : 0);
