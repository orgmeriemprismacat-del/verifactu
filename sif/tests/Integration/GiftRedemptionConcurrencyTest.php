<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class GiftRedemptionConcurrencyTest
{
    public function testTwoProcessesCannotConsumeSameGiftIntoDifferentEnrollments(): void
    {
        $db = TestDatabase::fresh();
        [$code, $holder, $destinationA, $destinationB] = $this->fixture($db);

        $chargesBefore = (int) $db->query(
            "SELECT COUNT(*) FROM payment_transaction
             WHERE TIPUS_MOVIMENT='CHARGE'"
        )->fetchColumn();

        $results = $this->runConcurrentWorkers([
            'a' => [
                'destination' => $destinationA,
                'idempotency_key' => 'UC018|CONCURRENT|A',
            ],
            'b' => [
                'destination' => $destinationB,
                'idempotency_key' => 'UC018|CONCURRENT|B',
            ],
        ], $code, $holder);

        $decoded = [];
        foreach ($results as $worker => $result) {
            Assert::same(0, $result['exit_code']);
            $decoded[$worker] = json_decode(
                $result['stdout'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }

        $successfulWorkers = array_keys(array_filter(
            $decoded,
            static fn (array $item): bool => ($item['ok'] ?? false) === true
        ));
        $rejectedWorkers = array_keys(array_filter(
            $decoded,
            static fn (array $item): bool => ($item['ok'] ?? true) === false
        ));

        Assert::same(1, count($successfulWorkers));
        Assert::same(1, count($rejectedWorkers));

        $winner = $successfulWorkers[0];
        $loser = $rejectedWorkers[0];
        Assert::same(409, (int) $decoded[$loser]['status_code']);

        $winningDestination = $winner === 'a' ? $destinationA : $destinationB;
        $winningEnrollment = $winner === 'a' ? 501 : 502;
        $losingDestination = $winner === 'a' ? $destinationB : $destinationA;

        $entitlement = $db->query(
            "SELECT STATUS, CONSUMED_UUID_OPERATION
             FROM commercial_entitlement"
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('CONSUMED', (string) $entitlement['STATUS']);
        Assert::same(
            $winningDestination,
            (string) $entitlement['CONSUMED_UUID_OPERATION']
        );

        $movement = $db->query(
            "SELECT ID_INSC_DESTI, UUID_OPERATION, IMPORT
             FROM enrollment_fund_movement
             WHERE MOVEMENT_TYPE='COMPENSATION_ALLOCATION'"
        )->fetchAll(\PDO::FETCH_ASSOC);

        Assert::same(1, count($movement));
        Assert::same($winningEnrollment, (int) $movement[0]['ID_INSC_DESTI']);
        Assert::same($winningDestination, (string) $movement[0]['UUID_OPERATION']);
        Assert::same('120.00', (string) $movement[0]['IMPORT']);

        Assert::same(
            1,
            (int) $db->query(
                "SELECT COUNT(*) FROM commercial_entitlement_event
                 WHERE ACTION='RESERVE'"
            )->fetchColumn()
        );
        Assert::same(
            1,
            (int) $db->query(
                "SELECT COUNT(*) FROM commercial_entitlement_event
                 WHERE ACTION='CONSUME'"
            )->fetchColumn()
        );
        Assert::same(
            $chargesBefore,
            (int) $db->query(
                "SELECT COUNT(*) FROM payment_transaction
                 WHERE TIPUS_MOVIMENT='CHARGE'"
            )->fetchColumn()
        );

        $winningStatus = $db->prepare(
            'SELECT STATUS FROM commercial_operation WHERE UUID_OPERATION = ?'
        );
        $winningStatus->execute([$winningDestination]);
        Assert::same('COMPLETED', (string) $winningStatus->fetchColumn());

        $losingStatus = $db->prepare(
            'SELECT STATUS FROM commercial_operation WHERE UUID_OPERATION = ?'
        );
        $losingStatus->execute([$losingDestination]);
        Assert::same('CONFIRMED', (string) $losingStatus->fetchColumn());
    }

    private function runConcurrentWorkers(
        array $commands,
        string $code,
        string $holder
    ): array {
        $barrier = sys_get_temp_dir() . '/uc018-' . bin2hex(random_bytes(8));
        if (!mkdir($barrier, 0700, true) && !is_dir($barrier)) {
            throw new \RuntimeException(
                'Could not create gift concurrency test barrier'
            );
        }

        $processes = [];
        try {
            foreach ($commands as $worker => $commandData) {
                $command = [
                    PHP_BINARY,
                    dirname(__DIR__) . '/Support/ConcurrentGiftRedemptionWorker.php',
                    $barrier,
                    $worker,
                    $code,
                    $holder,
                    (string) $commandData['destination'],
                    (string) $commandData['idempotency_key'],
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
                        'Could not start gift concurrency worker ' . $worker
                    );
                }

                fclose($pipes[0]);
                $processes[$worker] = [
                    'process' => $process,
                    'pipes' => $pipes,
                ];
            }

            $deadline = microtime(true) + 10;
            foreach (array_keys($commands) as $worker) {
                while (!is_file($barrier . '/ready-' . $worker)) {
                    if (microtime(true) >= $deadline) {
                        throw new \RuntimeException(
                            'Gift concurrency workers did not reach barrier'
                        );
                    }
                    usleep(10000);
                }
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

                if ($exitCode !== 0) {
                    Assert::fail(
                        'Gift concurrency worker '
                        . $worker
                        . ' failed: '
                        . $results[$worker]['stderr']
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

    private function fixture(\PDO $db): array
    {
        $holder = 'student:gift:concurrent';
        $code = 'GIFT-CONCURRENT-SECRET-001';
        $origin = $this->createPaidOrigin($db);
        $uuidEntitlement = (new UuidGenerator())->generate();

        $db->prepare(
            'INSERT INTO commercial_entitlement
             (UUID_ENTITLEMENT, ENTITLEMENT_TYPE, CODE_HASH, HOLDER_PARTY_KEY,
              ORIGIN_UUID_OPERATION, RULE_VERSION, RULE_SNAPSHOT_JSON,
              FACE_VALUE, CURRENCY, STATUS, ISSUED_AT, EXPIRES_AT,
              IDEMPOTENCY_KEY)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uuidEntitlement,
            'GIFT',
            hash('sha256', $code),
            $holder,
            $origin,
            'GIFT_V1',
            '{}',
            '120.00',
            'EUR',
            'ACTIVE',
            '2026-09-01 00:00:00',
            '2027-09-30 00:00:00',
            'TEST|GIFT|CONCURRENT|' . $uuidEntitlement,
        ]);

        $destinationA = $this->createDestination($db, $holder, 501);
        $destinationB = $this->createDestination($db, $holder, 502);

        return [$code, $holder, $destinationA, $destinationB];
    }

    private function createPaidOrigin(\PDO $db): string
    {
        $uuid = new UuidGenerator();
        $operation = $uuid->generate();

        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' =>
                    'UC018|CONCURRENT|ORIGIN|INVOICE|' . $operation,
                'emesa_abans_cobrament' => 1,
                'totals' => [
                    'import_base' => '120.00',
                    'taxable_base' => '120.00',
                    'total' => '120.00',
                ],
                'lines' => [[
                    'concept' => 'Regal concurrència',
                    'detail' => 'Regal concurrència',
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
                    'source_id' => 177,
                ]],
            ])
        );

        $payment = RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' =>
                'UC018|CONCURRENT|ORIGIN|PAYMENT|' . $operation,
            'movement_type' => 'CHARGE',
            'method' => 'REDSYS',
            'source_channel' => 'WEB',
            'amount' => '120.00',
            'movement_date' => '2026-09-01 10:00:00',
            'reference' => 'GIFT-CONCURRENT-' . $operation,
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
            $operation,
            'UC018|CONCURRENT|ORIGIN|OP|' . $operation,
            'GIFT_PURCHASE',
            'WEB',
            'REGAL',
            '177',
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

        return $operation;
    }

    private function createDestination(
        \PDO $db,
        string $holder,
        int $idInsc
    ): string {
        $operation = (new UuidGenerator())->generate();

        $db->prepare(
            'INSERT INTO commercial_operation
             (UUID_OPERATION, IDEMPOTENCY_KEY, OPERATION_TYPE, SOURCE_CHANNEL,
              SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE,
              CLASSIFICATION, CLASSIFICATION_REASON, STATUS, CURRENCY,
              GROSS_AMOUNT, DISCOUNT_AMOUNT, NET_AMOUNT, PRICE_SNAPSHOT_JSON,
              TAX_SNAPSHOT_JSON)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $operation,
            'UC018|CONCURRENT|DEST|' . $operation,
            'ENROLLMENT',
            'WEB',
            'INSCRIPCIO',
            (string) $idInsc,
            'CURS',
            'COURSE-TEST',
            'NON_BILLABLE',
            'GIFT_REDEMPTION',
            'CONFIRMED',
            'EUR',
            '120.00',
            '120.00',
            '0.00',
            '{}',
            '{}',
        ]);

        $db->prepare(
            'INSERT INTO commercial_operation_party
             (UUID_OPERATION, PARTY_KEY, PARTY_ROLE, NOM_RAO, SNAPSHOT_JSON)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $operation,
            $holder,
            'PARTICIPANT',
            'Concurrent gift holder',
            '{}',
        ]);

        return $operation;
    }
}
