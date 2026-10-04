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

// Test helper dependency used by several UC-002 integration tests.
// Load it before the class snapshot so its own test methods are not included
// in the selective UC-002 execution.
require_once __DIR__ . '/Integration/IssueInvoiceTest.php';

$relativeFiles = [
    'Integration/RegisterPaymentTest.php',
    'Integration/PayloadIdempotencyFlowTest.php',
    'Integration/ManualPaymentServiceTest.php',
    'Integration/InternalApiAuthenticatorTest.php',
    'Integration/Uc002PaymentApiBoundaryTest.php',
    'Integration/Uc002LegacyPaymentBoundaryTest.php',
    'Integration/Uc002LegacyExistingInvoiceTest.php',
    'Integration/ExistingInvoicePaymentCommandServiceTest.php',
    'Integration/ExistingInvoiceEnrollmentFundAllocationServiceTest.php',
    'Integration/ExistingInvoiceLegacyProjectionServiceTest.php',
    'Integration/ExistingInvoicePaymentAuditFlowTest.php',
    'Integration/Uc002ExistingInvoiceCommandTest.php',
    'Integration/Uc002AuthoritativeBridgeBoundaryTest.php',
    'Unit/PaymentStatusCalculatorTest.php',
    'Unit/PaymentPayloadValidatorTest.php',
    'Unit/ManualPaymentPayloadBuilderTest.php',
    'Unit/TransactionRunnerTest.php',
    'Unit/PaymentActionGatewayTest.php',
    'Unit/PaymentActionEventRepositoryTest.php',
];

$before = get_declared_classes();

foreach ($relativeFiles as $relativeFile) {
    $path = __DIR__ . '/' . $relativeFile;
    if (!is_file($path)) {
        fwrite(STDERR, "[MISSING TEST FILE] {$relativeFile}" . PHP_EOL);
        exit(1);
    }
    require_once $path;
}

$classes = array_values(array_filter(
    array_diff(get_declared_classes(), $before),
    static fn (string $class): bool => str_starts_with($class, 'Prisma\\Sif\\Tests\\')
        && str_ends_with($class, 'Test')
));

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

echo "\nUC-002 selective suite: {$passed} passed, {$failed} failed\n";
exit($failed > 0 || $passed === 0 ? 1 : 0);
