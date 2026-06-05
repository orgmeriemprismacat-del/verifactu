<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class InvoicePayloadValidator
{
    public function validate(array $payload): array
    {
        foreach (['idempotency_key', 'series', 'type', 'source_channel', 'billing', 'totals', 'lines'] as $key) {
            if (!array_key_exists($key, $payload)) {
                throw SifException::validation("Missing invoice field {$key}");
            }
        }

        if (!in_array($payload['series'], ['A', 'R'], true)) {
            throw SifException::validation('Invalid invoice series');
        }

        if (!in_array($payload['type'], ['F1', 'F2', 'R1', 'R2', 'R3', 'R4', 'R5'], true)) {
            throw SifException::validation('Invalid invoice type');
        }

        if (!is_array($payload['billing'])) {
            throw SifException::validation('Invalid billing block');
        }

        foreach (['name', 'nif'] as $key) {
            if (empty($payload['billing'][$key])) {
                throw SifException::validation("Missing billing field {$key}");
            }
        }

        if (!is_array($payload['totals'])) {
            throw SifException::validation('Invalid totals block');
        }

        foreach (['import_base', 'taxable_base', 'total'] as $key) {
            if (!isset($payload['totals'][$key]) || !is_numeric($payload['totals'][$key])) {
                throw SifException::validation("Invalid total {$key}");
            }
        }

        if (!is_array($payload['lines']) || count($payload['lines']) < 1) {
            throw SifException::validation('Invoice requires at least one line');
        }

        foreach ($payload['lines'] as $index => $line) {
            if (!is_array($line)) {
                throw SifException::validation("Invalid invoice line {$index}");
            }

            foreach (['concept', 'quantity', 'unit_price', 'base', 'total'] as $key) {
                if (!array_key_exists($key, $line)) {
                    throw SifException::validation("Missing invoice line field {$key}");
                }
            }

            foreach (['quantity', 'unit_price', 'base', 'total'] as $key) {
                if (!is_numeric($line[$key])) {
                    throw SifException::validation("Invalid invoice line amount {$key}");
                }
            }
        }

        return $payload;
    }
}
