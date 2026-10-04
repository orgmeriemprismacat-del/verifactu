<?php

require __DIR__ . '/bootstrap.php';

try {
    foreach (['pdo_mysql', 'openssl', 'mbstring'] as $extension) {
        if (!extension_loaded($extension)) {
            throw new RuntimeException("Missing PHP extension: {$extension}");
        }
    }
    PrismaSifTestsSupportTestDatabase::fresh();
} catch (Throwable $exception) {
    fwrite(STDERR, '[INFRASTRUCTURE FAIL] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

$testFiles = [
    __DIR__ . '/Integration/IssueInvoiceTest.php',
    __DIR__ . '/Integration/RegisterPaymentTest.php',
    __DIR__ . '/Integration/ManualPaymentServiceTest.php',
    __DIR__ . '/Integration/ManualTransferCommandServiceTest.php',
    __DIR__ . '/Unit/ManualPaymentPayloadBuilderTest.php',
];

foreach ($testFiles as $file) {
    require_once $file;
}

$classes = [
    PrismaSifTestsIntegrationManualPaymentServiceTest::class,
    PrismaSifTestsIntegrationManualTransferCommandServiceTest::class,
    PrismaSifTestsUnitManualPaymentPayloadBuilderTest::class,
];

$passed = 0;
$failed = 0;

foreach ($classes as $class) {
    $instance = new $class();
    $reflection = new ReflectionClass($class);

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

echo "\nUC-022: {$passed} passed, {$failed} failed\n";
exit($failed > 0 || $passed === 0 ? 1 : 0);
