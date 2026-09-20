<?php

require __DIR__ . '/bootstrap.php';

try {
    foreach (['pdo_mysql', 'openssl', 'mbstring'] as $extension) {
        if (!extension_loaded($extension)) {
            throw new RuntimeException("Missing PHP extension: {$extension}");
        }
    }
    $testLock = \Prisma\Sif\Tests\Support\TestDatabase::connect();
    $lockName = 'sif_tests:' . $testLock->query('SELECT DATABASE()')->fetchColumn();
    $lock = $testLock->prepare('SELECT GET_LOCK(?, 0)');
    $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('Another test suite is using this database.');
    }
    // Fail once before loading tests if migrations or configuration are broken.
    \Prisma\Sif\Tests\Support\TestDatabase::fresh();
} catch (Throwable $exception) {
    fwrite(STDERR, '[INFRASTRUCTURE FAIL] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

$testFiles = array_merge(
    glob(__DIR__ . '/Database/*Test.php') ?: [],
    glob(__DIR__ . '/Integration/*Test.php') ?: [],
    glob(__DIR__ . '/Unit/*Test.php') ?: []
);

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

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 || $passed === 0 ? 1 : 0);

