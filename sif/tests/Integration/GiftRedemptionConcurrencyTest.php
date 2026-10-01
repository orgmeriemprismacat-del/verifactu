<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\CommercialEntitlementRepository;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class GiftRedemptionConcurrencyTest
{
    public function testTwoProcessesRedeemingSameEnrollmentReuseSingleSaga(): void
    {
        [$db, $legacy, $code] = $this->fixture(false);

        $results = $this->runConcurrentWorkers([501, 501], $code);

        Assert::same(true, $results['a']['payload']['ok']);
        Assert::same(true, $results['b']['payload']['ok']);
        Assert::same(
            $results['a']['payload']['uuid_operation'],
            $results['b']['payload']['uuid_operation']
        );

        $stageReuse = [
            (bool) $results['a']['payload']['stage_reused'],
            (bool) $results['b']['payload']['stage_reused'],
        ];
        sort($stageReuse);
        Assert::same([false, true], $stageReuse);

        Assert::same(1, $this->count(
            $db,
            "SELECT COUNT(*) FROM commercial_operation
             WHERE SOURCE_TYPE='INSCRIPCIO' AND SOURCE_ID='501'"
        ));
        Assert::same(1, $this->count(
            $db,
            "SELECT COUNT(*) FROM enrollment_fund_movement
             WHERE MOVEMENT_TYPE='COMPENSATION_ALLOCATION'"
        ));
        Assert::same(1, $this->count(
            $db,
            "SELECT COUNT(*) FROM commercial_entitlement_event
             WHERE ACTION='RESERVE'"
        ));
        Assert::same(1, $this->count(
            $db,
            "SELECT COUNT(*) FROM commercial_entitlement_event
             WHERE ACTION='CONSUME'"
        ));
        Assert::same(1, $this->count(
            $db,
            "SELECT COUNT(*) FROM payment_transaction
             WHERE TIPUS_MOVIMENT='CHARGE'"
        ));
        Assert::same(
            'CONSUMED',
            (string) $db->query(
                "SELECT STATUS FROM commercial_entitlement
                 WHERE ENTITLEMENT_TYPE='GIFT'"
            )->fetchColumn()
        );
        Assert::same(
            501,
            (int) $legacy->query(
                "SELECT USAT FROM regal WHERE CODI='GIFT-CONCURRENT-001'"
            )->fetchColumn()
        );
    }

    public function testTwoProcessesWithDifferentEnrollmentsAllowOnlyOneDestination(): void
    {
        [$db, $legacy, $code] = $this->fixture(true);

        $results = $this->runConcurrentWorkers([501, 502], $code);

        $successes = array_values(array_filter(
            $results,
            static fn (array $result): bool => ($result['payload']['ok'] ?? false) === true
        ));
        $conflicts = array_values(array_filter(
            $results,
            static fn (array $result): bool => ($result['payload']['ok'] ?? false) === false
        ));

        Assert::same(1, count($successes));
        Assert::same(1, count($conflicts));
        Assert::same(409, (int) $conflicts[0]['payload']['code']);

        $winner = (int) $successes[0]['payload']['enrollment_id'];
        if (!in_array($winner, [501, 502], true)) {
            Assert::fail('Unexpected winning gift enrollment');
        }

        Assert::same(1, $this->count(
            $db,
            "SELECT COUNT(*) FROM commercial_operation
             WHERE SOURCE_TYPE='INSCRIPCIO'"
        ));
        Assert::same(1, $this->count(
            $db,
            "SELECT COUNT(*) FROM enrollment_fund_movement
             WHERE MOVEMENT_TYPE='COMPENSATION_ALLOCATION'"
        ));
        Assert::same(1, $this->count(
            $db,
            "SELECT COUNT(*) FROM commercial_entitlement_event
             WHERE ACTION='CONSUME'"
        ));
        Assert::same(1, $this->count(
            $db,
            "SELECT COUNT(*) FROM payment_transaction
             WHERE TIPUS_MOVIMENT='CHARGE'"
        ));
        Assert::same(
            $winner,
            (int) $legacy->query(
                "SELECT USAT FROM regal WHERE CODI='GIFT-CONCURRENT-001'"
            )->fetchColumn()
        );

        $destination = (int) $db->query(
            "SELECT SOURCE_ID FROM commercial_operation
             WHERE SOURCE_TYPE='INSCRIPCIO'"
        )->fetchColumn();
        Assert::same($winner, $destination);
    }

    private function runConcurrentWorkers(array $enrollmentIds, string $giftCode): array
    {
        $barrier = sys_get_temp_dir() . '/uc018-' . bin2hex(random_bytes(8));
        if (!mkdir($barrier, 0700, true) && !is_dir($barrier)) {
            throw new \RuntimeException('Could not create UC-018 concurrency barrier');
        }

        $workers = ['a', 'b'];
        $processes = [];

        try {
            foreach ($workers as $index => $worker) {
                $command = [
                    PHP_BINARY,
                    dirname(__DIR__) . '/Support/ConcurrentGiftRedemptionWorker.php',
                    $barrier,
                    $worker,
                    (string) $enrollmentIds[$index],
                    $giftCode,
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
                    throw new \RuntimeException(
                        'Could not start UC-018 concurrency worker ' . $worker
                    );
                }

                fclose($pipes[0]);
                $processes[$worker] = [
                    'process' => $process,
                    'pipes' => $pipes,
                ];
            }

            $deadline = microtime(true) + 10;
            while (!is_file($barrier . '/ready-a') || !is_file($barrier . '/ready-b')) {
                if (microtime(true) >= $deadline) {
                    throw new \RuntimeException(
                        'UC-018 concurrent workers did not reach barrier'
                    );
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
                        'UC-018 concurrency worker '
                        . $worker
                        . ' failed: '
                        . $stderr
                    );
                }

                $payload = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($payload)) {
                    Assert::fail('UC-018 concurrency worker returned invalid JSON');
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

    private function fixture(bool $withSecondEnrollment): array
    {
        $db = TestDatabase::fresh();
        $legacy = $this->freshLegacyDatabase();
        $code = 'GIFT-CONCURRENT-001';

        $legacy->prepare(
            'INSERT INTO regal
             (ID, CODI, IMPORT, FACT_REL, USAT, CCURS, NOM_CURS)
             VALUES (?, ?, ?, ?, NULL, ?, ?)'
        )->execute([
            77,
            $code,
            '120.00',
            987,
            'COURSE-TEST',
            'Curs concurrent',
        ]);

        $this->insertLegacyEnrollment($legacy, 501, $code, 9001);
        if ($withSecondEnrollment) {
            $this->insertLegacyEnrollment($legacy, 502, $code, 9002);
        }

        $uuid = new UuidGenerator();
        $origin = $uuid->generate();

        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC018|CONCURRENT|INVOICE|' . $origin,
                'emesa_abans_cobrament' => 1,
                'totals' => [
                    'import_base' => '120.00',
                    'taxable_base' => '120.00',
                    'total' => '120.00',
                ],
                'lines' => [[
                    'concept' => 'Regal concurrència UC-018',
                    'detail' => 'Gift concurrency fixture',
                    'quantity' => '1.00',
                    'unit_price' => '120.00',
                    'base' => '120.00',
                    'import_base' => '120.00',
                    'discount_amount' => '0.00',
                    'taxable_base' => '120.00',
                    'iva_regim' => 'EXEMPT',
                    'iva_pct' => '0.00',
                    'iva_import' => '0.00',
                    'total' => '120.00',
                    'source_type' => 'REGAL',
                    'source_id' => 77,
                ]],
            ])
        );

        $payment = RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'UC018|CONCURRENT|PAYMENT|' . $origin,
            'movement_type' => 'CHARGE',
            'method' => 'REDSYS',
            'source_channel' => 'WEB',
            'amount' => '120.00',
            'movement_date' => '2026-10-02 00:30:00',
            'reference' => 'GIFT-CONCURRENT-' . $origin,
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '120.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        $db->prepare(
            'INSERT INTO commercial_operation
             (UUID_OPERATION, IDEMPOTENCY_KEY, OPERATION_TYPE, SOURCE_CHANNEL,
              SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE,
              CLASSIFICATION, CLASSIFICATION_REASON, STATUS, CURRENCY,
              GROSS_AMOUNT, DISCOUNT_AMOUNT, NET_AMOUNT, PRICE_SNAPSHOT_JSON,
              TAX_SNAPSHOT_JSON, UUID_FACTURA, UUID_PAYMENT)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $origin,
            'UC018|CONCURRENT|ORIGIN|' . $origin,
            'GIFT_PURCHASE',
            'WEB',
            'REGAL',
            '77',
            'REGAL',
            'GIFT',
            'BILLABLE',
            'GIFT_PURCHASE',
            'PAID',
            'EUR',
            '120.00',
            '0.00',
            '120.00',
            '{}',
            '{}',
            $invoice['uuid_factura'],
            $payment['uuid_payment'],
        ]);

        $codeHash = hash('sha256', $code);
        $entitlement = $uuid->generate();
        $db->prepare(
            'INSERT INTO commercial_entitlement
             (UUID_ENTITLEMENT, ENTITLEMENT_TYPE, CODE_HASH, HOLDER_PARTY_KEY,
              ORIGIN_UUID_OPERATION, RULE_VERSION, RULE_SNAPSHOT_JSON, FACE_VALUE,
              CURRENCY, STATUS, ISSUED_AT, EXPIRES_AT, IDEMPOTENCY_KEY)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $entitlement,
            'GIFT',
            $codeHash,
            CommercialEntitlementRepository::unclaimedGiftHolderKey($codeHash),
            $origin,
            'GIFT_V1',
            '{}',
            '120.00',
            'EUR',
            'ACTIVE',
            '2026-10-01 00:00:00',
            '2027-10-01 00:00:00',
            'UC018|CONCURRENT|ENT|' . $entitlement,
        ]);

        return [$db, $legacy, $code];
    }

    private function freshLegacyDatabase(): \PDO
    {
        $config = require dirname(__DIR__, 2) . '/config/sif.php';
        $legacy = ConnectionFactory::makeLegacy($config);

        $database = (string) $legacy->query('SELECT DATABASE()')->fetchColumn();
        if (!preg_match('/^sif_legacy_test(?:_[a-z0-9_]+)?$/D', $database)) {
            throw new \RuntimeException(
                'UC-018 concurrency test requires isolated sif_legacy_test* database'
            );
        }

        $legacy->exec('DROP TABLE IF EXISTS inscripcions');
        $legacy->exec('DROP TABLE IF EXISTS regal');

        $legacy->exec(
            'CREATE TABLE regal (
                ID INT PRIMARY KEY,
                CODI VARCHAR(200) NOT NULL UNIQUE,
                IMPORT DECIMAL(12,2) NOT NULL,
                FACT_REL INT NULL,
                USAT INT NULL,
                CCURS VARCHAR(80) NULL,
                NOM_CURS VARCHAR(255) NULL
            ) ENGINE=InnoDB'
        );
        $legacy->exec(
            'CREATE TABLE inscripcions (
                ID INT PRIMARY KEY,
                ANY INT NOT NULL,
                MES VARCHAR(12) NOT NULL,
                CURS VARCHAR(80) NOT NULL,
                DNI VARCHAR(20) NOT NULL,
                NOM VARCHAR(80) NOT NULL,
                COGNOMS VARCHAR(120) NOT NULL,
                A_PAGAR DECIMAL(12,2) NOT NULL,
                FACTURA_RELACIONADA VARCHAR(30) NULL,
                pag_observacions VARCHAR(255) NULL,
                IDPAG INT NOT NULL,
                OBSERVACIONS VARCHAR(255) NULL
            ) ENGINE=InnoDB'
        );

        return $legacy;
    }

    private function insertLegacyEnrollment(
        \PDO $legacy,
        int $id,
        string $code,
        int $idPag
    ): void {
        $legacy->prepare(
            'INSERT INTO inscripcions
             (ID, ANY, MES, CURS, DNI, NOM, COGNOMS, A_PAGAR,
              FACTURA_RELACIONADA, pag_observacions, IDPAG, OBSERVACIONS)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $id,
            2026,
            '10',
            'COURSE-TEST',
            '12345678Z',
            'Persona',
            'Concurrent',
            '0.00',
            '987',
            $code,
            $idPag,
            'CURS REGAL',
        ]);
    }

    private function count(\PDO $db, string $sql): int
    {
        return (int) $db->query($sql)->fetchColumn();
    }
}
