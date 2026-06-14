<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class ManualRectificationPayloadBuilder
{
    public function forOriginalInvoice(array $invoice, array $input): array
    {
        $amount = $this->amount($this->required($input, ['amount', 'import'], 'rectification amount'));
        $reason = strtoupper($this->requiredString($input, ['reason', 'motiu'], 'rectification reason'));
        $mode = $this->mode($this->requiredString($input, ['mode', 'mode_rectificacio'], 'rectification mode'));
        $numVisible = $this->requiredString($invoice, ['NUM_VISIBLE'], 'original invoice number');
        $sourceId = (int) ($invoice['ID'] ?? 0);

        return [
            'idempotency_key' => $this->idempotencyKey($numVisible, $mode, $reason, $amount, $input),
            'series' => 'R',
            'year' => (int) ($input['year'] ?? $invoice['ANY_FACT'] ?? date('Y')),
            'type' => strtoupper($this->optionalString($input, ['type', 'tipus_factura'], 'R1')),
            'source_channel' => 'INTRANET',
            'created_by' => $this->optionalString($input, ['created_by', 'user', 'usuari']),
            'billing' => $this->billing($invoice),
            'totals' => [
                'import_base' => $amount,
                'discount' => '0.00',
                'taxable_base' => $amount,
                'iva_regim' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => $amount,
            ],
            'lines' => [[
                'concept' => $this->optionalString($input, ['concept', 'concepte'], 'Rectificacio factura ' . $numVisible),
                'detail' => $this->optionalString($input, ['detail', 'details', 'detall'], $reason . ' / ' . $mode),
                'quantity' => '1.00',
                'unit_price' => $amount,
                'base' => $amount,
                'import_base' => $amount,
                'discount_amount' => '0.00',
                'taxable_base' => $amount,
                'iva_regim' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => $amount,
                'source_type' => 'RECTIFICATIVA',
                'source_id' => $sourceId > 0 ? $sourceId : null,
            ]],
            'relations' => [[
                'source_type' => 'RECTIFICATIVA',
                'source_id' => $sourceId > 0 ? $sourceId : null,
                'relation_type' => 'RECTIFIES',
                'visible_alumne' => 1,
            ]],
        ];
    }

    private function billing(array $invoice): array
    {
        return [
            'name' => $this->requiredString($invoice, ['BILLING_NOM_RAO'], 'billing name'),
            'nif' => $this->requiredString($invoice, ['BILLING_NIF_CIF'], 'billing NIF'),
            'address' => $invoice['BILLING_ADRECA'] ?? null,
            'cp' => $invoice['BILLING_CP'] ?? null,
            'city' => $invoice['BILLING_POBLACIO'] ?? null,
            'province' => $invoice['BILLING_PROVINCIA'] ?? null,
            'country' => $invoice['BILLING_PAIS'] ?? 'ES',
            'email' => $invoice['BILLING_EMAIL'] ?? null,
        ];
    }

    private function idempotencyKey(string $numVisible, string $mode, string $reason, string $amount, array $input): string
    {
        $reference = $this->optionalString($input, ['reference', 'referencia']);
        if ($reference !== null) {
            return 'RECTIFICATIVA|REF:' . $this->keyPart($reference);
        }

        return 'RECTIFICATIVA'
            . '|FACT:' . $this->keyPart($numVisible)
            . '|MODE:' . $this->keyPart($mode)
            . '|MOTIU:' . $this->keyPart($reason)
            . '|IMPORT:' . $amount;
    }

    private function mode(string $value): string
    {
        $mode = strtoupper(trim($value));
        if (!in_array($mode, ['DIFERENCIES', 'SUBSTITUCIO'], true)) {
            throw SifException::validation('Invalid rectification mode');
        }

        return $mode;
    }

    private function amount(mixed $value): string
    {
        if (!is_numeric($value)) {
            throw SifException::validation('Invalid rectification amount');
        }

        $amount = (float) $value;
        if (abs($amount) < 0.005) {
            throw SifException::validation('Invalid rectification amount');
        }

        return number_format($amount, 2, '.', '');
    }

    private function requiredString(array $data, array $keys, string $label): string
    {
        $value = $this->required($data, $keys, $label);
        $string = trim((string) $value);
        if ($string === '') {
            throw SifException::validation("Missing {$label}");
        }

        return $string;
    }

    private function required(array $data, array $keys, string $label): mixed
    {
        $value = $this->optional($data, $keys);
        if ($value === null || $value === '') {
            throw SifException::validation("Missing {$label}");
        }

        return $value;
    }

    private function optionalString(array $data, array $keys, ?string $default = null): ?string
    {
        $value = $this->optional($data, $keys);
        if ($value === null) {
            return $default;
        }

        $string = trim((string) $value);

        return $string === '' ? $default : $string;
    }

    private function optional(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }

        return null;
    }

    private function keyPart(string $value): string
    {
        $part = trim($value);
        $part = preg_replace('/\s+/', '_', $part);

        if ($part === null || $part === '') {
            return 'NULL';
        }

        return $part;
    }
}
