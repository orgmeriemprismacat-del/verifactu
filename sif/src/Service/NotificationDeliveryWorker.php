<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Contract\NotificationDeliveryTransportInterface;
use Prisma\Sif\Exception\NotificationPreSendException;
use Prisma\Sif\Repository\NotificationOutboxRepository;

final class NotificationDeliveryWorker
{
    public function __construct(
        private NotificationDeliveryLifecycleService $lifecycle,
        private NotificationDeliveryTransportInterface $transport
    ) {
    }

    public function processOne(\PDO $db): array
    {
        $notification = $this->lifecycle->claimNext($db, 'EMAIL');
        if ($notification === null) {
            return [
                'ok' => true,
                'processed' => false,
                'status' => 'EMPTY',
            ];
        }

        $attemptUuid = (string) $notification['ATTEMPT_UUID'];

        try {
            $delivery = $this->transport->deliver($notification);
            if (!is_array($delivery)) {
                throw new \RuntimeException(
                    'Notification transport returned an invalid result'
                );
            }

            $providerRef = $delivery['provider_ref'] ?? null;
            $ack = $this->lifecycle->acknowledgeSent(
                $db,
                $attemptUuid,
                $providerRef === null ? null : (string) $providerRef
            );

            return [
                'ok' => true,
                'processed' => true,
                'uuid_notification' =>
                    (string) $notification['UUID_NOTIFICATION'],
                'attempt_uuid' => $attemptUuid,
                'status' => $ack['status'],
            ];
        } catch (NotificationPreSendException $exception) {
            $retry = $this->lifecycle->failBeforeSend(
                $db,
                $attemptUuid,
                'PRE_SEND_FAILURE',
                $exception->getMessage()
            );

            return [
                'ok' => false,
                'processed' => true,
                'uuid_notification' =>
                    (string) $notification['UUID_NOTIFICATION'],
                'attempt_uuid' => $attemptUuid,
                'status' => $retry['status'],
                'safe_to_retry' => true,
                'error' => $exception->getMessage(),
            ];
        } catch (\Throwable $exception) {
            $review = $this->lifecycle->markUncertain(
                $db,
                $attemptUuid,
                'DELIVERY_RESULT_UNCERTAIN',
                $exception->getMessage()
            );

            return [
                'ok' => false,
                'processed' => true,
                'uuid_notification' =>
                    (string) $notification['UUID_NOTIFICATION'],
                'attempt_uuid' => $attemptUuid,
                'status' => $review['status'],
                'safe_to_retry' => false,
                'requires_review' => true,
                'error' => $exception->getMessage(),
            ];
        }
    }
}
