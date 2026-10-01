<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Service\NotificationDeliveryLifecycleService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class NotificationDeliveryLifecycleServiceTest
{
    public function testClaimIsExclusiveAndSentAcknowledgementIsIdempotent(): void
    {
        $db = TestDatabase::fresh();
        $this->enqueue($db, 'NOTIFY|TEST|SENT');

        $service = $this->service();
        $claimed = $service->claimNext($db);

        Assert::same('PROCESSING', $claimed['STATUS']);
        Assert::same(1, (int) $claimed['ATTEMPT_NO']);
        Assert::same('EMAIL', $claimed['CHANNEL']);
        Assert::same(null, $service->claimNext($db));

        $first = $service->acknowledgeSent(
            $db,
            (string) $claimed['ATTEMPT_UUID'],
            'provider-message-1'
        );
        $second = $service->acknowledgeSent(
            $db,
            (string) $claimed['ATTEMPT_UUID'],
            'provider-message-1'
        );

        Assert::same('SENT', $first['status']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same(
            'SENT',
            (string) $db->query(
                "SELECT STATUS FROM notification_outbox"
            )->fetchColumn()
        );
        Assert::same(
            'SENT',
            (string) $db->query(
                "SELECT STATUS FROM notification_delivery_attempt"
            )->fetchColumn()
        );
        Assert::same(
            'provider-message-1',
            (string) $db->query(
                "SELECT PROVIDER_REF FROM notification_delivery_attempt"
            )->fetchColumn()
        );
        Assert::same(null, $service->claimNext($db));
    }

    public function testKnownFailureBeforeSendCanRetryWithNewAttempt(): void
    {
        $db = TestDatabase::fresh();
        $this->enqueue($db, 'NOTIFY|TEST|RETRY');

        $service = $this->service(60);
        $firstClaim = $service->claimNext($db);
        $failed = $service->failBeforeSend(
            $db,
            (string) $firstClaim['ATTEMPT_UUID'],
            'SMTP_CONNECT_FAILED',
            'Connection was not established',
            new \DateTimeImmutable(
                '2026-10-01 10:00:00',
                new \DateTimeZone('UTC')
            )
        );

        Assert::same('RETRY', $failed['status']);
        Assert::same(
            'FAILED',
            (string) $db->query(
                "SELECT STATUS FROM notification_delivery_attempt"
            )->fetchColumn()
        );
        Assert::same(
            '2026-10-01 10:01:00',
            substr(
                (string) $db->query(
                    "SELECT NEXT_ATTEMPT_AT FROM notification_outbox"
                )->fetchColumn(),
                0,
                19
            )
        );

        $db->exec(
            "UPDATE notification_outbox
             SET NEXT_ATTEMPT_AT = DATE_SUB(NOW(6), INTERVAL 1 SECOND)"
        );

        $secondClaim = $service->claimNext($db);
        Assert::same(2, (int) $secondClaim['ATTEMPT_NO']);
        Assert::same(
            2,
            (int) $db->query(
                'SELECT COUNT(*) FROM notification_delivery_attempt'
            )->fetchColumn()
        );
    }

    public function testAmbiguousDeliveryIsHeldForReviewAndNeverAutoRetried(): void
    {
        $db = TestDatabase::fresh();
        $this->enqueue($db, 'NOTIFY|TEST|UNCERTAIN');

        $service = $this->service();
        $claimed = $service->claimNext($db);
        $held = $service->markUncertain(
            $db,
            (string) $claimed['ATTEMPT_UUID'],
            'SMTP_RESULT_UNCERTAIN',
            'Transport ended after send may have reached provider'
        );

        Assert::same('REVIEW', $held['status']);
        Assert::same(
            'REVIEW',
            (string) $db->query(
                'SELECT STATUS FROM notification_outbox'
            )->fetchColumn()
        );
        Assert::same(
            'UNCERTAIN',
            (string) $db->query(
                'SELECT STATUS FROM notification_delivery_attempt'
            )->fetchColumn()
        );
        Assert::same(null, $service->claimNext($db));
    }

    private function service(
        int $retryDelaySeconds = 300
    ): NotificationDeliveryLifecycleService {
        return new NotificationDeliveryLifecycleService(
            new NotificationOutboxRepository(new UuidGenerator()),
            $retryDelaySeconds
        );
    }

    private function enqueue(
        \PDO $db,
        string $idempotencyKey
    ): void {
        (new NotificationOutboxRepository(new UuidGenerator()))->enqueue(
            $db,
            [
                'idempotency_key' => $idempotencyKey,
                'template_code' => 'TEST_NOTIFICATION',
                'template_version' => 'v1',
                'recipient_type' => 'INTERNAL',
                'recipient_hash' => hash(
                    'sha256',
                    'recipient@example.test'
                ),
                'payload' => [
                    'source_type' => 'TEST',
                    'reference' => hash('sha256', $idempotencyKey),
                ],
                'correlation_id' => 'NOTIFY-TEST-' . substr(
                    hash('sha256', $idempotencyKey),
                    0,
                    32
                ),
            ]
        );
    }
}
