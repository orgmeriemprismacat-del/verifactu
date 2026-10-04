<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class Uc021RedsysConcurrencyTest
{
    public function __destruct()
    {
        try {
            $config = require dirname(__DIR__, 2) . '/config/sif.php';
            $legacy = ConnectionFactory::makeLegacy($config);
            $database = (string) $legacy->query('SELECT DATABASE()')->fetchColumn();
            if (preg_match('/^sif_legacy_test(?:_[a-z0-9_]+)?$/D', $database)) {
                $legacy->exec('DROP TABLE IF EXISTS curs');
                $legacy->exec('DROP TABLE IF EXISTS inscripcions');
            }
        } catch (\Throwable) {
            // Best-effort cleanup.
        }
    }

    public function testConcurrentCourseIntentAndUc021InvoiceAllowExactlyOneEconomicWinner(): void
    {
        $db = TestDatabase::fresh();
        $this->prepareLegacy();

        $results = $this->runWorkers();

        $successes = array_values(array_filter(
            $results,
            static fn (array $result): bool => ($result['payload']['ok'] ?? false) === true
        ));
        $conflicts = array_values(array_filter(
            $results,
            static fn (array $result): bool => ($result['payload']['ok'] ?? false) === false
                && (int) ($result['payload']['code'] ?? 0) === 409
        ));

        Assert::same(1, count($successes));
        Assert::same(1, count($conflicts));

        $invoiceCount = (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn();
        $intentCount = (int) $db->query(
            "SELECT COUNT(*) FROM redsys_payment_intent
             WHERE SOURCE_TYPE = 'CURS' AND SOURCE_ID = '710'"
        )->fetchColumn();
        $coverageCount = (int) $db->query(
            "SELECT COUNT(*) FROM invoice_before_payment_coverage
             WHERE SOURCE_TYPE = 'INSCRIPCIO' AND SOURCE_ID = 710"
        )->fetchColumn();

        Assert::same(1, $invoiceCount + $intentCount);
        Assert::same($invoiceCount, $coverageCount);

        if ($invoiceCount === 1) {
            Assert::same(0, $intentCount);
            Assert::same(1, $coverageCount);
            Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
            Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        } else {
            Assert::same(1, $intentCount);
            Assert::same(0, $coverageCount);
            Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
            Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        }

        Assert::same(
            1,
            (int) $db->query(
                "SELECT COUNT(*) FROM enrollment_payment_flow_lock
                 WHERE SOURCE_TYPE = 'INSCRIPCIO' AND SOURCE_ID = 710"
            )->fetchColumn()
        );
    }

    private function runWorkers(): array
    {
        $barrier = sys_get_temp_dir() . '/uc021-redsys-' . bin2hex(random_bytes(8));
        if (!mkdir($barrier, 0700, true) && !is_dir($barrier)) {
            throw new \RuntimeException('Could not create UC-021 concurrency barrier');
        }

        $definitions = [
            'intent' => 'intent',
            'invoice' => 'invoice',
        ];
        $processes = [];

        try {
            foreach ($definitions as $worker => $mode) {
                $command = [
                    PHP_BINARY,
                    dirname(__DIR__) . '/Support/ConcurrentUc021RedsysWorker.php',
                    $mode,
                    $barrier,
                    $worker,
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
                    throw new \RuntimeException('Could not start UC-021 concurrency worker');
                }

                fclose($pipes[0]);
                $processes[$worker] = ['process' => $process, 'pipes' => $pipes];
            }

            $deadline = microtime(true) + 10;
            while (!is_file($barrier . '/ready-intent') || !is_file($barrier . '/ready-invoice')) {
                if (microtime(true) >= $deadline) {
                    throw new \RuntimeException('UC-021 workers did not reach barrier');
                }
                usleep(10000);
            }

            touch($barrier . '/go');

            $results = [];
            foreach ($processes as $worker => $entry) {
                $stdout = trim((string) stream_get_contents($entry['pipes'][1]));
                $stderr = trim((string) stream_get_contents($entry['pipes'][2]));
                fclose($entry['pipes'][1]);
                fclose($entry['pipes'][2]);
                $exitCode = proc_close($entry['process']);

                if ($exitCode !== 0) {
                    Assert::fail(
                        "UC-021 concurrency worker {$worker} failed with exit {$exitCode}: {$stderr}"
                    );
                }

                $payload = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($payload)) {
                    Assert::fail('UC-021 concurrency worker returned invalid JSON');
                }

                $results[$worker] = [
                    'stdout' => $stdout,
                    'stderr' => $stderr,
                    'payload' => $payload,
                ];
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

    private function prepareLegacy(): void
    {
        $config = require dirname(__DIR__, 2) . '/config/sif.php';
        $legacy = ConnectionFactory::makeLegacy($config);
        $database = (string) $legacy->query('SELECT DATABASE()')->fetchColumn();

        if (!preg_match('/^sif_legacy_test(?:_[a-z0-9_]+)?$/D', $database)) {
            throw new \RuntimeException('UC-021 concurrency test requires isolated sif_legacy_test* database');
        }

        $legacy->exec('DROP TABLE IF EXISTS curs');
        $legacy->exec('DROP TABLE IF EXISTS inscripcions');

        $legacy->exec(
            'CREATE TABLE inscripcions (
                ID INT PRIMARY KEY,
                IDPAG INT NOT NULL UNIQUE,
                ANY INT NOT NULL,
                MES VARCHAR(12) NOT NULL,
                CURS VARCHAR(80) NOT NULL,
                DATA_INSC DATETIME NULL,
                NOM VARCHAR(80) NOT NULL,
                COGNOMS VARCHAR(120) NOT NULL,
                DNI VARCHAR(20) NOT NULL,
                CORREU VARCHAR(180) NULL,
                ADRECA VARCHAR(255) NULL,
                Codi_Postal VARCHAR(20) NULL,
                Poblacio VARCHAR(120) NULL,
                FACTURA_RELACIONADA VARCHAR(30) NULL,
                A_PAGAR DECIMAL(12,2) NOT NULL,
                `INSC CURS` VARCHAR(2) NOT NULL,
                PAGAMENT DECIMAL(12,2) NOT NULL DEFAULT 0,
                FRACCIONAT TINYINT NOT NULL DEFAULT 0,
                FRACCIO VARCHAR(30) NULL,
                TIPUS_DESC INT NOT NULL DEFAULT 0,
                VALID_DESC INT NOT NULL DEFAULT 0
            ) ENGINE=InnoDB'
        );

        $legacy->exec(
            'CREATE TABLE curs (
                ANY INT NOT NULL,
                MES VARCHAR(12) NOT NULL,
                CURS VARCHAR(80) NOT NULL,
                NOM_CURS VARCHAR(255) NOT NULL,
                DATAI DATE NULL,
                DATAF DATE NULL,
                HORES INT NULL,
                ID_PREU INT NULL,
                PRIMARY KEY (ANY, MES, CURS)
            ) ENGINE=InnoDB'
        );

        $legacy->prepare(
            'INSERT INTO inscripcions
             (ID, IDPAG, ANY, MES, CURS, DATA_INSC, NOM, COGNOMS, DNI, CORREU,
              ADRECA, Codi_Postal, Poblacio, FACTURA_RELACIONADA, A_PAGAR,
              `INSC CURS`, PAGAMENT, FRACCIONAT, FRACCIO, TIPUS_DESC, VALID_DESC)
             VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            710,
            700,
            2026,
            '10',
            'UC21',
            'Persona',
            'Concurrent',
            '00000000T',
            'uc021@example.invalid',
            'Carrer prova',
            '08001',
            'Barcelona',
            '100.00',
            '0',
            '0.00',
            0,
            '',
            0,
            0,
        ]);

        $legacy->prepare(
            'INSERT INTO curs
             (ANY, MES, CURS, NOM_CURS, DATAI, DATAF, HORES, ID_PREU)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            2026,
            '10',
            'UC21',
            'Curs concurrent UC-021',
            '2026-10-01',
            '2026-10-31',
            30,
            1,
        ]);
    }
}
