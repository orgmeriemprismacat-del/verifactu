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

        return $payload;
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
