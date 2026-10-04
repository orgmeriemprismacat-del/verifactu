<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Service\GiftReservationNotificationService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class GiftReservationNotificationServiceTest
{
    public function testEnqueuesTwoIdempotentReservationNotificationsWithoutGiftCode(): void
    {
        $db = TestDatabase::fresh();
        $service = new GiftReservationNotificationService(
            new NotificationOutboxRepository(new UuidGenerator())
        );

        $first = $service->enqueueBundle($db, $this->snapshot());
        $second = $service->enqueueBundle($db, $this->snapshot());

        Assert::same(77, $first['gift_id']);
        Assert::same(2, count($first['notifications']));
        Assert::same(2, (int) $db->query(
            "SELECT COUNT(*) FROM notification_outbox
             WHERE TEMPLATE_CODE LIKE 'GIFT_RESERVATION_%'"
        )->fetchColumn());

        foreach ($second['notifications'] as $notification) {
            Assert::same(true, $notification['idempotency_reused']);
        }

        $rows = $db->query(
            "SELECT TEMPLATE_CODE, RECIPIENT_HASH, PAYLOAD_JSON
             FROM notification_outbox
             WHERE TEMPLATE_CODE LIKE 'GIFT_RESERVATION_%'
             ORDER BY TEMPLATE_CODE"
        )->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            Assert::same(64, strlen((string) $row['RECIPIENT_HASH']));
            if (str_contains((string) $row['PAYLOAD_JSON'], 'SECRET-REGAL-77')) {
                Assert::fail('Reservation outbox must not persist redeemable gift code');
            }
        }
    }

    private function snapshot(): array
    {
        return [
            'gift' => [
                'ID' => 77,
                'NOM_CURS' => 'Comunicacio assertiva',
                'CCURS' => 'COM',
                'MAILC' => 'buyer@example.test',
                'CODI' => 'SECRET-REGAL-77',
                'IMPORT' => '120.00',
            ],
        ];
    }
}
