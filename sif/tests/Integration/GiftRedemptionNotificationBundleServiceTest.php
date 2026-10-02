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
    public function testSuccessfulRedemptionEnqueuesSixStableNotificationsWithoutRawEmail(): void
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
        Assert::same(6, $first['count']);
        Assert::same(6, $second['count']);
        Assert::same(6, count($first['notifications']));
        Assert::same(6, count($second['notifications']));

        $firstByCode = [];
        foreach ($first['notifications'] as $item) {
            $firstByCode[(string) $item['message_code']] = (string) $item['uuid_notification'];
        }
        $secondByCode = [];
        foreach ($second['notifications'] as $item) {
            $secondByCode[(string) $item['message_code']] = (string) $item['uuid_notification'];
        }
        ksort($firstByCode);
        ksort($secondByCode);
        Assert::same($firstByCode, $secondByCode);

        Assert::same(
            6,
            (int) $db->query(
                "SELECT COUNT(*) FROM notification_outbox"
            )->fetchColumn()
        );

        $rows = $db->query(
            "SELECT TEMPLATE_CODE, STATUS, RECIPIENT_HASH, PAYLOAD_JSON
             FROM notification_outbox
             ORDER BY TEMPLATE_CODE"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $expectedCodes = [
            'GIFT_REDEEM_INTERNAL_DETAIL_GMAIL',
            'GIFT_REDEEM_INTERNAL_DETAIL_PRIMARY',
            'GIFT_REDEEM_RESGUARD_PRIMARY',
            'GIFT_REDEEM_RESGUARD_SECONDARY',
            'GIFT_REDEEM_SECRETARY_CONFIRMATION',
            'GIFT_REDEEM_STUDENT_CONFIRMATION',
        ];
        $actualCodes = [];

        foreach ($rows as $row) {
            $actualCodes[] = (string) $row['TEMPLATE_CODE'];
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

        sort($actualCodes);
        sort($expectedCodes);
        Assert::same($expectedCodes, $actualCodes);
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
