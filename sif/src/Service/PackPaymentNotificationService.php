<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\NotificationOutboxRepository;

final class PackPaymentNotificationService
{
    public function __construct(private NotificationOutboxRepository $outbox)
    {
    }

    public function enqueue(
        \PDO $db,
        string $dsOrder,
        array $snapshot,
        array $invoiceResult
    ): array {
        $pack = $snapshot['pack'] ?? null;
        $items = $snapshot['items'] ?? null;
        if (!is_array($pack) || !is_array($items) || $items === []) {
            throw SifException::validation('Invalid pack notification snapshot');
        }

        $firstInscription = $items[0]['inscription'] ?? null;
        if (!is_array($firstInscription)) {
            throw SifException::validation('Missing pack notification recipient source');
        }

        $email = strtolower(trim((string) ($firstInscription['CORREU'] ?? $firstInscription['correu'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw SifException::validation('Invalid pack notification recipient');
        }

        $idpag = $snapshot['payment']['idpag'] ?? $firstInscription['IDPAG'] ?? null;
        if (!is_numeric($idpag) || (int) $idpag <= 0) {
            throw SifException::validation('Missing pack notification IDPAG');
        }

        $packId = $pack['ID_PACK'] ?? $pack['id_pack'] ?? null;
        if (!is_numeric($packId) || (int) $packId <= 0) {
            throw SifException::validation('Missing pack notification pack ID');
        }

        $inscriptionIds = [];
        foreach ($items as $item) {
            $id = $item['inscription']['ID'] ?? null;
            if (!is_numeric($id) || (int) $id <= 0) {
                throw SifException::validation('Invalid pack notification inscription ID');
            }
            $inscriptionIds[] = (int) $id;
        }

        return $this->outbox->enqueue($db, [
            'idempotency_key' => 'NOTIFY|PACK_PAYMENT_CONFIRMED|ORDER:' . $dsOrder,
            'template_code' => 'PACK_PAYMENT_CONFIRMED',
            'template_version' => 'v1',
            'recipient_type' => 'ALUMNE',
            'recipient_hash' => hash('sha256', $email),
            'uuid_factura' => $invoiceResult['uuid_factura'] ?? null,
            'uuid_payment' => $invoiceResult['uuid_payment'] ?? null,
            'correlation_id' => 'REDSYS|' . $dsOrder,
            'payload' => [
                'source_type' => 'PACK',
                'ds_order' => $dsOrder,
                'idpag' => (int) $idpag,
                'pack_id' => (int) $packId,
                'pack_title' => (string) ($pack['TITOL'] ?? ''),
                'inscription_ids' => $inscriptionIds,
                'num_visible' => (string) ($invoiceResult['num_visible'] ?? ''),
                'recipient_resolution' => 'LEGACY_IDPAG_AND_HASH',
            ],
        ]);
    }
}
