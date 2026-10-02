<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\CommercialEntitlementRepository;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class GiftRedemptionConcurrencyTest
{
    public function testTwoProcessesRedeemSameGiftExactlyOnce(): void
    {
        $db = TestDatabase::fresh();
        $code = 'GIFT-CONCURRENT-001';

        $this->createLegacyTables($db);

        try {
            $this->seedFixture($db, $code);

            $chargesBefore = (int) $db->query(
                "SELECT COUNT(*) FROM payment_transaction WHERE TIPUS_MOVIMENT='CHARGE'"
            )->fetchColumn();
            $invoicesBefore = (int) $db->query(
                'SELECT COUNT(*) FROM factura'
            )->fetchColumn();

            $results = $this->runConcurrentWorkers($code);

            Assert::same(0, $results['a']['exit_code']);
            Assert::same(0, $results['b']['exit_code']);

            $a = json_decode($results['a']['stdout'], true, 512, JSON_THROW_ON_ERROR);
            $b = json_decode($results['b']['stdout'], true, 512, JSON_THROW_ON_ERROR);

            Assert::same($a['stage_operation'], $b['stage_operation']);
            Assert::same($a['fund_movement_uuid'], $b['fund_movement_uuid']);
            Assert::same('CONSUMED', $a['redemption_status']);
            Assert::same('CONSUMED', $b['redemption_status']);
            Assert::same('RECONCILED', $a['legacy_status']);
            Assert::same('RECONCILED', $b['legacy_status']);

            $stageReused = [(bool) $a['stage_reused'], (bool) $b['stage_reused']];
            sort($stageReused);
            Assert::same([false, true], $stageReused);

            $legacyReused = [(bool) $a['legacy_reused'], (bool) $b['legacy_reused']];
            sort($legacyReused);
            Assert::same([false, true], $legacyReused);

            Assert::same(501, (int) $db->query(
                "SELECT USAT FROM regal WHERE CODI='GIFT-CONCURRENT-001'"
            )->fetchColumn());
            Assert::same(1, (int) $db->query(
                "SELECT COUNT(*) FROM commercial_operation
                 WHERE SOURCE_TYPE='INSCRIPCIO' AND SOURCE_ID='501'"
            )->fetchColumn());
            Assert::same(1, (int) $db->query(
                "SELECT COUNT(*) FROM enrollment_fund_movement
                 WHERE MOVEMENT_TYPE='COMPENSATION_ALLOCATION'"
            )->fetchColumn());
            Assert::same(1, (int) $db->query(
                "SELECT COUNT(*) FROM commercial_entitlement_event WHERE ACTION='CLAIM'"
            )->fetchColumn());
            Assert::same(1, (int) $db->query(
                "SELECT COUNT(*) FROM commercial_entitlement_event WHERE ACTION='RESERVE'"
            )->fetchColumn());
            Assert::same(1, (int) $db->query(
                "SELECT COUNT(*) FROM commercial_entitlement_event WHERE ACTION='CONSUME'"
            )->fetchColumn());
            Assert::same($chargesBefore, (int) $db->query(
                "SELECT COUNT(*) FROM payment_transaction WHERE TIPUS_MOVIMENT='CHARGE'"
            )->fetchColumn());
            Assert::same($invoicesBefore, (int) $db->query(
                'SELECT COUNT(*) FROM factura'
            )->fetchColumn());
        } finally {
            $db->exec('DROP TABLE IF EXISTS inscripcions');
            $db->exec('DROP TABLE IF EXISTS regal');
        }
    }

    private function createLegacyTables(\PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS inscripcions');
        $db->exec('DROP TABLE IF EXISTS regal');

        $db->exec(
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

        $db->exec(
            'CREATE TABLE regal (
                ID INT PRIMARY KEY,
                CODI VARCHAR(200) NOT NULL UNIQUE,
                IMPORT DECIMAL(12,2) NOT NULL,
                FACT_REL VARCHAR(30) NULL,
                USAT INT NULL,
                CCURS VARCHAR(80) NULL,
                NOM_CURS VARCHAR(255) NULL
            ) ENGINE=InnoDB'
        );
    }

    private function seedFixture(\PDO $db, string $code): void
    {
        $db->prepare(
            'INSERT INTO inscripcions
             (ID, ANY, MES, CURS, DNI, NOM, COGNOMS, A_PAGAR,
              FACTURA_RELACIONADA, pag_observacions, IDPAG, OBSERVACIONS)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            501,
            2026,
            '09',
            'COURSE-TEST',
            '12345678Z',
            'Persona',
            'Concurrent',
            '0.00',
            '987',
            $code,
            9901,
            'CURS REGAL',
        ]);

        $db->prepare(
            'INSERT INTO regal
             (ID, CODI, IMPORT, FACT_REL, USAT, CCURS, NOM_CURS)
             VALUES (?, ?, ?, ?, NULL, ?, ?)'
        )->execute([
            77,
            $code,
            '120.00',
            '987',
            'COURSE-TEST',
            'Curs concurrent',
        ]);

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
                    'concept' => 'Regal concurrent test',
                    'detail' => 'Gift concurrent origin',
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
            'movement_date' => '2026-10-01 10:00:00',
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

        $hash = hash('sha256', $code);
        $db->prepare(
            'INSERT INTO commercial_entitlement
             (UUID_ENTITLEMENT, ENTITLEMENT_TYPE, CODE_HASH, HOLDER_PARTY_KEY,
              ORIGIN_UUID_OPERATION, RULE_VERSION, RULE_SNAPSHOT_JSON, FACE_VALUE,
              CURRENCY, STATUS, ISSUED_AT, EXPIRES_AT, IDEMPOTENCY_KEY)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uuid->generate(),
            'GIFT',
            $hash,
            CommercialEntitlementRepository::unclaimedGiftHolderKey($hash),
            $origin,
            'GIFT_V1',
            '{}',
            '120.00',
            'EUR',
            'ACTIVE',
            '2026-10-01 10:00:00',
            '2027-10-01 10:00:00',
            'UC018|CONCURRENT|ENTITLEMENT|77',
        ]);
    }

    private function runConcurrentWorkers(string $code): array
    {
        $barrier = sys_get_temp_dir() . '/uc018-' . bin2hex(random_bytes(8));
        if (!mkdir($barrier, 0700, true) && !is_dir($barrier)) {
            throw new \RuntimeException('Could not create UC-018 concurrency barrier');
        }

        $processes = [];
        try {
            foreach (['a', 'b'] as $worker) {
                $command = [
                    PHP_BINARY,
                    dirname(__DIR__) . '/Support/ConcurrentGiftRedemptionWorker.php',
                    $barrier,
                    $worker,
                    '501',
                    $code,
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
                $processes[$worker] = ['process' => $process, 'pipes' => $pipes];
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
                        'UC-018 concurrency worker ' . $worker
                        . ' failed: ' . $result['stderr']
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
}
