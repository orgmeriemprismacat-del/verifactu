<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class GiftRedemptionConcurrencyTest
{
    public function testConcurrentSameDestinationConsumesGiftExactlyOnceAndReusesResult(): void
    {
        [$db, $code, $holder, $destination] = $this->fixture();
        $chargesBefore = (int) $db->query(
            "SELECT COUNT(*) FROM payment_transaction WHERE TIPUS_MOVIMENT='CHARGE'"
        )->fetchColumn();

        $results = $this->runConcurrentWorkers(
            $code,
            $holder,
            [
                'a' => [
                    'destination' => $destination,
                    'idempotency_key' => 'UC018|CONCURRENT|SAME|A',
                ],
                'b' => [
                    'destination' => $destination,
                    'idempotency_key' => 'UC018|CONCURRENT|SAME|B',
                ],
            ]
        );

        Assert::same(0, $results['a']['exit_code']);
        Assert::same(0, $results['b']['exit_code']);

        $a = $this->decoded($results['a']);
        $b = $this->decoded($results['b']);
        Assert::same(true, $a['ok']);
        Assert::same(true, $b['ok']);
        Assert::same('CONSUMED', $a['result']['status']);
        Assert::same('CONSUMED', $b['result']['status']);
        Assert::same($destination, $a['result']['uuid_operation']);
        Assert::same($destination, $b['result']['uuid_operation']);
        Assert::same(
            $a['result']['fund_movement_uuid'],
            $b['result']['fund_movement_uuid']
        );

        $reused = [
            (bool) $a['result']['idempotency_reused'],
            (bool) $b['result']['idempotency_reused'],
        ];
        sort($reused);
        Assert::same([false, true], $reused);

        $this->assertSingleCommittedRedemption(
            $db,
            $destination,
            501,
            $chargesBefore
        );
    }

    public function testConcurrentDifferentDestinationsAllowsOneWinnerAndRejectsTheOther(): void
    {
        [$db, $code, $holder, $destinationA] = $this->fixture();
        $destinationB = $this->createDestination($db, $holder, 502);
        $chargesBefore = (int) $db->query(
            "SELECT COUNT(*) FROM payment_transaction WHERE TIPUS_MOVIMENT='CHARGE'"
        )->fetchColumn();

        $results = $this->runConcurrentWorkers(
            $code,
            $holder,
            [
                'a' => [
                    'destination' => $destinationA,
                    'idempotency_key' => 'UC018|CONCURRENT|COMPETE|A',
                ],
                'b' => [
                    'destination' => $destinationB,
                    'idempotency_key' => 'UC018|CONCURRENT|COMPETE|B',
                ],
            ]
        );

        $exitCodes = [
            $results['a']['exit_code'],
            $results['b']['exit_code'],
        ];
        sort($exitCodes);
        Assert::same([0, 1], $exitCodes);

        $winnerKey = $results['a']['exit_code'] === 0 ? 'a' : 'b';
        $loserKey = $winnerKey === 'a' ? 'b' : 'a';
        $winner = $this->decoded($results[$winnerKey]);
        $loser = $this->decoded($results[$loserKey]);

        Assert::same(true, $winner['ok']);
        Assert::same('CONSUMED', $winner['result']['status']);
        Assert::same(false, $loser['ok']);
        Assert::same(409, (int) $loser['code']);
        Assert::stringContainsString(
            'already redeemed for another operation',
            (string) $loser['error']
        );

        $winningDestination = (string) $winner['result']['uuid_operation'];
        $winningEnrollment = (int) $winner['result']['enrollment_id'];
        $this->assertSingleCommittedRedemption(
            $db,
            $winningDestination,
            $winningEnrollment,
            $chargesBefore
        );

        $entitlementDestination = (string) $db->query(
            'SELECT CONSUMED_UUID_OPERATION FROM commercial_entitlement'
        )->fetchColumn();
        Assert::same($winningDestination, $entitlementDestination);

        $movement = $db->query(
            "SELECT UUID_OPERATION, ID_INSC_DESTI
             FROM enrollment_fund_movement
             WHERE MOVEMENT_TYPE='COMPENSATION_ALLOCATION'"
        )->fetch(\PDO::FETCH_ASSOC);
        Assert::same($winningDestination, (string) $movement['UUID_OPERATION']);
        Assert::same((string) $winningEnrollment, (string) $movement['ID_INSC_DESTI']);

        $statuses = $db->query(
            "SELECT STATUS FROM commercial_operation
             WHERE UUID_OPERATION IN ("
            . $db->quote($destinationA) . ','
            . $db->quote($destinationB) . ')
             ORDER BY STATUS'
        )->fetchAll(\PDO::FETCH_COLUMN);
        Assert::same(['COMPLETED', 'CONFIRMED'], $statuses);
    }

    private function assertSingleCommittedRedemption(
        \PDO $db,
        string $destination,
        int $enrollmentId,
        int $chargesBefore
    ): void {
        $entitlement = $db->query(
            'SELECT STATUS, CONSUMED_UUID_OPERATION
             FROM commercial_entitlement'
        )->fetch(\PDO::FETCH_ASSOC);
        Assert::same('CONSUMED', (string) $entitlement['STATUS']);
        Assert::same($destination, (string) $entitlement['CONSUMED_UUID_OPERATION']);

        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_entitlement_event
             WHERE ACTION='RESERVE'"
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_entitlement_event
             WHERE ACTION='CONSUME'"
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM enrollment_fund_movement
             WHERE MOVEMENT_TYPE='COMPENSATION_ALLOCATION'"
        )->fetchColumn());
        Assert::same($chargesBefore, (int) $db->query(
            "SELECT COUNT(*) FROM payment_transaction WHERE TIPUS_MOVIMENT='CHARGE'"
        )->fetchColumn());

        $movement = $db->query(
            "SELECT ID_INSC_DESTI, IMPORT, UUID_OPERATION
             FROM enrollment_fund_movement
             WHERE MOVEMENT_TYPE='COMPENSATION_ALLOCATION'"
        )->fetch(\PDO::FETCH_ASSOC);
        Assert::same((string) $enrollmentId, (string) $movement['ID_INSC_DESTI']);
        Assert::same('120.00', (string) $movement['IMPORT']);
        Assert::same($destination, (string) $movement['UUID_OPERATION']);

        $operation = $db->prepare(
            'SELECT STATUS FROM commercial_operation WHERE UUID_OPERATION = ?'
        );
        $operation->execute([$destination]);
        Assert::same('COMPLETED', (string) $operation->fetchColumn());
    }

    private function runConcurrentWorkers(
        string $code,
        string $holder,
        array $workers
    ): array {
        $barrier = sys_get_temp_dir() . '/uc018-gift-' . bin2hex(random_bytes(8));
        if (!mkdir($barrier, 0700, true) && !is_dir($barrier)) {
            throw new \RuntimeException('Could not create gift concurrency barrier');
        }

        $processes = [];
        try {
            foreach ($workers as $worker => $input) {
                $command = [
                    PHP_BINARY,
                    dirname(__DIR__) . '/Support/ConcurrentGiftRedemptionWorker.php',
                    $barrier,
                    $worker,
                    $code,
                    $holder,
                    (string) $input['destination'],
                    (string) $input['idempotency_key'],
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
            foreach (array_keys($workers) as $worker) {
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

    private function decoded(array $result): array
    {
        if ($result['stdout'] === '') {
            Assert::fail(
                'Gift concurrency worker produced no JSON. STDERR: '
                . $result['stderr']
            );
        }

        return json_decode(
            $result['stdout'],
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private function fixture(): array
    {
        $db = TestDatabase::fresh();
        $holder = 'student:gift:concurrent';
        $code = 'GIFT-CONCURRENT-SECRET-001';
        $origin = $this->createPaidOrigin($db);
        $uuid = (new UuidGenerator())->generate();

        $db->prepare(
            'INSERT INTO commercial_entitlement
             (UUID_ENTITLEMENT, ENTITLEMENT_TYPE, CODE_HASH, HOLDER_PARTY_KEY,
              ORIGIN_UUID_OPERATION, RULE_VERSION, RULE_SNAPSHOT_JSON, FACE_VALUE,
              CURRENCY, STATUS, ISSUED_AT, EXPIRES_AT, IDEMPOTENCY_KEY)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uuid,
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
            'TEST|GIFT|CONCURRENT|' . $uuid,
        ]);

        $destination = $this->createDestination($db, $holder, 501);

        return [$db, $code, $holder, $destination];
    }

    private function createPaidOrigin(\PDO $db): string
    {
        $uuid = new UuidGenerator();
        $operation = $uuid->generate();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC018|CONCURRENT|ORIGIN|INVOICE|' . $operation,
                'emesa_abans_cobrament' => 1,
                'totals' => [
                    'import_base' => '120.00',
                    'taxable_base' => '120.00',
                    'total' => '120.00',
                ],
                'lines' => [[
                    'concept' => 'Regal concurrència',
                    'detail' => 'Gift concurrency origin',
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
            'idempotency_key' => 'UC018|CONCURRENT|ORIGIN|PAYMENT|' . $operation,
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
            'Gift concurrency holder',
            '{}',
        ]);

        return $operation;
    }
}
