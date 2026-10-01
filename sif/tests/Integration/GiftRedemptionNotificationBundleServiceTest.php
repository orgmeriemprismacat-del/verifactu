<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Service\GiftRedemptionNotificationBundleService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class GiftRedemptionNotificationBundleServiceTest
{
    public function testSuccessfulRedemptionEnqueuesOneStableBundleWithoutRawEmail(): void
    {
        $db = TestDatabase::fresh();
        $db->exec(
            'CREATE TEMPORARY TABLE inscripcions (
                ID INT PRIMARY KEY,
                CORREU VARCHAR(255) NOT NULL
            )'
        );
        $db->exec(
            "INSERT INTO inscripcions (ID, CORREU)
             VALUES (501, 'student@example.test')"
        );

        $service = new GiftRedemptionNotificationBundleService(
            new NotificationOutboxRepository(new UuidGenerator())
        );
        $execution = $this->execution();

        $first = $service->enqueue($db, $db, 501, $execution);
        $second = $service->enqueue($db, $db, 501, $execution);

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_notification'], $second['uuid_notification']);
        Assert::same(
            1,
            (int) $db->query(
                "SELECT COUNT(*) FROM notification_outbox"
            )->fetchColumn()
        );

        $row = $db->query(
            "SELECT TEMPLATE_CODE, STATUS, RECIPIENT_HASH, PAYLOAD_JSON
             FROM notification_outbox"
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same(
            GiftRedemptionNotificationBundleService::TEMPLATE_CODE,
            $row['TEMPLATE_CODE']
        );
        Assert::same('PENDING', $row['STATUS']);
        Assert::same(64, strlen((string) $row['RECIPIENT_HASH']));
        Assert::same(
            false,
            str_contains((string) $row['PAYLOAD_JSON'], 'student@example.test')
        );
        Assert::same(
            false,
            str_contains((string) $row['PAYLOAD_JSON'], 'GIFT-SECRET')
        );
    }

    public function testCannotEnqueueBeforeConsumedAndReconciledResult(): void
    {
        $db = TestDatabase::fresh();
        $db->exec(
            'CREATE TEMPORARY TABLE inscripcions (
                ID INT PRIMARY KEY,
                CORREU VARCHAR(255) NOT NULL
            )'
        );
        $db->exec(
            "INSERT INTO inscripcions (ID, CORREU)
             VALUES (501, 'student@example.test')"
        );

        $execution = $this->execution();
        $execution['redemption']['status'] = 'RESERVED';

        Assert::throws(SifException::class, function () use ($db, $execution): void {
            (new GiftRedemptionNotificationBundleService(
                new NotificationOutboxRepository(new UuidGenerator())
            ))->enqueue($db, $db, 501, $execution);
        }, 409);

        Assert::same(
            0,
            (int) $db->query(
                "SELECT COUNT(*) FROM notification_outbox"
            )->fetchColumn()
        );
    }

    private function execution(): array
    {
        return [
            'stage' => [
                'uuid_operation' => '11111111-1111-4111-8111-111111111111',
                'uuid_entitlement' => '22222222-2222-4222-8222-222222222222',
            ],
            'redemption' => [
                'status' => 'CONSUMED',
            ],
            'legacy_reconciliation' => [
                'status' => 'RECONCILED',
            ],
        ];
    }
}
