<?php

require dirname(__DIR__) . '/bootstrap.php';

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Repository\IncidentActionRepository;
use Prisma\Sif\Repository\IncidentRepository;
use Prisma\Sif\Service\IncidentLifecycleService;
use Prisma\Sif\Tests\Support\TestDatabase;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$mode = (string) ($argv[1] ?? '');
$barrierDir = (string) ($argv[2] ?? '');
$worker = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($argv[3] ?? 'worker')) ?: 'worker';
$incidentId = isset($argv[4]) ? (int) $argv[4] : 0;

if (!in_array($mode, ['open', 'assign'], true) || $barrierDir === '') {
    fwrite(STDERR, "Invalid worker arguments\n");
    exit(2);
}

try {
    $db = TestDatabase::connect();
    $service = new IncidentLifecycleService(
        $db,
        new TransactionRunner($db),
        new IncidentRepository(),
        new IncidentActionRepository(),
        ['AUDITOR_FISCAL', 'SIF_ADMIN'],
        ['SIF_ADMIN']
    );

    if (!is_dir($barrierDir) && !mkdir($barrierDir, 0700, true) && !is_dir($barrierDir)) {
        throw new RuntimeException('Could not create barrier directory');
    }

    file_put_contents($barrierDir . '/ready-' . $worker, (string) getmypid());

    $deadline = microtime(true) + 10;
    while (!is_file($barrierDir . '/go')) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Concurrency barrier timeout');
        }
        usleep(10000);
    }

    $actor = [
        'actor_id' => 'concurrency-manager',
        'roles' => ['SIF_ADMIN'],
        'request_id' => 'concurrency-' . $worker,
    ];

    if ($mode === 'open') {
        $result = $service->open($actor, [
            'type' => 'CONCURRENT_OPEN',
            'message' => 'Concurrent open test',
            'severity' => 'HIGH',
            'resource_type' => 'TEST_CASE',
            'resource_id' => 'UC008-CONCURRENT-OPEN',
            'source_type' => 'TEST_CONCURRENCY',
            'source_id' => 'UC008-CONCURRENT-OPEN',
            'reason_code' => 'CONCURRENT_OPEN',
            'correlation_id' => 'UC008-CONCURRENT-OPEN',
            'idempotency_key' => 'TEST|UC08|CONCURRENT|OPEN',
        ]);
    } else {
        if ($incidentId < 1) {
            throw new RuntimeException('Missing incident id for assign worker');
        }
        $suffix = strtoupper($worker);
        $result = $service->assign($actor, $incidentId, [
            'assignee_id' => 'assignee-' . strtolower($worker),
            'severity' => $worker === 'a' ? 'HIGH' : 'CRITICAL',
            'reason_code' => 'CONCURRENT_ASSIGN_' . $suffix,
            'details' => 'Concurrent assign ' . $suffix,
            'correlation_id' => 'UC008-CONCURRENT-ASSIGN-' . $suffix,
            'idempotency_key' => 'TEST|UC08|CONCURRENT|ASSIGN|' . $suffix,
        ]);
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class . ': ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
