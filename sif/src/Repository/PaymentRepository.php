<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Service\PayloadIdempotencyValidator;

final class PaymentRepository
{
    public function __construct(
        private UuidGenerator $uuidGenerator,
        private PaymentStatusCalculator $statusCalculator
    ) {
    }

    public function findByIdempotencyKey(\PDO $db, string $key, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM payment_transaction WHERE IDEMPOTENCY_KEY = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function createPayment(\PDO $db, array $payload): array
    {
        $uuid = $this->uuidGenerator->generate();

        $db->prepare(
            'INSERT INTO payment_transaction (
                UUID_PAYMENT, IDEMPOTENCY_KEY, TIPUS_MOVIMENT, METODE, SOURCE_CHANNEL,
                IMPORT, DATA_MOVIMENT, PROVIDER_REF, DS_ORDER, IDPAG, REFERENCIA_BANCARIA,
                PAYLOAD_HASH, PAYLOAD_HASH_VERSION, ESTAT, NOTES
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 2, \'CONFIRMED\', ?)'
        )->execute([
            $uuid,
            $payload['idempotency_key'],
            $payload['movement_type'],
            $payload['method'],
            $payload['source_channel'],
            $payload['amount'],
            $payload['movement_date'],
            $payload['provider_ref'] ?? null,
            $payload['ds_order'] ?? null,
            $payload['idpag'] ?? null,
            $payload['reference'] ?? null,
            $this->hashPayload($payload),
            $payload['notes'] ?? null,
        ]);

        foreach ($payload['allocations'] as $allocation) {
            $this->createAllocation($db, $uuid, $allocation);
            $this->refreshInvoicePaymentStatus($db, $allocation['uuid_factura']);
        }

        return [
            'uuid_payment' => $uuid,
        ];
    }

    private function createAllocation(\PDO $db, string $uuidPayment, array $allocation): void
    {
        $db->prepare(
            'INSERT INTO payment_allocation (
                UUID_PAYMENT, UUID_FACTURA, IMPORT_ASSIGNAT, TIPUS_ASSIGNACIO
            ) VALUES (?, ?, ?, ?)'
        )->execute([
            $uuidPayment,
            $allocation['uuid_factura'],
            $allocation['amount'],
            $allocation['allocation_type'],
        ]);
    }

    private function refreshInvoicePaymentStatus(\PDO $db, string $uuidFactura): void
    {
        $totalStmt = $db->prepare('SELECT TOTAL FROM factura WHERE UUID_FACTURA = ? FOR UPDATE');
        $totalStmt->execute([$uuidFactura]);
        $total = $totalStmt->fetchColumn();

        if ($total === false) {
            throw new \RuntimeException('Invoice not found for payment allocation.');
        }

        $charges = $this->sumAllocationsByMovementTypes($db, $uuidFactura, ['CHARGE', 'COMPENSATION']);
        $refunds = $this->sumAllocationsByMovementTypes($db, $uuidFactura, ['REFUND']);
        $status = $this->statusCalculator->calculate((string) $total, $charges, $refunds);

        $db->prepare('UPDATE factura SET ESTAT_COBRAMENT = ? WHERE UUID_FACTURA = ?')
            ->execute([$status, $uuidFactura]);
    }

    private function sumAllocationsByMovementTypes(\PDO $db, string $uuidFactura, array $movementTypes): string
    {
        $marks = implode(', ', array_fill(0, count($movementTypes), '?'));
        $stmt = $db->prepare(
            "SELECT COALESCE(SUM(pa.IMPORT_ASSIGNAT), 0)
             FROM payment_allocation pa
             JOIN payment_transaction pt ON pt.UUID_PAYMENT = pa.UUID_PAYMENT
             WHERE pa.UUID_FACTURA = ? AND pt.TIPUS_MOVIMENT IN ({$marks})"
        );
        $stmt->execute(array_merge([$uuidFactura], $movementTypes));

        return number_format((float) $stmt->fetchColumn(), 2, '.', '');
    }

    private function hashPayload(array $payload): string
    {
        return (new PayloadIdempotencyValidator())->calculateHash($payload);
    }
}
