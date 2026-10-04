<?php

require __DIR__ . '/bootstrap.php';

try {
    foreach (['pdo_mysql', 'openssl', 'mbstring'] as $extension) {
        if (!extension_loaded($extension)) {
            throw new RuntimeException("Missing PHP extension: {$extension}");
        }
    }
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

$classes = [
    'Prisma\\Sif\\Tests\\Integration\\ManualPaymentServiceTest',
    'Prisma\\Sif\\Tests\\Integration\\ManualTransferCommandServiceTest',
    'Prisma\\Sif\\Tests\\Integration\\ManualTransferAuditedFlowTest',
    'Prisma\\Sif\\Tests\\Integration\\ManualTransferHttpEndpointTest',
    'Prisma\\Sif\\Tests\\Integration\\ManualTransferIntranetAdapterTest',
    'Prisma\\Sif\\Tests\\Integration\\ManualTransferNotificationServiceTest',
    'Prisma\\Sif\\Tests\\Unit\\ManualPaymentPayloadBuilderTest',
    'Prisma\\Sif\\Tests\\Unit\\GeneratedInvoiceLegacyPaymentSyncServiceTest',
];

$passed = 0;
$failed = 0;

foreach ($classes as $class) {
    if (!class_exists($class)) {
        echo "[FAIL] {$class}: class not found\n";
        $failed++;
        continue;
    }

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
