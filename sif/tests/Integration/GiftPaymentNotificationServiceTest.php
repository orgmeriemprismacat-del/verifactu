<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Service\GiftPaymentNotificationService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class GiftPaymentNotificationServiceTest
{
    public function testEnqueuesBuyerNotificationWithoutRawGiftCode(): void
    {
        $db = TestDatabase::fresh();
        $service = new GiftPaymentNotificationService(
            new NotificationOutboxRepository(new UuidGenerator())
        );

        $first = $service->enqueue(
            $db,
            '770000000001',
            [
                'gift' => [
                    'ID' => 77,
                    'CODI' => 'SECRET-GIFT-CODE',
                    'MAILC' => 'Buyer@Example.Test',
                ],
            ],
            [
                'uuid_factura' => '11111111-1111-4111-8111-111111111111',
                'uuid_payment' => '22222222-2222-4222-8222-222222222222',
                'num_visible' => 'A2026/77',
            ],
            [
                'uuid_entitlement' => '33333333-3333-4333-8333-333333333333',
                'holder_state' => 'UNCLAIMED',
                'status' => 'ACTIVE',
            ]
        );
        $second = $service->enqueue(
            $db,
            '770000000001',
            [
                'gift' => [
                    'ID' => 77,
                    'CODI' => 'SECRET-GIFT-CODE',
                    'MAILC' => 'buyer@example.test',
                ],
            ],
            [
                'uuid_factura' => '11111111-1111-4111-8111-111111111111',
                'uuid_payment' => '22222222-2222-4222-8222-222222222222',
                'num_visible' => 'A2026/77',
            ],
            [
                'uuid_entitlement' => '33333333-3333-4333-8333-333333333333',
                'holder_state' => 'UNCLAIMED',
                'status' => 'ACTIVE',
            ]
        );

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_notification'], $second['uuid_notification']);

        $row = $db->query(
            "SELECT TEMPLATE_CODE, RECIPIENT_TYPE, RECIPIENT_HASH, PAYLOAD_JSON
             FROM notification_outbox
             WHERE IDEMPOTENCY_KEY='NOTIFY|GIFT_PAYMENT_CONFIRMED|ORDER:770000000001'"
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('GIFT_PAYMENT_CONFIRMED', $row['TEMPLATE_CODE']);
        Assert::same('COMPRADOR', $row['RECIPIENT_TYPE']);
        Assert::same(hash('sha256', 'buyer@example.test'), $row['RECIPIENT_HASH']);

        $payload = json_decode((string) $row['PAYLOAD_JSON'], true);
        Assert::same(77, $payload['gift_id']);
        Assert::same(false, $payload['gift_code_in_payload']);
        Assert::same(false, str_contains((string) $row['PAYLOAD_JSON'], 'SECRET-GIFT-CODE'));
    }
}
