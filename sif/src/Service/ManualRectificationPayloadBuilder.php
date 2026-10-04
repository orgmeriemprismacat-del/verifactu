<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class ManualRectificationPayloadBuilder
{
    public function forOriginalInvoice(array $invoice, array $input): array
    {
        $reason = strtoupper($this->requiredString($input, ['reason', 'motiu'], 'rectification reason'));
        $mode = $this->mode($this->requiredString($input, ['mode', 'mode_rectificacio'], 'rectification mode'));
        $numVisible = $this->requiredString($invoice, ['NUM_VISIBLE'], 'original invoice number');
        $sourceId = (int) ($invoice['ID'] ?? 0);
        $totals = $this->fiscalTotals($invoice, $input);
        $amount = $totals['total'];
        $billing = $this->billing($invoice, $input, $mode);

        $payload = [
            'idempotency_key' => $this->idempotencyKey($numVisible, $mode, $reason, $amount, $input),
            'series' => 'R',
            'type' => strtoupper($this->optionalString($input, ['type', 'tipus_factura'], 'R1')),
            'source_channel' => 'INTRANET',
            'created_by' => $this->optionalString($input, ['created_by', 'user', 'usuari']),
            'billing' => $billing,
            'totals' => $totals,
            'lines' => [[
                'concept' => $this->optionalString($input, ['concept', 'concepte'], 'Rectificacio factura ' . $numVisible),
                'detail' => $this->optionalString($input, ['detail', 'details', 'detall'], $reason . ' / ' . $mode),
                'quantity' => '1.00',
                'unit_price' => $totals['import_base'],
                'base' => $totals['import_base'],
                'import_base' => $totals['import_base'],
                'discount_amount' => '0.00',
                'taxable_base' => $totals['taxable_base'],
                'iva_regim' => $totals['iva_regim'],
                'iva_pct' => $totals['iva_pct'],
                'iva_import' => $totals['iva_import'],
                'exemption_reason' => $totals['exemption_reason'] ?? null,
                'total' => $totals['total'],
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

        foreach (['aeat_header', 'aeat_fields'] as $serverField) {
            if (!array_key_exists($serverField, $input)) {
                continue;
            }
            if (!is_array($input[$serverField])) {
                throw SifException::validation('Invalid server-side AEAT rectification block');
            }
            $payload[$serverField] = $input[$serverField];
        }

        return $payload;
    }

    private function billing(array $invoice, array $input, string $mode): array
    {
        $original = [
            'name' => $this->requiredString($invoice, ['BILLING_NOM_RAO'], 'billing name'),
            'nif' => $this->requiredString($invoice, ['BILLING_NIF_CIF'], 'billing NIF'),
            'address' => $invoice['BILLING_ADRECA'] ?? null,
            'cp' => $invoice['BILLING_CP'] ?? null,
            'city' => $invoice['BILLING_POBLACIO'] ?? null,
            'province' => $invoice['BILLING_PROVINCIA'] ?? null,
            'country' => $invoice['BILLING_PAIS'] ?? 'ES',
            'email' => $invoice['BILLING_EMAIL'] ?? null,
        ];

        $correction = $input['billing'] ?? null;
        if ($correction === null) {
            return $original;
        }
        if (!is_array($correction)) {
            throw SifException::validation('Invalid rectification billing block');
        }
        if ($mode !== 'SUBSTITUCIO') {
            throw SifException::validation('Billing data can only be corrected in SUBSTITUCIO mode');
        }

        $aliases = [
            'name' => ['name', 'nom_rao', 'billing_name'],
            'nif' => ['nif', 'nif_cif', 'billing_nif'],
            'address' => ['address', 'adreca'],
            'cp' => ['cp', 'postal_code'],
            'city' => ['city', 'poblacio'],
            'province' => ['province', 'provincia'],
            'country' => ['country', 'pais'],
            'email' => ['email', 'correu'],
        ];

        $result = $original;
        foreach ($aliases as $target => $keys) {
            $value = $this->optional($correction, $keys);
            if ($value === null) {
                continue;
            }

            $result[$target] = trim((string) $value);
        }

        if ($result['name'] === '' || $result['nif'] === '') {
            throw SifException::validation('Corrected billing requires name and NIF');
        }

        return $result;
    }

    private function fiscalTotals(array $invoice, array $input): array
    {
        $explicit = $input['fiscal'] ?? null;
        if ($explicit !== null) {
            if (!is_array($explicit)) {
                throw SifException::validation('Invalid rectification fiscal block');
            }

            $totals = [
                'import_base' => $this->money($this->required($explicit, ['import_base'], 'rectification fiscal import_base')),
                'discount' => '0.00',
                'taxable_base' => $this->money($this->required($explicit, ['taxable_base'], 'rectification fiscal taxable_base')),
                'iva_regim' => strtoupper($this->requiredString($explicit, ['iva_regim'], 'rectification IVA regime')),
                'iva_pct' => $this->money($this->required($explicit, ['iva_pct'], 'rectification IVA percentage')),
                'iva_import' => $this->money($this->required($explicit, ['iva_import'], 'rectification IVA amount')),
                'total' => $this->money($this->required($explicit, ['total'], 'rectification fiscal total')),
            ];

            $exemptionReason = $this->optionalString($explicit, ['exemption_reason', 'causa_exempcio_no_subjecta']);
            if ($exemptionReason !== null) {
                $totals['exemption_reason'] = strtoupper($exemptionReason);
            }

            $this->assertFiscalConsistency($totals);
            if (abs((float) $totals['total']) < 0.005) {
                throw SifException::validation('Invalid rectification amount');
            }

            $requestedAmount = $this->optional($input, ['amount', 'import']);
            if ($requestedAmount !== null && $this->money($requestedAmount) !== $totals['total']) {
                throw SifException::validation('Rectification amount does not match fiscal total');
            }

            return $totals;
        }

        $amount = $this->amount($this->required($input, ['amount', 'import'], 'rectification amount'));
        $ivaRegim = strtoupper(trim((string) ($invoice['IVA_REGIM'] ?? 'EXEMPT')));
        $ivaPct = $this->money($invoice['IVA_PCT'] ?? '0.00');
        $ivaImport = $this->money($invoice['IVA_IMPORT'] ?? '0.00');

        if ($ivaRegim !== 'EXEMPT' || $ivaPct !== '0.00' || $ivaImport !== '0.00') {
            throw SifException::validation(
                'Taxed rectification requires an explicit fiscal block; amount alone is ambiguous'
            );
        }

        $totals = [
            'import_base' => $amount,
            'discount' => '0.00',
            'taxable_base' => $amount,
            'iva_regim' => $ivaRegim,
            'iva_pct' => $ivaPct,
            'iva_import' => '0.00',
            'total' => $amount,
        ];

        $exemptionReason = $this->optionalString(
            $invoice,
            ['CAUSA_EXEMPCIO_NO_SUBJECTA']
        );
        if ($exemptionReason !== null) {
            $totals['exemption_reason'] = strtoupper($exemptionReason);
        }

        return $totals;
    }

    private function assertFiscalConsistency(array $totals): void
    {
        $base = $this->toCents($totals['taxable_base']);
        $tax = $this->toCents($totals['iva_import']);
        $total = $this->toCents($totals['total']);

        if (abs(($base + $tax) - $total) > 1) {
            throw SifException::validation('Rectification fiscal total is inconsistent with taxable base and IVA');
        }

        if ($totals['iva_regim'] === 'EXEMPT') {
            if ($totals['iva_pct'] !== '0.00' || $totals['iva_import'] !== '0.00') {
                throw SifException::validation('EXEMPT rectification cannot carry IVA percentage or amount');
            }
        }
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
        $amount = $this->money($value);
        if (abs((float) $amount) < 0.005) {
            throw SifException::validation('Invalid rectification amount');
        }

        return $amount;
    }

    private function money(mixed $value): string
    {
        if (!is_numeric($value)) {
            throw SifException::validation('Invalid rectification monetary value');
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function toCents(string $value): int
    {
        return (int) round(((float) $value) * 100);
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
