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
        $this->assertTraceMetadata($payload);
        $payload['idempotency_key'] = trim((string) $payload['idempotency_key']);
        $payload['source_channel'] = strtoupper(trim((string) $payload['source_channel']));

        if (!in_array($payload['series'], ['A', 'R'], true)) {
            throw SifException::validation('Invalid invoice series');
        }

        if (!in_array($payload['type'], ['F1', 'F2', 'R1', 'R2', 'R3', 'R4', 'R5'], true)) {
            throw SifException::validation('Invalid invoice type');
        }

        $ordinary = in_array($payload['type'], ['F1', 'F2'], true);
        if (($payload['series'] === 'A') !== $ordinary) {
            throw SifException::validation('Invoice series does not match invoice type');
        }

        if (!is_array($payload['billing'])) {
            throw SifException::validation('Invalid billing block');
        }

        foreach (['name', 'nif'] as $key) {
            if (!isset($payload['billing'][$key]) || !is_string($payload['billing'][$key])
                || trim($payload['billing'][$key]) === '') {
                throw SifException::validation("Missing billing field {$key}");
            }
            $payload['billing'][$key] = trim($payload['billing'][$key]);
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

            if (!is_string($line['concept']) || trim($line['concept']) === '') {
                throw SifException::validation("Invalid invoice line concept {$index}");
            }
            $payload['lines'][$index]['concept'] = trim($line['concept']);

            foreach (['quantity', 'unit_price', 'base', 'total'] as $key) {
                if (!is_numeric($line[$key])) {
                    throw SifException::validation("Invalid invoice line amount {$key}");
                }
            }

            $this->assertExemptionReason($line);
            $this->assertOperationLineUuid($line);
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

    private function assertTraceMetadata(array $payload): void
    {
        foreach (['request_id' => 120, 'correlation_id' => 120, 'actor_role' => 80] as $field => $maxLength) {
            if (!array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
                continue;
            }
            if (!is_string($payload[$field])) {
                throw SifException::validation("Invalid invoice trace field {$field}");
            }

            $value = trim($payload[$field]);
            if ($value === '' || $value !== $payload[$field] || mb_strlen($value, 'UTF-8') > $maxLength) {
                throw SifException::validation("Invalid invoice trace field {$field}");
            }
        }

        if (array_key_exists('actor_type', $payload)
            && $payload['actor_type'] !== null
            && $payload['actor_type'] !== ''
        ) {
            if (!is_string($payload['actor_type'])) {
                throw SifException::validation('Invalid invoice trace field actor_type');
            }
            $actorType = strtoupper(trim($payload['actor_type']));
            if ($actorType !== $payload['actor_type']
                || !in_array($actorType, ['HUMAN', 'SYSTEM', 'PROCESS'], true)
            ) {
                throw SifException::validation('Invalid invoice trace field actor_type');
            }
        }
    }

    private function assertLineTotalsMatchHeader(array $payload): void
    {
        $sums = ['import_base' => 0, 'discount' => 0, 'taxable_base' => 0, 'iva_import' => 0, 'total' => 0];

        foreach ($payload['lines'] as $line) {
            $sums['import_base'] += $this->toCents($line['import_base'] ?? $line['base']);
            $sums['discount'] += $this->toCents($line['discount_amount'] ?? '0.00');
            $sums['taxable_base'] += $this->toCents($line['taxable_base'] ?? $line['base']);
            $sums['iva_import'] += $this->toCents($line['iva_import'] ?? '0.00');
            $sums['total'] += $this->toCents($line['total']);
        }

        $expected = [
            'import_base' => $this->toCents($payload['totals']['import_base']),
            'discount' => $this->toCents($payload['totals']['discount'] ?? '0.00'),
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

    private function assertOperationLineUuid(array $line): void
    {
        if (!array_key_exists('uuid_operation_line', $line)
            || $line['uuid_operation_line'] === null
            || $line['uuid_operation_line'] === ''
        ) {
            return;
        }

        $uuid = strtolower(trim((string) $line['uuid_operation_line']));
        if (preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D',
            $uuid
        ) !== 1) {
            throw SifException::validation('Invalid commercial operation line UUID');
        }
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
