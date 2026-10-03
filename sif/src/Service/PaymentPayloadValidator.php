<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class PaymentPayloadValidator
{
    public function validate(array $payload): array
    {
        foreach (['idempotency_key', 'movement_type', 'method', 'source_channel', 'amount', 'movement_date', 'allocations'] as $key) {
            if (!array_key_exists($key, $payload)) {
                throw SifException::validation("Missing payment field {$key}");
            }
        }

        foreach (['idempotency_key', 'source_channel', 'movement_date'] as $key) {
            if (!is_string($payload[$key]) || trim($payload[$key]) === '') {
                throw SifException::validation("Invalid payment field {$key}");
            }
        }

        if (!in_array($payload['movement_type'], ['CHARGE', 'REFUND', 'COMPENSATION'], true)) {
            throw SifException::validation('Invalid movement type');
        }

        if (!in_array($payload['method'], ['REDSYS', 'TRANSFERENCIA', 'COMPENSACIO', 'MANUAL'], true)) {
            throw SifException::validation('Invalid payment method');
        }

        $paymentCents = $this->positiveMoneyToCents($payload['amount'], 'Invalid payment amount');

        if (!is_array($payload['allocations']) || count($payload['allocations']) < 1) {
            throw SifException::validation('Payment requires at least one allocation');
        }

        $allocatedCents = 0;
        foreach ($payload['allocations'] as $index => $allocation) {
            if (!is_array($allocation)) {
                throw SifException::validation("Invalid payment allocation {$index}");
            }

            foreach (['uuid_factura', 'amount', 'allocation_type'] as $key) {
                if (!array_key_exists($key, $allocation)) {
                    throw SifException::validation("Missing payment allocation field {$key}");
                }
            }

            if (!is_string($allocation['uuid_factura']) || trim($allocation['uuid_factura']) === '') {
                throw SifException::validation('Invalid payment allocation invoice');
            }

            if (!is_string($allocation['allocation_type']) || trim($allocation['allocation_type']) === '') {
                throw SifException::validation('Invalid payment allocation type');
            }

            $allocatedCents += $this->positiveMoneyToCents(
                $allocation['amount'],
                'Invalid payment allocation amount'
            );
        }

        if ($allocatedCents !== $paymentCents) {
            throw SifException::validation('Payment allocations must equal payment amount');
        }

        return $payload;
    }

    private function positiveMoneyToCents(mixed $value, string $message): int
    {
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            throw SifException::validation($message);
        }

        $raw = trim((string) $value);
        if (preg_match('/^\d+(?:\.\d{1,2})?$/D', $raw) !== 1) {
            throw SifException::validation($message);
        }

        [$whole, $decimal] = array_pad(explode('.', $raw, 2), 2, '0');
        $decimal = str_pad($decimal, 2, '0');
        $cents = ((int) $whole * 100) + (int) $decimal;

        if ($cents <= 0) {
            throw SifException::validation($message);
        }

        return $cents;
    }
}
