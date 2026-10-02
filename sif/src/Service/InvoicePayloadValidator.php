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

        $this->assertIdempotencyKey($payload['idempotency_key']);
        $this->assertSourceChannel($payload['source_channel']);

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

        $this->assertExemptionReason($payload['totals']);

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

            $this->assertExemptionReason($line);
        }

        $this->assertLineTotalsMatchHeader($payload);

        return $payload;
    }

    private function assertIdempotencyKey(mixed $value): void
    {
        if (!is_string($value)) {
            throw SifException::validation('Invalid invoice idempotency key');
        }

        $key = trim($value);
        if ($key === '' || strlen($key) > 100) {
            throw SifException::validation('Invalid invoice idempotency key');
        }
    }

    private function assertSourceChannel(mixed $value): void
    {
        if (!is_string($value)) {
            throw SifException::validation('Invalid invoice source channel');
        }

        $channel = trim($value);
        if ($channel === '' || strlen($channel) > 30) {
            throw SifException::validation('Invalid invoice source channel');
        }
    }

    private function assertLineTotalsMatchHeader(array $payload): void
    {
        $sums = ['import_base' => 0, 'taxable_base' => 0, 'iva_import' => 0, 'total' => 0];

        foreach ($payload['lines'] as $line) {
            $sums['import_base'] += $this->toCents($line['import_base'] ?? $line['base']);
            $sums['taxable_base'] += $this->toCents($line['taxable_base'] ?? $line['base']);
            $sums['iva_import'] += $this->toCents($line['iva_import'] ?? '0.00');
            $sums['total'] += $this->toCents($line['total']);
        }

        $expected = [
            'import_base' => $this->toCents($payload['totals']['import_base']),
            'taxable_base' => $this->toCents($payload['totals']['taxable_base']),
            'iva_import' => $this->toCents($payload['totals']['iva_import'] ?? '0.00'),
            'total' => $this->toCents($payload['totals']['total']),
        ];

        foreach ($expected as $field => $value) {
            if ($sums[$field] !== $value) {
                throw SifException::validation("Invoice {$field} does not match line totals");
            }
        }
    }

    private function toCents(mixed $value): int
    {
        if (!is_numeric($value)) {
            throw SifException::validation('Invalid invoice monetary value');
        }

        return (int) round((float) $value * 100, 0, PHP_ROUND_HALF_UP);
    }

    private function assertExemptionReason(array $block): void
    {
        if (!array_key_exists('exemption_reason', $block) || $block['exemption_reason'] === null || $block['exemption_reason'] === '') {
            return;
        }

        $reason = strtoupper(trim((string) $block['exemption_reason']));
        if (!in_array($reason, ['E1', 'E2', 'E3', 'E4', 'E5', 'E6', 'E7', 'E8'], true)) {
            throw SifException::validation('Invalid exemption reason');
        }

        if (strtoupper(trim((string) ($block['iva_regim'] ?? ''))) !== 'EXEMPT') {
            throw SifException::validation('Exemption reason requires EXEMPT IVA regime');
        }
    }
}
