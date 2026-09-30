<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

final class EnrollmentFundMovementRepository
{
    public function __construct(private UuidGenerator $uuidGenerator)
    {
    }

    public function lockPayment(\PDO $db, string $uuidPayment): array
    {
        $stmt = $db->prepare(
            'SELECT UUID_PAYMENT, TIPUS_MOVIMENT, IMPORT, ESTAT
             FROM payment_transaction
             WHERE UUID_PAYMENT = ?
             FOR UPDATE'
        );
        $stmt->execute([$uuidPayment]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw SifException::validation('Payment not found for enrollment fund allocation');
        }

        return $row;
    }

    public function findInvoiceLineForInscription(
        \PDO $db,
        string $uuidFactura,
        int $idInsc
    ): array {
        $stmt = $db->prepare(
            "SELECT ID, ORDRE, TOTAL
             FROM factura_linia
             WHERE UUID_FACTURA = ?
               AND SOURCE_TYPE = 'INSCRIPCIO'
               AND SOURCE_ID = ?
             FOR UPDATE"
        );
        $stmt->execute([$uuidFactura, $idInsc]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) !== 1) {
            throw SifException::conflict('Expected exactly one invoice line for enrollment allocation');
        }

        return $rows[0];
    }

    public function insertOrReuseExternalAllocation(
        \PDO $db,
        array $movement
    ): array {
        foreach ([
            'idempotency_key',
            'order',
            'uuid_payment',
            'uuid_factura',
            'invoice_line_id',
            'id_insc',
            'amount',
            'correlation_id',
        ] as $field) {
            if (!array_key_exists($field, $movement)
                || $movement[$field] === null
                || $movement[$field] === ''
            ) {
                throw SifException::validation('Missing enrollment fund movement field ' . $field);
            }
        }

        $normalized = [
            'uuid_movement' => $this->uuidGenerator->generate(),
            'idempotency_key' => trim((string) $movement['idempotency_key']),
            'movement_type' => 'EXTERNAL_ALLOCATION',
            'order' => (int) $movement['order'],
            'uuid_payment' => trim((string) $movement['uuid_payment']),
            'uuid_factura' => trim((string) $movement['uuid_factura']),
            'invoice_line_id' => (int) $movement['invoice_line_id'],
            'id_insc' => (int) $movement['id_insc'],
            'amount' => $this->money($movement['amount']),
            'currency' => strtoupper(trim((string) ($movement['currency'] ?? 'EUR'))),
            'uuid_operation' => $this->optionalString($movement['uuid_operation'] ?? null),
            'correlation_id' => trim((string) $movement['correlation_id']),
            'notes' => $this->optionalString($movement['notes'] ?? null),
        ];

        if ($normalized['order'] <= 0
            || $normalized['invoice_line_id'] <= 0
            || $normalized['id_insc'] <= 0
            || (float) $normalized['amount'] <= 0
        ) {
            throw SifException::validation('Invalid enrollment fund movement values');
        }

        $existing = $this->findByIdempotencyKey($db, $normalized['idempotency_key'], true);
        if ($existing !== null) {
            $this->assertMatches($existing, $normalized);
            return $this->result($existing, true);
        }

        try {
            $db->prepare(
                'INSERT INTO enrollment_fund_movement (
                    UUID_MOVEMENT, IDEMPOTENCY_KEY, MOVEMENT_TYPE, ORDRE,
                    UUID_PAYMENT, UUID_FACTURA, ID_FACTURA_LINIA,
                    ID_INSC_ORIGEN, ID_INSC_DESTI, IMPORT, CURRENCY,
                    UUID_OPERATION, CORRELATION_ID, NOTES
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $normalized['uuid_movement'],
                $normalized['idempotency_key'],
                $normalized['movement_type'],
                $normalized['order'],
                $normalized['uuid_payment'],
                $normalized['uuid_factura'],
                $normalized['invoice_line_id'],
                $normalized['id_insc'],
                $normalized['amount'],
                $normalized['currency'],
                $normalized['uuid_operation'],
                $normalized['correlation_id'],
                $normalized['notes'],
            ]);
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            $existing = $this->findByIdempotencyKey($db, $normalized['idempotency_key'], true);
            if ($existing === null) {
                throw $exception;
            }
            $this->assertMatches($existing, $normalized);
            return $this->result($existing, true);
        }

        $created = $this->findByIdempotencyKey($db, $normalized['idempotency_key'], true);
        if ($created === null) {
            throw new \RuntimeException('Created enrollment fund movement could not be loaded');
        }

        return $this->result($created, false);
    }

    public function insertOrReuseCompensationAllocation(
        \PDO $db,
        array $movement
    ): array {
        foreach ([
            'idempotency_key',
            'order',
            'uuid_payment',
            'id_insc',
            'amount',
            'correlation_id',
        ] as $field) {
            if (!array_key_exists($field, $movement)
                || $movement[$field] === null
                || $movement[$field] === ''
            ) {
                throw SifException::validation(
                    'Missing compensation enrollment fund movement field ' . $field
                );
            }
        }

        $normalized = [
            'uuid_movement' => $this->uuidGenerator->generate(),
            'idempotency_key' => trim((string) $movement['idempotency_key']),
            'movement_type' => 'COMPENSATION_ALLOCATION',
            'order' => (int) $movement['order'],
            'uuid_payment' => trim((string) $movement['uuid_payment']),
            'id_insc' => (int) $movement['id_insc'],
            'amount' => $this->money($movement['amount']),
            'currency' => strtoupper(trim((string) ($movement['currency'] ?? 'EUR'))),
            'uuid_operation' => $this->optionalString($movement['uuid_operation'] ?? null),
            'correlation_id' => trim((string) $movement['correlation_id']),
            'notes' => $this->optionalString($movement['notes'] ?? null),
        ];

        if ($normalized['idempotency_key'] === ''
            || strlen($normalized['idempotency_key']) > 160
            || $normalized['order'] <= 0
            || $normalized['id_insc'] <= 0
            || (float) $normalized['amount'] <= 0
            || $normalized['correlation_id'] === ''
            || strlen($normalized['correlation_id']) > 120
            || $normalized['currency'] === ''
        ) {
            throw SifException::validation('Invalid compensation enrollment fund movement values');
        }

        $payment = $this->lockPayment($db, $normalized['uuid_payment']);
        if ((string) $payment['TIPUS_MOVIMENT'] !== 'CHARGE'
            || (string) $payment['ESTAT'] !== 'CONFIRMED'
        ) {
            throw SifException::conflict(
                'Compensation allocation requires a confirmed origin charge'
            );
        }

        $existing = $this->findByIdempotencyKey($db, $normalized['idempotency_key'], true);
        if ($existing !== null) {
            $this->assertCompensationMatches($existing, $normalized);
            return $this->result($existing, true);
        }

        try {
            $db->prepare(
                'INSERT INTO enrollment_fund_movement (
                    UUID_MOVEMENT, IDEMPOTENCY_KEY, MOVEMENT_TYPE, ORDRE,
                    UUID_PAYMENT, UUID_FACTURA, ID_FACTURA_LINIA,
                    ID_INSC_ORIGEN, ID_INSC_DESTI, IMPORT, CURRENCY,
                    UUID_OPERATION, CORRELATION_ID, NOTES
                 ) VALUES (?, ?, ?, ?, ?, NULL, NULL, NULL, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $normalized['uuid_movement'],
                $normalized['idempotency_key'],
                $normalized['movement_type'],
                $normalized['order'],
                $normalized['uuid_payment'],
                $normalized['id_insc'],
                $normalized['amount'],
                $normalized['currency'],
                $normalized['uuid_operation'],
                $normalized['correlation_id'],
                $normalized['notes'],
            ]);
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            $existing = $this->findByIdempotencyKey($db, $normalized['idempotency_key'], true);
            if ($existing === null) {
                throw $exception;
            }
            $this->assertCompensationMatches($existing, $normalized);
            return $this->result($existing, true);
        }

        $created = $this->findByIdempotencyKey($db, $normalized['idempotency_key'], true);
        if ($created === null) {
            throw new \RuntimeException(
                'Created compensation enrollment fund movement could not be loaded'
            );
        }

        return $this->result($created, false);
    }

    public function findByIdempotencyKey(
        \PDO $db,
        string $key,
        bool $forUpdate = false
    ): ?array {
        $sql =
            'SELECT UUID_MOVEMENT, IDEMPOTENCY_KEY, MOVEMENT_TYPE, ORDRE,
                    UUID_PAYMENT, UUID_FACTURA, ID_FACTURA_LINIA,
                    ID_INSC_ORIGEN, ID_INSC_DESTI, IMPORT, CURRENCY,
                    UUID_OPERATION, CORRELATION_ID, NOTES
             FROM enrollment_fund_movement
             WHERE IDEMPOTENCY_KEY = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function assertMatches(array $existing, array $movement): void
    {
        $matches =
            (string) $existing['MOVEMENT_TYPE'] === $movement['movement_type']
            && (int) $existing['ORDRE'] === $movement['order']
            && (string) $existing['UUID_PAYMENT'] === $movement['uuid_payment']
            && (string) $existing['UUID_FACTURA'] === $movement['uuid_factura']
            && (int) $existing['ID_FACTURA_LINIA'] === $movement['invoice_line_id']
            && $existing['ID_INSC_ORIGEN'] === null
            && (int) $existing['ID_INSC_DESTI'] === $movement['id_insc']
            && $this->money($existing['IMPORT']) === $movement['amount']
            && (string) $existing['CURRENCY'] === $movement['currency']
            && (string) ($existing['UUID_OPERATION'] ?? '') === (string) ($movement['uuid_operation'] ?? '')
            && (string) $existing['CORRELATION_ID'] === $movement['correlation_id'];

        if (!$matches) {
            throw SifException::conflict(
                'Enrollment fund idempotency key already exists with different payload'
            );
        }
    }

    private function assertCompensationMatches(array $existing, array $movement): void
    {
        $matches =
            (string) $existing['MOVEMENT_TYPE'] === $movement['movement_type']
            && (int) $existing['ORDRE'] === $movement['order']
            && (string) $existing['UUID_PAYMENT'] === $movement['uuid_payment']
            && $existing['UUID_FACTURA'] === null
            && $existing['ID_FACTURA_LINIA'] === null
            && $existing['ID_INSC_ORIGEN'] === null
            && (int) $existing['ID_INSC_DESTI'] === $movement['id_insc']
            && $this->money($existing['IMPORT']) === $movement['amount']
            && (string) $existing['CURRENCY'] === $movement['currency']
            && (string) ($existing['UUID_OPERATION'] ?? '')
                === (string) ($movement['uuid_operation'] ?? '');

        if (!$matches) {
            throw SifException::conflict(
                'Compensation allocation idempotency key already exists with different payload'
            );
        }
    }

    private function result(array $row, bool $reused): array
    {
        return [
            'uuid_movement' => (string) $row['UUID_MOVEMENT'],
            'id_insc' => (int) $row['ID_INSC_DESTI'],
            'amount' => $this->money($row['IMPORT']),
            'idempotency_reused' => $reused,
        ];
    }

    private function money(mixed $value): string
    {
        if (!is_numeric($value)) {
            throw SifException::validation('Invalid enrollment fund amount');
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
