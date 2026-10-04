<?php

require __DIR__ . '/bootstrap.php';

try {
    foreach (['pdo_mysql', 'openssl', 'mbstring'] as $extension) {
        if (!extension_loaded($extension)) {
            throw new RuntimeException("Missing PHP extension: {$extension}");
        }
    }

    $testLock = \Prisma\Sif\Tests\Support\TestDatabase::connect();
    $lockName = 'uc021_tests:' . $testLock->query('SELECT DATABASE()')->fetchColumn();
    $lock = $testLock->prepare('SELECT GET_LOCK(?, 0)');
    $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('Another UC-021 test suite is using this database.');
    }

    \Prisma\Sif\Tests\Support\TestDatabase::fresh();
} catch (Throwable $exception) {
    fwrite(STDERR, '[INFRASTRUCTURE FAIL] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

$testFiles = array_merge(
    glob(__DIR__ . '/Integration/InvoiceBeforePayment*Test.php') ?: [],
    glob(__DIR__ . '/Integration/Uc021*Test.php') ?: [],
    [
        __DIR__ . '/Integration/RedsysCoursePaymentIntentServiceTest.php',
        __DIR__ . '/Integration/RedsysCourseInvoiceServiceTest.php',
        __DIR__ . '/Integration/ManualPaymentServiceTest.php',
    ]
);

$testFiles = array_values(array_unique(array_filter($testFiles, 'is_file')));
foreach ($testFiles as $file) {
    require_once $file;
}

$classes = array_filter(
    get_declared_classes(),
    static fn (string $class): bool => str_starts_with($class, 'Prisma\\Sif\\Tests\\')
        && str_ends_with($class, 'Test')
);

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

echo "\nUC-021: {$passed} passed, {$failed} failed\n";
exit($failed > 0 || $passed === 0 ? 1 : 0);
