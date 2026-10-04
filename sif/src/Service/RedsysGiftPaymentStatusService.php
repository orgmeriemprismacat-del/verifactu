<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\RedsysCallbackQueueRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;

final class RedsysGiftPaymentStatusService
{
    public function __construct(
        private RedsysPaymentIntentRepository $intents,
        private RedsysNotificationRepository $notifications,
        private RedsysCallbackQueueRepository $queue
    ) {
    }

    public function status(\PDO $db, string $dsOrder): array
    {
        $dsOrder = trim($dsOrder);
        if ($dsOrder === '' || strlen($dsOrder) > 40) {
            throw SifException::validation('Invalid Redsys gift payment status input');
        }

        $intent = $this->intents->findByDsOrder($db, $dsOrder);
        if ($intent === null || (string) ($intent['SOURCE_TYPE'] ?? '') !== 'REGAL') {
            throw SifException::notFound('Gift payment status not found');
        }

        $giftId = (int) ($intent['SOURCE_ID'] ?? 0);
        if ($giftId < 1) {
            throw SifException::conflict('Gift payment intent has invalid source identity');
        }

        $notification = $this->notifications->findByDsOrder($db, $dsOrder);
        if ($notification === null) {
            return $this->result($dsOrder, $giftId, 'PENDING', null, null, null);
        }

        $notificationStatus = strtoupper((string) ($notification['STATUS'] ?? ''));
        if ($notificationStatus === 'ERROR') {
            return $this->result($dsOrder, $giftId, 'REJECTED', null, null, null);
        }

        if ($notificationStatus !== 'VALIDATED') {
            return $this->result($dsOrder, $giftId, 'PENDING', null, null, null);
        }

        $job = $this->queue->findByNotificationId($db, (int) $notification['ID']);
        if ($job === null) {
            return $this->result($dsOrder, $giftId, 'PROCESSING', null, null, null);
        }

        $queueStatus = strtoupper((string) ($job['STATUS'] ?? ''));
        if ($queueStatus === 'PROCESSED') {
            $uuidFactura = $this->nullableString($job['UUID_FACTURA'] ?? null);
            $uuidPayment = $this->nullableString($job['UUID_PAYMENT'] ?? null);
            if ($uuidFactura === null || $uuidPayment === null) {
                return $this->result($dsOrder, $giftId, 'REVIEW', $queueStatus, null, null);
            }

            return $this->result(
                $dsOrder,
                $giftId,
                'CONFIRMED',
                $queueStatus,
                $uuidFactura,
                $uuidPayment
            );
        }

        if ($queueStatus === 'INCIDENT') {
            return $this->result($dsOrder, $giftId, 'REVIEW', $queueStatus, null, null);
        }

        return $this->result($dsOrder, $giftId, 'PROCESSING', $queueStatus, null, null);
    }

    private function result(
        string $dsOrder,
        int $giftId,
        string $status,
        ?string $queueStatus,
        ?string $uuidFactura,
        ?string $uuidPayment
    ): array {
        return [
            'ds_order' => $dsOrder,
            'gift_id' => $giftId,
            'status' => $status,
            'queue_status' => $queueStatus,
            'uuid_factura' => $uuidFactura,
            'uuid_payment' => $uuidPayment,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
