<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Repository\NotificationOutboxRepository;

final class NotificationDeliveryLifecycleService
{
    public function __construct(
        private NotificationOutboxRepository $outbox,
        private int $retryDelaySeconds = 300
    ) {
        if ($this->retryDelaySeconds < 1 || $this->retryDelaySeconds > 86400) {
            throw new \InvalidArgumentException(
                'Notification retry delay must be between 1 and 86400 seconds'
            );
        }
    }

    public function claimNext(
        \PDO $db,
        string $channel = 'EMAIL'
    ): ?array {
        if ($db->inTransaction()) {
            throw new \LogicException(
                'Notification claim owns its transaction'
            );
        }

        $db->beginTransaction();
        try {
            $claimed = $this->outbox->claimNext($db, $channel);
            $db->commit();

            return $claimed;
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    public function acknowledgeSent(
        \PDO $db,
        string $attemptUuid,
        ?string $providerRef = null
    ): array {
        return $this->transactional(
            $db,
            fn (\PDO $tx): array => $this->outbox->acknowledgeSent(
                $tx,
                $attemptUuid,
                $providerRef
            )
        );
    }

    public function failBeforeSend(
        \PDO $db,
        string $attemptUuid,
        string $errorCode,
        string $errorDetail,
        ?\DateTimeImmutable $now = null
    ): array {
        $now ??= new \DateTimeImmutable(
            'now',
            new \DateTimeZone('UTC')
        );
        $nextAttemptAt = $now
            ->setTimezone(new \DateTimeZone('UTC'))
            ->modify('+' . $this->retryDelaySeconds . ' seconds')
            ->format('Y-m-d H:i:s');

        return $this->transactional(
            $db,
            fn (\PDO $tx): array => $this->outbox->failBeforeSend(
                $tx,
                $attemptUuid,
                $errorCode,
                $errorDetail,
                $nextAttemptAt
            )
        );
    }

    public function markUncertain(
        \PDO $db,
        string $attemptUuid,
        string $errorCode,
        string $errorDetail
    ): array {
        return $this->transactional(
            $db,
            fn (\PDO $tx): array => $this->outbox->markUncertain(
                $tx,
                $attemptUuid,
                $errorCode,
                $errorDetail
            )
        );
    }

    private function transactional(
        \PDO $db,
        callable $callback
    ): array {
        if ($db->inTransaction()) {
            throw new \LogicException(
                'Notification delivery lifecycle owns its transaction'
            );
        }

        $db->beginTransaction();
        try {
            $result = $callback($db);
            $db->commit();

            return $result;
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }
}
