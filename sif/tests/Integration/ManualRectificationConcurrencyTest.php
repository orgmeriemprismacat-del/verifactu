<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\RectificationRepository;
use Prisma\Sif\Service\ManualRectificationPayloadBuilder;
use Prisma\Sif\Service\ManualRectificationService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ManualRectificationConcurrencyTest
{
    public function testConcurrentEquivalentRectificationNeverCreatesSecondR(): void
    {
        $db = TestDatabase::fresh();
        $original = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'UC005|CONCURRENT|ORIGINAL',
        ]));

        $barrier = sys_get_temp_dir() . '/uc005-' . bin2hex(random_bytes(8));
        if (!mkdir($barrier, 0700, true) && !is_dir($barrier)) {
            throw new \RuntimeException('Could not create UC-005 concurrency barrier');
        }

        $processes = [];

        try {
            $processes['a'] = $this->startWorker(
                $barrier,
                'a',
                $original['uuid_factura'],
                true
            );
            $this->waitFor($barrier . '/locked-a', 'first UC-005 worker did not hold transaction lock');

            $processes['b'] = $this->startWorker(
                $barrier,
                'b',
                $original['uuid_factura'],
                false
            );
            $this->waitFor($barrier . '/started-b', 'second UC-005 worker did not start');

            // Give worker B time to enter the conflicting transaction while A still holds
            // the original invoice and rectification transaction open.
            usleep(200000);
            touch($barrier . '/release-a');

            $results = [];
            foreach ($processes as $name => $entry) {
                $results[$name] = $this->finishWorker($name, $entry);
            }

            Assert::same(true, $results['a']['ok']);
            if (($results['b']['ok'] ?? false) === true) {
                Assert::same(true, (bool) $results['b']['idempotency_reused']);
                Assert::same($results['a']['uuid_factura'], $results['b']['uuid_factura']);
            } else {
                Assert::same(409, (int) ($results['b']['code'] ?? 0));
            }

            Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
            Assert::same(
                1,
                (int) $db->query("SELECT COUNT(*) FROM factura WHERE TIPUS_SERIE = 'R'")->fetchColumn()
            );
            Assert::same(
                1,
                (int) $db->query('SELECT COUNT(*) FROM factura_rectificacio')->fetchColumn()
            );
            Assert::same(
                1,
                (int) $db->query(
                    "SELECT LAST_NUM FROM fiscal_sequence WHERE TIPUS_SERIE = 'R' AND ANY_FACT = 2026"
                )->fetchColumn()
            );
            Assert::same(
                'RECTIFIED',
                (string) $db->query(
                    "SELECT ESTAT_FACTURA FROM factura WHERE UUID_FACTURA = "
                    . $db->quote($original['uuid_factura'])
                )->fetchColumn()
            );

            $retry = $this->service($db)->issueByUuid($db, $original['uuid_factura'], [
                'amount' => '-40.00',
                'reason' => 'CONCURRENT_RECTIFICATION',
                'mode' => 'DIFERENCIES',
                'concept' => 'Rectificacio concurrent UC-005',
                'reference' => 'UC005-CONCURRENT-SAME-CORRECTION',
            ]);

            Assert::same(true, (bool) $retry['idempotency_reused']);
            Assert::same($results['a']['uuid_factura'], $retry['uuid_factura']);
            Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
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

    private function startWorker(
        string $barrier,
        string $name,
        string $uuidFactura,
        bool $holdLock
    ): array {
        $command = [
            PHP_BINARY,
            dirname(__DIR__) . '/Support/ConcurrentRectificationWorker.php',
            $barrier,
            $name,
            $uuidFactura,
            $holdLock ? '1' : '0',
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
            throw new \RuntimeException('Could not start UC-005 concurrency worker ' . $name);
        }
        fclose($pipes[0]);

        return ['process' => $process, 'pipes' => $pipes];
    }

    private function finishWorker(string $name, array $entry): array
    {
        $stdout = trim((string) stream_get_contents($entry['pipes'][1]));
        $stderr = trim((string) stream_get_contents($entry['pipes'][2]));
        fclose($entry['pipes'][1]);
        fclose($entry['pipes'][2]);

        $exitCode = proc_close($entry['process']);
        if ($exitCode !== 0) {
            Assert::fail('UC-005 concurrency worker ' . $name . ' failed: ' . $stderr);
        }

        $payload = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            Assert::fail('UC-005 concurrency worker returned invalid JSON');
        }

        return $payload;
    }

    private function waitFor(string $path, string $message): void
    {
        $deadline = microtime(true) + 10;
        while (!is_file($path)) {
            if (microtime(true) >= $deadline) {
                throw new \RuntimeException($message);
            }
            usleep(10000);
        }
    }

    private function service(\PDO $db): ManualRectificationService
    {
        return new ManualRectificationService(
            new ManualPaymentInvoiceRepository(),
            new RectificationRepository(),
            new ManualRectificationPayloadBuilder(),
            IssueInvoiceTest::serviceFor($db)
        );
    }
}
