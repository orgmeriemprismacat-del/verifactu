<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\RedsysCallbackQueueRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Service\RedsysGiftPaymentStatusService;
use Prisma\Sif\Service\RedsysPaymentIntentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RedsysGiftPaymentStatusServiceTest
{
    public function testPendingBeforeServerCallback(): void
    {
        $db = TestDatabase::fresh();
        $this->createGiftIntent($db, 'GIFTSTATUS001');

        $result = $this->service()->status($db, 'GIFTSTATUS001');

        Assert::same('PENDING', $result['status']);
        Assert::same(77, $result['gift_id']);
        Assert::same(null, $result['queue_status']);
    }

    public function testRejectedNotificationIsAuthoritative(): void
    {
        $db = TestDatabase::fresh();
        $this->createGiftIntent($db, 'GIFTSTATUS002');
        (new RedsysNotificationRepository())->recordReceived(
            $db,
            'GIFTSTATUS002',
            null,
            '50.00',
            '0190',
            true,
            $this->rawPayload('GIFTSTATUS002'),
            'ERROR'
        );

        $result = $this->service()->status($db, 'GIFTSTATUS002');

        Assert::same('REJECTED', $result['status']);
    }

    public function testQueuedAndProcessedGiftStates(): void
    {
        $db = TestDatabase::fresh();
        $intent = $this->createGiftIntent($db, 'GIFTSTATUS003');
        $record = (new RedsysNotificationRepository())->recordReceived(
            $db,
            'GIFTSTATUS003',
            null,
            '50.00',
            '0000',
            true,
            $this->rawPayload('GIFTSTATUS003'),
            'VALIDATED'
        );

        $queue = new RedsysCallbackQueueRepository(new UuidGenerator());
        $job = $queue->enqueue($db, (int) $record['notification_id'], (string) $intent['UUID_INTENT']);

        $processing = $this->service()->status($db, 'GIFTSTATUS003');
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

        $confirmed = $this->service()->status($db, 'GIFTSTATUS003');
        Assert::same('CONFIRMED', $confirmed['status']);
        Assert::same('PROCESSED', $confirmed['queue_status']);
        Assert::same('11111111-1111-4111-8111-111111111111', $confirmed['uuid_factura']);
        Assert::same('22222222-2222-4222-8222-222222222222', $confirmed['uuid_payment']);
    }

    public function testProcessedWithoutFiscalIdentifiersRequiresReview(): void
    {
        $db = TestDatabase::fresh();
        $intent = $this->createGiftIntent($db, 'GIFTSTATUS004');
        $record = (new RedsysNotificationRepository())->recordReceived(
            $db,
            'GIFTSTATUS004',
            null,
            '50.00',
            '0000',
            true,
            $this->rawPayload('GIFTSTATUS004'),
            'VALIDATED'
        );

        $queue = new RedsysCallbackQueueRepository(new UuidGenerator());
        $job = $queue->enqueue($db, (int) $record['notification_id'], (string) $intent['UUID_INTENT']);
        $db->prepare(
            "UPDATE redsys_callback_queue SET STATUS = 'PROCESSED', PROCESSED_AT = NOW() WHERE ID = ?"
        )->execute([$job['ID']]);

        $result = $this->service()->status($db, 'GIFTSTATUS004');

        Assert::same('REVIEW', $result['status']);
        Assert::same('PROCESSED', $result['queue_status']);
    }

    public function testNonGiftIntentIsNotDisclosedAsGiftStatus(): void
    {
        $db = TestDatabase::fresh();

        Assert::throws(SifException::class, function () use ($db): void {
            $this->service()->status($db, 'DOESNOTEXIST');
        }, 404);
    }

    private function service(): RedsysGiftPaymentStatusService
    {
        return new RedsysGiftPaymentStatusService(
            new RedsysPaymentIntentRepository(),
            new RedsysNotificationRepository(),
            new RedsysCallbackQueueRepository(new UuidGenerator())
        );
    }

    private function createGiftIntent(\PDO $db, string $dsOrder): array
    {
        (new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        ))->create($db, [
            'ds_order' => $dsOrder,
            'idpag' => null,
            'source_type' => 'REGAL',
            'source_id' => '77',
            'expected_amount' => '50.00',
            'currency' => 'EUR',
            'terminal' => '1',
            'snapshot' => [
                'gift' => [
                    'ID' => 77,
                    'CODI' => 'REGAL-77',
                    'IMPORT' => '50.00',
                    'FACT_REL' => 0,
                ],
            ],
            'created_by' => 'test',
        ]);

        return (new RedsysPaymentIntentRepository())->findByDsOrder($db, $dsOrder)
            ?? throw new \RuntimeException('Gift intent was not created');
    }

    private function rawPayload(string $dsOrder): array
    {
        return [
            'currency_code' => '978',
            'terminal' => '1',
            'signature_version' => 'HMAC_SHA512_V2',
            'payload_hash' => hash('sha256', $dsOrder),
        ];
    }
}
