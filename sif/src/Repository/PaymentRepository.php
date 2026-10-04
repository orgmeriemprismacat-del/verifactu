<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
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

    public function findByExternalReceipt(\PDO $db, array $payload, bool $forUpdate = false): ?array
    {
        $dsOrder = trim((string) ($payload['ds_order'] ?? ''));
        $reference = trim((string) ($payload['reference'] ?? ''));

        if ($dsOrder === '' && $reference === '') {
            return null;
        }

        if ($dsOrder !== '') {
            $sql = 'SELECT * FROM payment_transaction WHERE DS_ORDER = ? ORDER BY ID ASC LIMIT 1';
            if ($forUpdate) {
                $sql .= ' FOR UPDATE';
            }
            $stmt = $db->prepare($sql);
            $stmt->execute([$dsOrder]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        if ($reference !== '') {
            $sql = 'SELECT * FROM payment_transaction WHERE REFERENCIA_BANCARIA = ? ORDER BY ID ASC LIMIT 1';
            if ($forUpdate) {
                $sql .= ' FOR UPDATE';
            }
            $stmt = $db->prepare($sql);
            $stmt->execute([$reference]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $row ?: null;
        }

        return null;
    }

    public function findAllocations(\PDO $db, string $uuidPayment): array
    {
        $stmt = $db->prepare(
            'SELECT UUID_FACTURA, IMPORT_ASSIGNAT, TIPUS_ASSIGNACIO
             FROM payment_allocation
             WHERE UUID_PAYMENT = ?
             ORDER BY UUID_FACTURA, IMPORT_ASSIGNAT, TIPUS_ASSIGNACIO'
        );
        $stmt->execute([$uuidPayment]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function createPayment(\PDO $db, array $payload): array
    {
        $this->assertInstallmentAllocationsAllowed($db, $payload);
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

    private function assertInstallmentAllocationsAllowed(\PDO $db, array $payload): void
    {
        $allocations = $payload['allocations'] ?? [];
        if (!is_array($allocations)) {
            return;
        }

        $hasInstallment = false;
        foreach ($allocations as $allocation) {
            if (is_array($allocation)
                && (string) ($allocation['allocation_type'] ?? '') === 'INSTALLMENT_PAYMENT') {
                $hasInstallment = true;
                break;
            }
        }

        if (!$hasInstallment) {
            return;
        }

        $inscriptionId = (int) ($payload['inscription_id'] ?? 0);
        if ($inscriptionId <= 0) {
            throw SifException::validation('Installment payment requires inscription_id');
        }

        foreach ($allocations as $allocation) {
            if (!is_array($allocation)
                || (string) ($allocation['allocation_type'] ?? '') !== 'INSTALLMENT_PAYMENT') {
                continue;
            }

            $uuidFactura = trim((string) ($allocation['uuid_factura'] ?? ''));
            if ($uuidFactura === '') {
                throw SifException::validation('Installment allocation requires invoice UUID');
            }

            $invoiceStmt = $db->prepare(
                'SELECT TOTAL FROM factura WHERE UUID_FACTURA = ? FOR UPDATE'
            );
            $invoiceStmt->execute([$uuidFactura]);
            $invoiceTotal = $invoiceStmt->fetchColumn();
            if ($invoiceTotal === false) {
                throw SifException::validation('Invoice not found for installment allocation');
            }

            $coverageStmt = $db->prepare(
                "SELECT 1
                 FROM fact_rels
                 WHERE UUID_FACTURA = ?
                   AND SOURCE_TYPE = 'INSCRIPCIO'
                   AND SOURCE_ID = ?
                   AND RELATION_TYPE = 'ORIGIN'
                 LIMIT 1"
            );
            $coverageStmt->execute([$uuidFactura, $inscriptionId]);
            if ($coverageStmt->fetchColumn() === false) {
                throw SifException::conflict('Inscription is not covered by the target invoice');
            }

            $charges = $this->sumAllocationsByMovementTypes(
                $db,
                $uuidFactura,
                ['CHARGE', 'COMPENSATION']
            );
            $refunds = $this->sumAllocationsByMovementTypes(
                $db,
                $uuidFactura,
                ['REFUND']
            );

            $pendingCents = max(
                0,
                $this->toCents((string) $invoiceTotal)
                - ($this->toCents($charges) - $this->toCents($refunds))
            );
            $requestedCents = $this->toCents((string) ($allocation['amount'] ?? '0'));

            if ($requestedCents <= 0) {
                throw SifException::validation('Installment allocation amount must be positive');
            }

            if ($requestedCents > $pendingCents) {
                throw SifException::conflict('Installment amount exceeds pending invoice balance');
            }
        }
    }

    private function toCents(string $amount): int
    {
        $normalized = str_replace(',', '.', trim($amount));
        $negative = str_starts_with($normalized, '-');
        if ($negative) {
            $normalized = substr($normalized, 1);
        }

        [$whole, $decimal] = array_pad(explode('.', $normalized, 2), 2, '0');
        $decimal = substr(str_pad($decimal, 2, '0'), 0, 2);
        $cents = ((int) $whole * 100) + (int) $decimal;

        return $negative ? -$cents : $cents;
    }

    private function hashPayload(array $payload): string
    {
        return (new PayloadIdempotencyValidator())->calculateHash($payload);
    }
}
