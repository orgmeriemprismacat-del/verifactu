<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\RedsysNotificationRepository;

final class RedsysInvoicePayloadBuilder
{
    public function __construct(private RedsysNotificationRepository $notifications)
    {
    }

    public function buildFromValidatedNotification(\PDO $db, string $dsOrder, array $invoicePayload): array
    {
        $notification = $this->notifications->findByDsOrder($db, $dsOrder);
        if ($notification === null) {
            throw SifException::validation('Redsys notification not found');
        }

        if ((string) $notification['STATUS'] !== 'VALIDATED') {
            throw SifException::conflict('Redsys notification is not validated');
        }

        return $this->withRedsysPayment($invoicePayload, $notification);
    }

    private function withRedsysPayment(array $payload, array $notification): array
    {
        $dsOrder = (string) $notification['DS_ORDER'];
        $idpag = $notification['IDPAG'] === null ? null : (int) $notification['IDPAG'];
        $amount = number_format((float) $notification['IMPORT'], 2, '.', '');
        $sourceType = $this->invoiceSourceType($payload);

        $payload['idempotency_key'] = $this->invoiceIdempotencyKey($sourceType, $idpag, $dsOrder);
        $payload['source_channel'] = 'REDSYS';
        $payload['relations'] = $this->withRedsysRelations($payload['relations'] ?? [], $idpag, $dsOrder);
        $payload['payment'] = $this->withRedsysPaymentBlock($payload['payment'] ?? [], $notification, $amount);

        return $payload;
    }

    private function withRedsysRelations(array $relations, ?int $idpag, string $dsOrder): array
    {
        if ($relations === []) {
            $relations[] = [
                'source_type' => 'INSCRIPCIO',
                'source_id' => $idpag,
                'visible_alumne' => 1,
            ];
        }

        foreach ($relations as $index => $relation) {
            $relations[$index]['idpag'] = $idpag;
            $relations[$index]['ds_order'] = $dsOrder;
        }

        return $relations;
    }

    private function withRedsysPaymentBlock(array $payment, array $notification, string $amount): array
    {
        $dsOrder = (string) $notification['DS_ORDER'];
        $idpag = $notification['IDPAG'] === null ? null : (int) $notification['IDPAG'];

        return array_replace($payment, [
            'idempotency_key' => 'PAYMENT|REDSYS|ORDER:' . $dsOrder,
            'movement_type' => 'CHARGE',
            'method' => 'REDSYS',
            'source_channel' => 'REDSYS',
            'amount' => $amount,
            'movement_date' => (string) $notification['CREATED_AT'],
            'provider_ref' => $dsOrder,
            'ds_order' => $dsOrder,
            'idpag' => $idpag,
        ]);
    }

    private function invoiceIdempotencyKey(string $sourceType, ?int $idpag, string $dsOrder): string
    {
        return 'REDSYS|' . $sourceType . '|IDPAG:' . ($idpag === null ? 'NULL' : (string) $idpag) . '|ORDER:' . $dsOrder;
    }

    private function invoiceSourceType(array $payload): string
    {
        $sourceType = strtoupper(trim((string) ($payload['source_type'] ?? 'CURS')));

        return $sourceType === '' ? 'CURS' : $sourceType;
    }
}
