<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\IncidentRepository;
use Prisma\Sif\Repository\LegacyGiftSnapshotRepository;
use Prisma\Sif\Repository\RedsysCallbackQueueRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Service\LegacyGiftInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysCallbackDispatcher;
use Prisma\Sif\Service\RedsysCallbackService;
use Prisma\Sif\Service\RedsysCallbackWorker;
use Prisma\Sif\Service\RedsysGiftInvoiceService;
use Prisma\Sif\Service\RedsysGiftPaymentStatusService;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysPaymentIntentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RedsysGiftWorkerEndToEndTest
{
    public function testGiftCallbackWorkerAndReplayKeepInvoicePaymentAndEntitlementIdempotent(): void
    {
        $db = TestDatabase::fresh();
        $dsOrder = '770000000101';

        $intent = (new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        ))->create($db, [
            'ds_order' => $dsOrder,
            'idpag' => null,
            'source_type' => 'REGAL',
            'source_id' => '77',
            'expected_amount' => '120.00',
            'currency' => 'EUR',
            'terminal' => '1',
            'snapshot' => $this->snapshot(),
            'created_by' => 'pay-prisma-cat',
        ]);

        $notifications = new RedsysNotificationRepository();
        $queue = new RedsysCallbackQueueRepository(new UuidGenerator());
        $callback = new RedsysCallbackService(
            new RedsysPaymentIntentRepository(),
            $notifications,
            $queue,
            new IncidentRepository()
        );

        $firstCallback = $callback->receiveCallback(
            $db,
            $this->callbackPayload($dsOrder),
            true
        );
        $duplicateCallback = $callback->receiveCallback(
            $db,
            $this->callbackPayload($dsOrder),
            true
        );

        Assert::same('QUEUED', $firstCallback['queue_status']);
        Assert::same(true, $duplicateCallback['duplicate']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM redsys_notifications')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM redsys_callback_queue')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());

        $handler = new RedsysGiftInvoiceService(
            $notifications,
            new LegacyGiftSnapshotRepository(),
            new LegacyGiftInvoicePayloadBuilder(),
            new RedsysInvoicePayloadBuilder($notifications),
            IssueInvoiceTest::serviceFor($db)
        );
        $worker = new RedsysCallbackWorker(
            $queue,
            new RedsysCallbackDispatcher([$handler]),
            new IncidentRepository(),
            5
        );

        $first = $worker->runOne(
            $db,
            'gift-worker-a',
            new \DateTimeImmutable('2030-10-04 10:00:00')
        );

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same(false, $first['gift_entitlement']['idempotency_reused']);
        Assert::same('UNCLAIMED', $first['gift_entitlement']['holder_state']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_operation WHERE OPERATION_TYPE='GIFT_PURCHASE'"
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_entitlement WHERE ENTITLEMENT_TYPE='GIFT'"
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_entitlement_event WHERE ACTION='ISSUE'"
        )->fetchColumn());

        $status = (new RedsysGiftPaymentStatusService(
            new RedsysPaymentIntentRepository(),
            $notifications,
            $queue
        ))->status($db, $dsOrder);

        Assert::same('CONFIRMED', $status['status']);
        Assert::same($first['uuid_factura'], $status['uuid_factura']);
        Assert::same($first['uuid_payment'], $status['uuid_payment']);

        $job = $db->query('SELECT ID, UUID_FACTURA, UUID_PAYMENT FROM redsys_callback_queue')
            ->fetch(\PDO::FETCH_ASSOC);
        $db->prepare(
            "UPDATE redsys_callback_queue
             SET STATUS='RETRY', AVAILABLE_AT=?, PROCESSED_AT=NULL,
                 LOCKED_AT=NULL, LOCKED_BY=NULL
             WHERE ID=?"
        )->execute(['2030-10-04 10:01:00', (int) $job['ID']]);

        $second = $worker->runOne(
            $db,
            'gift-worker-b',
            new \DateTimeImmutable('2030-10-04 10:01:00')
        );

        Assert::same(true, $second['ok']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same(true, $second['gift_entitlement']['idempotency_reused']);
        Assert::same($first['uuid_factura'], $second['uuid_factura']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);
        Assert::same(
            $first['gift_entitlement']['uuid_entitlement'],
            $second['gift_entitlement']['uuid_entitlement']
        );

        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_operation WHERE OPERATION_TYPE='GIFT_PURCHASE'"
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_entitlement WHERE ENTITLEMENT_TYPE='GIFT'"
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_entitlement_event WHERE ACTION='ISSUE'"
        )->fetchColumn());

        $after = $db->query(
            'SELECT STATUS, ATTEMPTS, UUID_FACTURA, UUID_PAYMENT FROM redsys_callback_queue'
        )->fetch(\PDO::FETCH_ASSOC);
        Assert::same('PROCESSED', $after['STATUS']);
        Assert::same(2, (int) $after['ATTEMPTS']);
        Assert::same($job['UUID_FACTURA'], $after['UUID_FACTURA']);
        Assert::same($job['UUID_PAYMENT'], $after['UUID_PAYMENT']);

        Assert::same((string) $intent['uuid_intent'], (string) (
            $db->query('SELECT UUID_INTENT FROM redsys_payment_intent')->fetchColumn()
        ));
    }

    private function snapshot(): array
    {
        return [
            'gift' => [
                'ID' => 77,
                'NOM_CURS' => 'Comunicacio assertiva',
                'CCURS' => 'COM',
                'NOMC' => 'Compradora Regal',
                'NIFC' => '55555555R',
                'MAILC' => 'compradora@example.test',
                'ADRECAC' => 'Carrer Regal 5',
                'POBLEC' => 'Barcelona',
                'CPC' => '08005',
                'CODI' => 'REGAL-77',
                'IMPORT' => '120.00',
                'FACT_REL' => 0,
                'ORIGEN' => 'Compradora Regal',
                'DESTI' => 'Destinatari Regal',
                'OBSERVACIONS' => 'Dedicatoria',
            ],
        ];
    }

    private function callbackPayload(string $dsOrder): array
    {
        return [
            'ds_order' => $dsOrder,
            'amount' => '120.00',
            'response_code' => '0000',
            'currency_code' => '978',
            'currency' => 'EUR',
            'terminal' => '1',
            'signature_version' => 'HMAC_SHA512_V2',
            'payload_hash' => hash('sha256', $dsOrder),
        ];
    }
}
