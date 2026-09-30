<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Repository\IncidentActionRepository;
use Prisma\Sif\Repository\IncidentRepository;
use Prisma\Sif\Service\IncidentLifecycleService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class IncidentConcurrencyTest
{
    public function testConcurrentOpenWithSameIdempotencyKeyCreatesOneIncidentAndOneOpenAction(): void
    {
        $db = TestDatabase::fresh();

        $results = $this->runConcurrentWorkers('open');

        Assert::same(0, $results['a']['exit_code']);
        Assert::same(0, $results['b']['exit_code']);

        $a = json_decode($results['a']['stdout'], true, 512, JSON_THROW_ON_ERROR);
        $b = json_decode($results['b']['stdout'], true, 512, JSON_THROW_ON_ERROR);

        Assert::same((int) $a['incident_id'], (int) $b['incident_id']);

        $reused = [(bool) $a['reused'], (bool) $b['reused']];
        sort($reused);
        Assert::same([false, true], $reused);

        Assert::same(
            1,
            (int) $db->query(
                "SELECT COUNT(*) FROM errors_verifactu
                 WHERE IDEMPOTENCY_KEY = 'TEST|UC08|CONCURRENT|OPEN'"
            )->fetchColumn()
        );
        Assert::same(
            1,
            (int) $db->query(
                "SELECT COUNT(*) FROM sif_incident_action
                 WHERE ACTION_TYPE = 'OPEN'"
            )->fetchColumn()
        );
    }

    public function testConcurrentAssignmentsAreSerializedAndLeaveCoherentHistory(): void
    {
        $db = TestDatabase::fresh();
        $opened = $this->service($db)->open($this->manager(), [
            'type' => 'CONCURRENT_ASSIGN',
            'message' => 'Concurrent assignment test',
            'severity' => 'MEDIUM',
            'resource_type' => 'TEST_CASE',
            'resource_id' => 'UC008-CONCURRENT-ASSIGN',
            'source_type' => 'TEST_CONCURRENCY',
            'source_id' => 'UC008-CONCURRENT-ASSIGN',
            'reason_code' => 'CONCURRENT_ASSIGN',
            'correlation_id' => 'UC008-CONCURRENT-ASSIGN',
            'idempotency_key' => 'TEST|UC08|CONCURRENT|ASSIGN|OPEN',
        ]);

        $results = $this->runConcurrentWorkers('assign', (int) $opened['incident_id']);

        Assert::same(0, $results['a']['exit_code']);
        Assert::same(0, $results['b']['exit_code']);

        $a = json_decode($results['a']['stdout'], true, 512, JSON_THROW_ON_ERROR);
        $b = json_decode($results['b']['stdout'], true, 512, JSON_THROW_ON_ERROR);
        Assert::same('IN_PROGRESS', $a['status']);
        Assert::same('IN_PROGRESS', $b['status']);

        $incident = $db->query(
            'SELECT ESTAT, SEVERITY, ASSIGNED_TO
             FROM errors_verifactu
             WHERE ID = ' . (int) $opened['incident_id']
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('IN_PROGRESS', $incident['ESTAT']);
        if (!in_array($incident['ASSIGNED_TO'], ['assignee-a', 'assignee-b'], true)) {
            Assert::fail('Unexpected final concurrent assignee');
        }
        if (!in_array($incident['SEVERITY'], ['HIGH', 'CRITICAL'], true)) {
            Assert::fail('Unexpected final concurrent severity');
        }

        $actions = $db->query(
            "SELECT PREVIOUS_STATUS, NEW_STATUS, ASSIGNEE_ID, REASON_CODE
             FROM sif_incident_action
             WHERE INCIDENT_ID = " . (int) $opened['incident_id'] . "
               AND ACTION_TYPE = 'ASSIGN'
             ORDER BY ID ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);

        Assert::same(2, count($actions));
        Assert::same('IN_PROGRESS', $actions[0]['NEW_STATUS']);
        Assert::same('IN_PROGRESS', $actions[1]['NEW_STATUS']);

        $previous = [$actions[0]['PREVIOUS_STATUS'], $actions[1]['PREVIOUS_STATUS']];
        sort($previous);
        Assert::same(['IN_PROGRESS', 'OPEN'], $previous);

        $assignees = [$actions[0]['ASSIGNEE_ID'], $actions[1]['ASSIGNEE_ID']];
        sort($assignees);
        Assert::same(['assignee-a', 'assignee-b'], $assignees);
    }

    private function runConcurrentWorkers(string $mode, int $incidentId = 0): array
    {
        $barrier = sys_get_temp_dir() . '/uc008-' . bin2hex(random_bytes(8));
        if (!mkdir($barrier, 0700, true) && !is_dir($barrier)) {
            throw new \RuntimeException('Could not create concurrency test barrier');
        }

        $processes = [];
        try {
            foreach (['a', 'b'] as $worker) {
                $command = [
                    PHP_BINARY,
                    dirname(__DIR__) . '/Support/ConcurrentIncidentWorker.php',
                    $mode,
                    $barrier,
                    $worker,
                    (string) $incidentId,
                ];
                $pipes = [];
                $process = proc_open(
                    $command,
                    [
                        0 => ['pipe', 'r'],
                        1 => ['pipe', 'w'],
                        2 => ['pipe', 'w'],
                    ],
                    $pipes,
                    dirname(__DIR__, 3),
                    getenv(),
                    ['bypass_shell' => true]
                );
                if (!is_resource($process)) {
                    throw new \RuntimeException('Could not start concurrency worker ' . $worker);
                }
                fclose($pipes[0]);
                $processes[$worker] = ['process' => $process, 'pipes' => $pipes];
            }

            $deadline = microtime(true) + 10;
            while (!is_file($barrier . '/ready-a') || !is_file($barrier . '/ready-b')) {
                if (microtime(true) >= $deadline) {
                    throw new \RuntimeException('Concurrent workers did not reach barrier');
                }
                usleep(10000);
            }

            touch($barrier . '/go');

            $results = [];
            foreach ($processes as $worker => $entry) {
                $stdout = stream_get_contents($entry['pipes'][1]);
                $stderr = stream_get_contents($entry['pipes'][2]);
                fclose($entry['pipes'][1]);
                fclose($entry['pipes'][2]);
                $exitCode = proc_close($entry['process']);
                $results[$worker] = [
                    'exit_code' => $exitCode,
                    'stdout' => trim((string) $stdout),
                    'stderr' => trim((string) $stderr),
                ];
            }

            foreach ($results as $worker => $result) {
                if ($result['exit_code'] !== 0) {
                    Assert::fail(
                        'Concurrency worker ' . $worker . ' failed: ' . $result['stderr']
                    );
                }
            }

            return $results;
        } finally {
            foreach ($processes as $entry) {
                if (is_resource($entry['process'])) {
                    @proc_terminate($entry['process']);
                }
                foreach ($entry['pipes'] as $pipe) {
                    if (is_resource($pipe)) {
                        @fclose($pipe);
                    }
                }
            }
            foreach (glob($barrier . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($barrier);
        }
    }

    private function service(\PDO $db): IncidentLifecycleService
    {
        return new IncidentLifecycleService(
            $db,
            new TransactionRunner($db),
            new IncidentRepository(),
            new IncidentActionRepository(),
            ['AUDITOR_FISCAL', 'SIF_ADMIN'],
            ['SIF_ADMIN']
        );
    }

    private function manager(): array
    {
        return [
            'actor_id' => 'concurrency-manager',
            'roles' => ['SIF_ADMIN'],
            'request_id' => 'concurrency-parent',
        ];
    }
}
