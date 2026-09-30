<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\RedsysCallbackQueueRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Service\RedsysCoursePaymentStatusService;
use Prisma\Sif\Service\RedsysPaymentIntentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RedsysCoursePaymentStatusServiceTest
{
    public function testPendingBeforeServerCallback(): void
    {
        $db = TestDatabase::fresh();
        $this->createIntent($db, 'STATUS000001', 701);

        $result = $this->service()->status($db, 'STATUS000001', 701);

        Assert::same('PENDING', $result['status']);
        Assert::same(null, $result['queue_status']);
        Assert::same(null, $result['uuid_factura']);
        Assert::same(null, $result['uuid_payment']);
    }

    public function testRejectedNotificationIsAuthoritative(): void
    {
        $db = TestDatabase::fresh();
        $this->createIntent($db, 'STATUS000002', 702);
        (new RedsysNotificationRepository())->recordReceived(
            $db,
            'STATUS000002',
            702,
            '50.00',
            '0190',
            true,
            $this->rawPayload('STATUS000002'),
            'ERROR'
        );

        $result = $this->service()->status($db, 'STATUS000002', 702);

        Assert::same('REJECTED', $result['status']);
        Assert::same(null, $result['queue_status']);
    }

    public function testValidatedQueuedAndProcessedStates(): void
    {
        $db = TestDatabase::fresh();
        $intent = $this->createIntent($db, 'STATUS000003', 703);
        $notifications = new RedsysNotificationRepository();
        $record = $notifications->recordReceived(
            $db,
            'STATUS000003',
            703,
            '50.00',
            '0000',
            true,
            $this->rawPayload('STATUS000003'),
            'VALIDATED'
        );
        $queue = new RedsysCallbackQueueRepository(new UuidGenerator());
        $job = $queue->enqueue($db, (int) $record['notification_id'], (string) $intent['UUID_INTENT']);

        $processing = $this->service()->status($db, 'STATUS000003', 703);
        Assert::same('PROCESSING', $processing['status']);
        Assert::same('QUEUED', $processing['queue_status']);

        $db->prepare(
            "UPDATE redsys_callback_queue
             SET STATUS = 'PROCESSED', UUID_FACTURA = ?, UUID_PAYMENT = ?, PROCESSED_AT = NOW()
             WHERE ID = ?"
        )->execute([
            '11111111-1111-4111-8111-111111111111',
            '22222222-2222-4222-8222-222222222222',
            $job['ID'],
        ]);

        $confirmed = $this->service()->status($db, 'STATUS000003', 703);
        Assert::same('CONFIRMED', $confirmed['status']);
        Assert::same('PROCESSED', $confirmed['queue_status']);
        Assert::same('11111111-1111-4111-8111-111111111111', $confirmed['uuid_factura']);
        Assert::same('22222222-2222-4222-8222-222222222222', $confirmed['uuid_payment']);
    }

    public function testProcessedWithoutInvoiceOrPaymentIsReview(): void
    {
        $db = TestDatabase::fresh();
        $intent = $this->createIntent($db, 'STATUS000006', 706);
        $record = (new RedsysNotificationRepository())->recordReceived(
            $db,
            'STATUS000006',
            706,
            '50.00',
            '0000',
            true,
            $this->rawPayload('STATUS000006'),
            'VALIDATED'
        );
        $queue = new RedsysCallbackQueueRepository(new UuidGenerator());
        $job = $queue->enqueue($db, (int) $record['notification_id'], (string) $intent['UUID_INTENT']);
        $db->prepare("UPDATE redsys_callback_queue SET STATUS = 'PROCESSED', PROCESSED_AT = NOW() WHERE ID = ?")
            ->execute([$job['ID']]);

        $result = $this->service()->status($db, 'STATUS000006', 706);

        Assert::same('REVIEW', $result['status']);
        Assert::same('PROCESSED', $result['queue_status']);
        Assert::same(null, $result['uuid_factura']);
        Assert::same(null, $result['uuid_payment']);
    }

    public function testIncidentIsPresentedAsReview(): void
    {
        $db = TestDatabase::fresh();
        $intent = $this->createIntent($db, 'STATUS000004', 704);
        $record = (new RedsysNotificationRepository())->recordReceived(
            $db,
            'STATUS000004',
            704,
            '50.00',
            '0000',
            true,
            $this->rawPayload('STATUS000004'),
            'VALIDATED'
        );
        $queue = new RedsysCallbackQueueRepository(new UuidGenerator());
        $job = $queue->enqueue($db, (int) $record['notification_id'], (string) $intent['UUID_INTENT']);
        $db->prepare("UPDATE redsys_callback_queue SET STATUS = 'INCIDENT' WHERE ID = ?")
            ->execute([$job['ID']]);

        $result = $this->service()->status($db, 'STATUS000004', 704);

        Assert::same('REVIEW', $result['status']);
        Assert::same('INCIDENT', $result['queue_status']);
    }

    public function testOrderAndIdpagMustBelongToSameCourseIntent(): void
    {
        $db = TestDatabase::fresh();
        $this->createIntent($db, 'STATUS000005', 705);

        Assert::throws(SifException::class, function () use ($db): void {
            $this->service()->status($db, 'STATUS000005', 999);
        }, 404);
    }

    private function service(): RedsysCoursePaymentStatusService
    {
        return new RedsysCoursePaymentStatusService(
            new RedsysPaymentIntentRepository(),
            new RedsysNotificationRepository(),
            new RedsysCallbackQueueRepository(new UuidGenerator())
        );
    }

    private function createIntent(\PDO $db, string $dsOrder, int $idpag): array
    {
        (new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        ))->create($db, [
            'ds_order' => $dsOrder,
            'idpag' => $idpag,
            'source_type' => 'CURS',
            'source_id' => (string) $idpag,
            'expected_amount' => '50.00',
            'currency' => 'EUR',
            'terminal' => '1',
            'snapshot' => [
                'inscription' => [
                    'ID' => $idpag,
                    'ANY' => 2026,
                    'MES' => '10',
                    'CURS' => 'RET',
                    'NOM' => 'Test',
                    'DNI' => '00000000T',
                    'A_PAGAR' => '50.00',
                ],
                'course' => ['NOM_CURS' => 'Retorn autoritatiu'],
                'payment' => ['idpag' => $idpag, 'amount' => '50.00'],
            ],
        ]);

        return (new RedsysPaymentIntentRepository())->findByDsOrder($db, $dsOrder)
            ?? throw new \RuntimeException('Intent was not created');
    }

    private function rawPayload(string $dsOrder): array
    {
        return [
            'currency_code' => '978',
            'terminal' => '1',
            'signature_version' => 'HMAC_SHA256_V1',
            'payload_hash' => hash('sha256', $dsOrder),
        ];
    }
}
