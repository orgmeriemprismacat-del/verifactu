<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\RedsysCallbackQueueRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;

final class RedsysCoursePaymentStatusService
{
    public function __construct(
        private RedsysPaymentIntentRepository $intents,
        private RedsysNotificationRepository $notifications,
        private RedsysCallbackQueueRepository $queue
    ) {
    }

    public function status(\PDO $db, string $dsOrder, int $idpag): array
    {
        $dsOrder = trim($dsOrder);
        if ($dsOrder === '' || strlen($dsOrder) > 40 || $idpag < 1) {
            throw SifException::validation('Invalid Redsys course payment status input');
        }

        $intent = $this->intents->findByDsOrder($db, $dsOrder);
        if ($intent === null
            || (string) ($intent['SOURCE_TYPE'] ?? '') !== 'CURS'
            || (int) ($intent['IDPAG'] ?? 0) !== $idpag
        ) {
            // Do not disclose whether a different DS_ORDER or IDPAG exists.
            throw SifException::notFound('Course payment status not found');
        }

        $notification = $this->notifications->findByDsOrder($db, $dsOrder);
        if ($notification === null) {
            return $this->result($dsOrder, $idpag, 'PENDING', null, null, null);
        }

        $notificationStatus = strtoupper((string) ($notification['STATUS'] ?? ''));
        if ($notificationStatus === 'ERROR') {
            return $this->result(
                $dsOrder,
                $idpag,
                'REJECTED',
                null,
                null,
                null
            );
        }

        if ($notificationStatus !== 'VALIDATED') {
            return $this->result($dsOrder, $idpag, 'PENDING', null, null, null);
        }

        $job = $this->queue->findByNotificationId($db, (int) $notification['ID']);
        if ($job === null) {
            // VALIDATED and not yet materialised in the queue is treated as transient.
            return $this->result($dsOrder, $idpag, 'PROCESSING', null, null, null);
        }

        $queueStatus = strtoupper((string) ($job['STATUS'] ?? ''));
        if ($queueStatus === 'PROCESSED') {
            $uuidFactura = $this->nullableString($job['UUID_FACTURA'] ?? null);
            $uuidPayment = $this->nullableString($job['UUID_PAYMENT'] ?? null);
            if ($uuidFactura === null || $uuidPayment === null) {
                return $this->result($dsOrder, $idpag, 'REVIEW', $queueStatus, null, null);
            }

            return $this->result(
                $dsOrder,
                $idpag,
                'CONFIRMED',
                $queueStatus,
                $uuidFactura,
                $uuidPayment
            );
        }

        if ($queueStatus === 'INCIDENT') {
            return $this->result($dsOrder, $idpag, 'REVIEW', $queueStatus, null, null);
        }

        return $this->result($dsOrder, $idpag, 'PROCESSING', $queueStatus, null, null);
    }

    private function result(
        string $dsOrder,
        int $idpag,
        string $status,
        ?string $queueStatus,
        ?string $uuidFactura,
        ?string $uuidPayment
    ): array {
        return [
            'ds_order' => $dsOrder,
            'idpag' => $idpag,
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
