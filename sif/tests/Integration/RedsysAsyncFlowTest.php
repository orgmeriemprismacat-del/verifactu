<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\IncidentRepository;
use Prisma\Sif\Repository\LegacyCourseSnapshotRepository;
use Prisma\Sif\Repository\RedsysCallbackQueueRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Service\LegacyCourseInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysCallbackDispatcher;
use Prisma\Sif\Service\RedsysCallbackService;
use Prisma\Sif\Service\RedsysCallbackWorker;
use Prisma\Sif\Service\RedsysCourseInvoiceService;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysPaymentIntentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RedsysAsyncFlowTest
{
    public function testAuthorizedCallbackIsProcessedAsynchronouslyFromSnapshot(): void
    {
        $db = TestDatabase::fresh();
        [$callback, $worker] = $this->circuit($db);
        $this->createIntent($db, 'ORDERASYNC1');

        $queued = $callback->receiveCallback($db, $this->callbackPayload('ORDERASYNC1'), true);
        Assert::same('QUEUED', $queued['queue_status']);
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());

        $result = $worker->runOne($db, 'worker-a', new \DateTimeImmutable('2030-06-19 10:00:00'));
        $job = $db->query('SELECT * FROM redsys_callback_queue')->fetch(\PDO::FETCH_ASSOC);

        Assert::same(true, $result['ok']);
        Assert::same('PROCESSED', $job['STATUS']);
        Assert::same($result['uuid_factura'], $job['UUID_FACTURA']);
        Assert::same($result['uuid_payment'], $job['UUID_PAYMENT']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(null, $worker->runOne($db, 'worker-a', new \DateTimeImmutable('2030-06-19 10:01:00')));
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
    }

    public function testTwoConnectionsCannotClaimSameJob(): void
    {
        $dbA = TestDatabase::fresh();
        [$callback] = $this->circuit($dbA);
        $this->createIntent($dbA, 'ORDERASYNC2');
        $callback->receiveCallback($dbA, $this->callbackPayload('ORDERASYNC2'), true);
        $dbB = TestDatabase::connect();
        $queueA = new RedsysCallbackQueueRepository(new UuidGenerator());
        $queueB = new RedsysCallbackQueueRepository(new UuidGenerator());
        $now = new \DateTimeImmutable('2030-06-19 10:00:00');

        $claimed = $queueA->claimNext($dbA, 'worker-a', $now);

        Assert::same('PROCESSING', $claimed['STATUS']);
        Assert::same(null, $queueB->claimNext($dbB, 'worker-b', $now));
    }

    private function circuit(\PDO $db): array
    {
        $notifications = new RedsysNotificationRepository();
        $queue = new RedsysCallbackQueueRepository(new UuidGenerator());
        $callback = new RedsysCallbackService(
            new RedsysPaymentIntentRepository(),
            $notifications,
            $queue,
            new IncidentRepository()
        );
        $handler = new RedsysCourseInvoiceService(
            $notifications,
            new LegacyCourseSnapshotRepository(),
            new LegacyCourseInvoicePayloadBuilder(),
            new RedsysInvoicePayloadBuilder($notifications),
            IssueInvoiceTest::serviceFor($db)
        );
        $worker = new RedsysCallbackWorker(
            $queue,
            new RedsysCallbackDispatcher([$handler]),
            new IncidentRepository(),
            5
        );

        return [$callback, $worker];
    }

    private function createIntent(\PDO $db, string $dsOrder): void
    {
        (new RedsysPaymentIntentService(new RedsysPaymentIntentRepository(), new UuidGenerator()))->create($db, [
            'ds_order' => $dsOrder,
            'idpag' => 400,
            'source_type' => 'CURS',
            'source_id' => '400',
            'expected_amount' => '95.50',
            'currency' => 'EUR',
            'terminal' => '1',
            'snapshot' => [
                'inscription' => [
                    'ID' => 410, 'ANY' => 2026, 'MES' => '07', 'CURS' => 'LM',
                    'NOM' => 'Joan', 'COGNOMS' => 'Mostra', 'DNI' => '87654321Z',
                    'A_PAGAR' => '95.50', 'FACTURA_RELACIONADA' => 810,
                ],
                'course' => ['NOM_CURS' => 'Llenguatge musical'],
                'payment' => ['idpag' => 400, 'amount' => '95.50'],
            ],
        ]);
    }

    private function callbackPayload(string $dsOrder): array
    {
        return [
            'ds_order' => $dsOrder,
            'amount' => '95.50',
            'response_code' => '0000',
            'currency_code' => '978',
            'currency' => 'EUR',
            'terminal' => '1',
            'signature_version' => 'HMAC_SHA256_V1',
            'payload_hash' => hash('sha256', $dsOrder),
        ];
    }
}
