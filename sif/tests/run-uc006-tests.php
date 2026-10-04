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
    $lockName = 'sif_tests:' . $testLock->query('SELECT DATABASE()')->fetchColumn();
    $lock = $testLock->prepare('SELECT GET_LOCK(?, 0)');
    $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('Another test suite is using this database.');
    }

    TestDatabase::fresh();
} catch (Throwable $exception) {
    fwrite(STDERR, '[INFRASTRUCTURE FAIL] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

$relativeFiles = [
    'Integration/IssueInvoiceTest.php',
    'Integration/RegisterPaymentTest.php',
    'Integration/CourseEnrollmentFundAllocationServiceTest.php',
    'Integration/EnrollmentFundTransferServiceTest.php',
    'Integration/EnrollmentFundTransferPreviewScriptTest.php',
    'Integration/EnrollmentFundTransferPreproductionScriptTest.php',
    'Integration/CreditBalanceServiceTest.php',
    'Integration/ManualRefundServiceTest.php',
    'Integration/CreditBalancePreviewScriptTest.php',
    'Integration/CreditBalancePreproductionScriptTest.php',
    'Integration/ManualRefundPreviewScriptTest.php',
    'Integration/ManualRefundPreproductionScriptTest.php',
    'Integration/CreditCompensationPreviewScriptTest.php',
    'Integration/CreditCompensationPreproductionScriptTest.php',
];

foreach ($relativeFiles as $relativeFile) {
    $path = __DIR__ . '/' . $relativeFile;
    if (!is_file($path)) {
        fwrite(STDERR, "[MISSING TEST FILE] {$relativeFile}" . PHP_EOL);
        exit(1);
    }
    require_once $path;
}

$classes = [
    Prisma\Sif\Tests\Integration\CourseEnrollmentFundAllocationServiceTest::class,
    Prisma\Sif\Tests\Integration\EnrollmentFundTransferServiceTest::class,
    Prisma\Sif\Tests\Integration\EnrollmentFundTransferPreviewScriptTest::class,
    Prisma\Sif\Tests\Integration\EnrollmentFundTransferPreproductionScriptTest::class,
    Prisma\Sif\Tests\Integration\CreditBalanceServiceTest::class,
    Prisma\Sif\Tests\Integration\ManualRefundServiceTest::class,
    Prisma\Sif\Tests\Integration\CreditBalancePreviewScriptTest::class,
    Prisma\Sif\Tests\Integration\CreditBalancePreproductionScriptTest::class,
    Prisma\Sif\Tests\Integration\ManualRefundPreviewScriptTest::class,
    Prisma\Sif\Tests\Integration\ManualRefundPreproductionScriptTest::class,
    Prisma\Sif\Tests\Integration\CreditCompensationPreviewScriptTest::class,
    Prisma\Sif\Tests\Integration\CreditCompensationPreproductionScriptTest::class,
];

$passed = 0;
$failed = 0;

foreach ($classes as $class) {
    if (!class_exists($class)) {
        fwrite(STDERR, "[MISSING TEST CLASS] {$class}" . PHP_EOL);
        exit(1);
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

echo "\nUC-006 selective suite: {$passed} passed, {$failed} failed\n";
exit($failed > 0 || $passed === 0 ? 1 : 0);
