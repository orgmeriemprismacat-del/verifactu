<?php

// Offline protocol tests, independent of MySQL and the legacy test suite.
require dirname(__DIR__) . '/tests/bootstrap.php';
$tests = ['Prisma\\Sif\\Tests\\Unit\\AeatProtocolTest', 'Prisma\\Sif\\Tests\\Unit\\AeatSecurityTest',
    'Prisma\\Sif\\Tests\\Unit\\FiscalRecordArgumentsTest', 'Prisma\\Sif\\Tests\\Unit\\AeatIntegrityTest'];
if (in_array('--integration', $argv ?? [], true)) {
    $tests[] = 'Prisma\\Sif\\Tests\\Integration\\AeatWorkflowTest';
    $tests[] = 'Prisma\\Sif\\Tests\\Integration\\FiscalRecordCliTest';
}
$passed = 0;
$failed = 0;
foreach ($tests as $class) {
    $instance = new $class();
    foreach (get_class_methods($instance) as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }
        try {
            $instance->$method();
            echo "[PASS] $method\n";
            $passed++;
        } catch (\Throwable $error) {
            echo "[FAIL] $method: " . $error->getMessage() . "\n";
            $failed++;
        }
    }
}
echo "$passed passed, $failed failed (offline; no AEAT submission)\n";
exit($failed ? 1 : 0);
