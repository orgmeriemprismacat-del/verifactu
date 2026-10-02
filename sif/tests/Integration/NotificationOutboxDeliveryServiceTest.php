<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Service\NotificationOutboxDeliveryService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class NotificationOutboxDeliveryServiceTest
{
    public function testClaimIsAtMostOnceAndSentCompletionIsIdempotent(): void
    {
        $db = TestDatabase::fresh();
        $notification = $this->enqueue($db, 'NOTIFY|TEST|ONE');
        $service = new NotificationOutboxDeliveryService(new UuidGenerator());

        $first = $service->claim($db, $notification['uuid_notification']);
        $ambiguous = $service->claim($db, $notification['uuid_notification']);

        Assert::same(true, $first['should_send']);
        Assert::same('SENDING', $first['status']);
        Assert::same(false, $ambiguous['should_send']);
        Assert::same(
            'AMBIGUOUS_IN_FLIGHT_REQUIRES_REVIEW',
            $ambiguous['reason']
        );

        $completed = $service->complete(
            $db,
            $notification['uuid_notification'],
            $first['uuid_delivery_attempt'],
            true,
            'smtp-accepted'
        );
        $replayed = $service->complete(
            $db,
            $notification['uuid_notification'],
            $first['uuid_delivery_attempt'],
            true,
            'smtp-accepted'
        );
        $afterSent = $service->claim(
            $db,
            $notification['uuid_notification']
        );

        Assert::same('SENT', $completed['status']);
        Assert::same(false, $completed['idempotency_reused']);
        Assert::same(true, $replayed['idempotency_reused']);
        Assert::same(false, $afterSent['should_send']);
        Assert::same('ALREADY_SENT', $afterSent['reason']);
        Assert::same(
            1,
            (int) $db->query(
                "SELECT COUNT(*) FROM notification_delivery_attempt"
            )->fetchColumn()
        );
    }

    public function testKnownFailureRequiresReviewAndIsNotAutomaticallyReclaimed(): void
    {
        $db = TestDatabase::fresh();
        $notification = $this->enqueue($db, 'NOTIFY|TEST|FAIL');
        $service = new NotificationOutboxDeliveryService(new UuidGenerator());

        $claim = $service->claim($db, $notification['uuid_notification']);
        $failed = $service->complete(
            $db,
            $notification['uuid_notification'],
            $claim['uuid_delivery_attempt'],
            false,
            null,
            'SMTP_SEND_FAILED'
        );
        $retry = $service->claim($db, $notification['uuid_notification']);

        Assert::same('FAILED', $failed['status']);
        Assert::same(false, $retry['should_send']);
        Assert::same('FAILED_REQUIRES_REVIEW', $retry['reason']);
        Assert::same(
            'FAILED',
            (string) $db->query(
                "SELECT STATUS FROM notification_delivery_attempt"
            )->fetchColumn()
        );
    }

    private function enqueue(\PDO $db, string $key): array
    {
        return (new NotificationOutboxRepository(new UuidGenerator()))->enqueue(
            $db,
            [
                'idempotency_key' => $key,
                'template_code' => 'TEST_NOTIFICATION',
                'template_version' => '1',
                'recipient_type' => 'TEST',
                'recipient_hash' => hash('sha256', 'recipient@example.test'),
                'payload' => ['test' => true],
                'correlation_id' => $key,
            ]
        );
    }
}
