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

        if (!in_array($payload['movement_type'], ['CHARGE', 'REFUND', 'COMPENSATION'], true)) {
            throw SifException::validation('Invalid movement type');
        }

        if (!in_array($payload['method'], ['REDSYS', 'TRANSFERENCIA', 'COMPENSACIO', 'MANUAL'], true)) {
            throw SifException::validation('Invalid payment method');
        }

        if (!is_numeric($payload['amount'])) {
            throw SifException::validation('Invalid payment amount');
        }

        if (!is_array($payload['allocations']) || count($payload['allocations']) < 1) {
            throw SifException::validation('Payment requires at least one allocation');
        }

        foreach ($payload['allocations'] as $index => $allocation) {
            if (!is_array($allocation)) {
                throw SifException::validation("Invalid payment allocation {$index}");
            }

            foreach (['uuid_factura', 'amount', 'allocation_type'] as $key) {
                if (!array_key_exists($key, $allocation)) {
                    throw SifException::validation("Missing payment allocation field {$key}");
                }
            }

            if (!is_numeric($allocation['amount'])) {
                throw SifException::validation('Invalid payment allocation amount');
            }
        }

        return $payload;
    }
}
