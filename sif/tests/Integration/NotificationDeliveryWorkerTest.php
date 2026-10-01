<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Contract\NotificationDeliveryTransportInterface;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\NotificationDeliveryUncertainException;
use Prisma\Sif\Exception\NotificationPreSendException;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Service\NotificationDeliveryLifecycleService;
use Prisma\Sif\Service\NotificationDeliveryWorker;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class NotificationDeliveryWorkerTest
{
    public function testSuccessfulTransportAcknowledgesClaimedNotification(): void
    {
        $db = TestDatabase::fresh();
        $this->enqueue($db, 'WORKER|SUCCESS');

        $worker = $this->worker(
            new class implements NotificationDeliveryTransportInterface {
                public function deliver(array $notification): array
                {
                    return ['provider_ref' => 'smtp-message-001'];
                }
            }
        );

        $result = $worker->processOne($db);

        Assert::same(true, $result['ok']);
        Assert::same(true, $result['processed']);
        Assert::same('SENT', $result['status']);
        Assert::same(
            'SENT',
            (string) $db->query(
                'SELECT STATUS FROM notification_outbox'
            )->fetchColumn()
        );
        Assert::same(
            'smtp-message-001',
            (string) $db->query(
                'SELECT PROVIDER_REF FROM notification_delivery_attempt'
            )->fetchColumn()
        );
    }

    public function testPreSendFailureSchedulesSafeRetry(): void
    {
        $db = TestDatabase::fresh();
        $this->enqueue($db, 'WORKER|PRE-SEND');

        $worker = $this->worker(
            new class implements NotificationDeliveryTransportInterface {
                public function deliver(array $notification): array
                {
                    throw new NotificationPreSendException(
                        'SMTP configuration unavailable'
                    );
                }
            }
        );

        $result = $worker->processOne($db);

        Assert::same(false, $result['ok']);
        Assert::same(true, $result['safe_to_retry']);
        Assert::same('RETRY', $result['status']);
        Assert::same(
            'FAILED',
            (string) $db->query(
                'SELECT STATUS FROM notification_delivery_attempt'
            )->fetchColumn()
        );
    }

    public function testAmbiguousTransportFailureIsQuarantinedForReview(): void
    {
        $db = TestDatabase::fresh();
        $this->enqueue($db, 'WORKER|UNCERTAIN');

        $worker = $this->worker(
            new class implements NotificationDeliveryTransportInterface {
                public function deliver(array $notification): array
                {
                    throw new NotificationDeliveryUncertainException(
                        'Connection closed after DATA was accepted'
                    );
                }
            }
        );

        $result = $worker->processOne($db);

        Assert::same(false, $result['ok']);
        Assert::same(false, $result['safe_to_retry']);
        Assert::same(true, $result['requires_review']);
        Assert::same('REVIEW', $result['status']);
        Assert::same(
            'UNCERTAIN',
            (string) $db->query(
                'SELECT STATUS FROM notification_delivery_attempt'
            )->fetchColumn()
        );
    }

    public function testUnexpectedTransportExceptionDefaultsToReviewNotRetry(): void
    {
        $db = TestDatabase::fresh();
        $this->enqueue($db, 'WORKER|UNKNOWN');

        $worker = $this->worker(
            new class implements NotificationDeliveryTransportInterface {
                public function deliver(array $notification): array
                {
                    throw new \RuntimeException(
                        'Unknown transport termination'
                    );
                }
            }
        );

        $result = $worker->processOne($db);

        Assert::same(false, $result['safe_to_retry']);
        Assert::same('REVIEW', $result['status']);
    }

    public function testEmptyQueueDoesNothing(): void
    {
        $db = TestDatabase::fresh();

        $worker = $this->worker(
            new class implements NotificationDeliveryTransportInterface {
                public function deliver(array $notification): array
                {
                    Assert::fail('Transport must not run for an empty queue');
                }
            }
        );

        $result = $worker->processOne($db);

        Assert::same(true, $result['ok']);
        Assert::same(false, $result['processed']);
        Assert::same('EMPTY', $result['status']);
    }

    private function worker(
        NotificationDeliveryTransportInterface $transport
    ): NotificationDeliveryWorker {
        return new NotificationDeliveryWorker(
            new NotificationDeliveryLifecycleService(
                new NotificationOutboxRepository(new UuidGenerator()),
                60
            ),
            $transport
        );
    }

    private function enqueue(\PDO $db, string $key): void
    {
        (new NotificationOutboxRepository(new UuidGenerator()))->enqueue(
            $db,
            [
                'idempotency_key' => $key,
                'template_code' => 'WORKER_TEST',
                'template_version' => 'v1',
                'recipient_type' => 'INTERNAL',
                'recipient_hash' => hash(
                    'sha256',
                    'recipient@example.test'
                ),
                'payload' => [
                    'source_type' => 'WORKER_TEST',
                    'reference_hash' => hash('sha256', $key),
                ],
                'correlation_id' => 'WORKER-' . substr(
                    hash('sha256', $key),
                    0,
                    32
                ),
            ]
        );
    }
}
