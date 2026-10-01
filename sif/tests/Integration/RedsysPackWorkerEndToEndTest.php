<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Repository\IncidentRepository;
use Prisma\Sif\Repository\LegacyPackSnapshotRepository;
use Prisma\Sif\Repository\LegacySyncRepository;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Repository\RedsysCallbackQueueRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Service\LegacyPackInvoicePayloadBuilder;
use Prisma\Sif\Service\LegacySyncService;
use Prisma\Sif\Service\PackEnrollmentFundAllocationService;
use Prisma\Sif\Service\PackPaymentNotificationService;
use Prisma\Sif\Service\RedsysCallbackDispatcher;
use Prisma\Sif\Service\RedsysCallbackWorker;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysLegacySyncingProcessor;
use Prisma\Sif\Service\RedsysPackInvoiceService;
use Prisma\Sif\Service\RedsysPaymentIntentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RedsysPackWorkerEndToEndTest
{
    public function testPackWorkerReplayKeepsFiscalEconomicAndOutboxEffectsIdempotent(): void
    {
        $db = TestDatabase::fresh();
        $dsOrder = 'ORDERPACKWORKER1';
        $snapshot = $this->snapshot();

        $intent = (new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        ))->create($db, [
            'ds_order' => $dsOrder,
            'idpag' => 910,
            'source_type' => 'PACK',
            'source_id' => '77',
            'expected_amount' => '210.00',
            'currency' => 'EUR',
            'terminal' => '1',
            'snapshot' => $snapshot,
        ]);

        $notifications = new RedsysNotificationRepository();
        $notification = $notifications->recordReceived(
            $db,
            $dsOrder,
            910,
            '210.00',
            '0000',
            true,
            [
                'source' => 'pack-worker-e2e',
                'currency_code' => '978',
                'terminal' => '1',
                'signature_version' => 'HMAC_SHA256_V1',
                'payload_hash' => str_repeat('b', 64),
            ],
            'VALIDATED'
        );

        $queue = new RedsysCallbackQueueRepository(new UuidGenerator());
        $job = $queue->enqueue(
            $db,
            (int) $notification['notification_id'],
            (string) $intent['uuid_intent']
        );

        $legacyDb = new RedsysPackWorkerLegacyPdo();
        $worker = $this->worker($db, $notifications, $queue, $legacyDb);

        $first = $worker->runOne(
            $db,
            'pack-worker-a',
            new \DateTimeImmutable('2030-10-01 10:00:00')
        );

        Assert::same(true, $first['ok']);
        Assert::same(true, $first['legacy_sync_executed']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura_linia')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM enrollment_fund_movement')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM notification_outbox')->fetchColumn());

        $afterFirst = $db->query(
            'SELECT STATUS, ATTEMPTS, UUID_FACTURA, UUID_PAYMENT
             FROM redsys_callback_queue'
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('PROCESSED', $afterFirst['STATUS']);
        Assert::same(1, (int) $afterFirst['ATTEMPTS']);

        $db->prepare(
            "UPDATE redsys_callback_queue
             SET STATUS = 'RETRY', AVAILABLE_AT = ?, PROCESSED_AT = NULL,
                 LOCKED_AT = NULL, LOCKED_BY = NULL
             WHERE ID = ?"
        )->execute(['2030-10-01 10:01:00', (int) $job['ID']]);

        $second = $worker->runOne(
            $db,
            'pack-worker-b',
            new \DateTimeImmutable('2030-10-01 10:01:00')
        );

        Assert::same(true, $second['ok']);
        Assert::same(true, $second['legacy_sync_executed']);
        Assert::same($first['uuid_factura'], $second['uuid_factura']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);

        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura_linia')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM enrollment_fund_movement')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM notification_outbox')->fetchColumn());

        $afterSecond = $db->query(
            'SELECT STATUS, ATTEMPTS, UUID_FACTURA, UUID_PAYMENT
             FROM redsys_callback_queue'
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('PROCESSED', $afterSecond['STATUS']);
        Assert::same(2, (int) $afterSecond['ATTEMPTS']);
        Assert::same($afterFirst['UUID_FACTURA'], $afterSecond['UUID_FACTURA']);
        Assert::same($afterFirst['UUID_PAYMENT'], $afterSecond['UUID_PAYMENT']);
        Assert::same(8, count($legacyDb->preparedSql));
    }

    private function worker(
        \PDO $db,
        RedsysNotificationRepository $notifications,
        RedsysCallbackQueueRepository $queue,
        \PDO $legacyDb
    ): RedsysCallbackWorker {
        $handler = new RedsysPackInvoiceService(
            $notifications,
            new LegacyPackSnapshotRepository(),
            new LegacyPackInvoicePayloadBuilder(),
            new RedsysInvoicePayloadBuilder($notifications),
            IssueInvoiceTest::serviceFor($db),
            new PackPaymentNotificationService(
                new NotificationOutboxRepository(new UuidGenerator())
            ),
            new PackEnrollmentFundAllocationService(
                new EnrollmentFundMovementRepository(new UuidGenerator())
            )
        );

        return new RedsysCallbackWorker(
            $queue,
            new RedsysLegacySyncingProcessor(
                new RedsysCallbackDispatcher([$handler]),
                $legacyDb,
                new LegacySyncService(new LegacySyncRepository())
            ),
            new IncidentRepository(),
            5
        );
    }

    private function snapshot(): array
    {
        return [
            'pack' => [
                'ID_PACK' => 77,
                'TITOL' => 'Benestar docent',
                'CODI' => 'BDOC',
            ],
            'payment' => [
                'idpag' => 910,
                'amount' => '210.00',
            ],
            'items' => [
                [
                    'ordinal' => 1,
                    'inscription' => $this->inscription(
                        501,
                        '06',
                        'ABC',
                        '120.00',
                        '0.00',
                        '0.00'
                    ),
                    'course' => ['NOM_CURS' => 'Gestio emocional'],
                ],
                [
                    'ordinal' => 2,
                    'inscription' => $this->inscription(
                        502,
                        '07',
                        'DEF',
                        '120.00',
                        '30.00',
                        '25.00'
                    ),
                    'course' => ['NOM_CURS' => 'Mindfulness a l aula'],
                ],
            ],
        ];
    }

    private function inscription(
        int $id,
        string $month,
        string $course,
        string $base,
        string $discount,
        string $pct
    ): array {
        $total = number_format((float) $base - (float) $discount, 2, '.', '');

        return [
            'ID' => $id,
            'IDPAG' => 910,
            'ANY' => 2026,
            'MES' => $month,
            'CURS' => $course,
            'TIPUS_INSC' => 'P',
            'NOM' => 'Maria',
            'COGNOMS' => 'Exemple',
            'DNI' => '12345678Z',
            'CORREU' => 'maria@example.test',
            'ADRECA' => 'Carrer Exemple 1',
            'Codi_Postal' => '08001',
            'Poblacio' => 'Barcelona',
            'A_PAGAR' => $total,
            'IMPORT_BASE' => $base,
            'DESC_IMPORT' => $discount,
            'DESC_PCT' => $pct,
            'TOTAL' => $total,
        ];
    }
}

final class RedsysPackWorkerLegacyPdo extends \PDO
{
    public array $preparedSql = [];

    public function __construct()
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->preparedSql[] = $query;

        return new RedsysPackWorkerLegacyStatement();
    }
}

final class RedsysPackWorkerLegacyStatement extends \PDOStatement
{
    public function execute(?array $params = null): bool
    {
        return true;
    }
}
