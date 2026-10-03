<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class HistoricalInvoicePayloadBuilder
{
    public function build(array $input): array
    {
        $numVisible = $this->requiredString($input, ['num_visible', 'num_factura'], 'historical invoice visible number');
        $parsed = $this->parseVisibleNumber($numVisible);

        $payload = $input;
        $payload['num_visible'] = $numVisible;
        $payload['series'] = strtoupper($this->optionalString($input, ['series', 'tipus_serie'], $parsed['series']));
        $payload['year'] = (int) ($this->optional($input, ['year', 'any_fact']) ?? $parsed['year']);
        $payload['num_seq'] = (int) ($this->optional($input, ['num_seq', 'numero', 'num']) ?? $parsed['num_seq']);
        $this->assertVisibleNumberConsistency($payload, $parsed);
        $payload['type'] = strtoupper($this->optionalString($input, ['type', 'tipus_factura'], 'F1'));
        $this->assertInvoiceType($payload['type']);
        $payload['idempotency_key'] = $this->idempotencyKey($input, $numVisible);
        $payload['source_channel'] = 'MIGRACIO';
        $payload['source_type'] = 'HISTORIC_WEB_FACTURES';
        $payload['created_by'] = $this->optionalString($input, ['created_by', 'user', 'usuari'], 'historic-migration');
        $payload['invoice_status'] = 'HISTORICAL';
        $payload['aeat_status'] = 'NO_VERIFACTU';
        $payload['payment_status'] = $this->optionalString($input, ['payment_status', 'estat_cobrament'], 'UNKNOWN');
        $payload['issue_date'] = $this->requiredString($input, ['issue_date', 'data_emissio'], 'historical invoice issue date');
        $this->assertDate($payload['issue_date'], 'historical invoice issue date');
        $payload['operation_date'] = $this->optionalString($input, ['operation_date', 'data_operacio']);
        if ($payload['operation_date'] !== null) {
            $this->assertDate($payload['operation_date'], 'historical invoice operation date');
        }
        $payload['payment_date'] = $this->optionalString($input, ['payment_date', 'data_pagament']);
        if ($payload['payment_date'] !== null) {
            $this->assertDate($payload['payment_date'], 'historical invoice payment date');
        }
        $payload['issuer'] = $this->issuer($input);
        $payload['operation_description'] = $this->optionalString(
            $input,
            ['operation_description', 'descripcio_operacio']
        );
        $payload['billing'] = $this->billing($input);
        $payload['totals'] = $this->totals($input);
        $payload['lines'] = $this->lines($input);
        $payload['relations'] = $this->relations($input);

        if (array_key_exists('document', $input) && $input['document'] !== null) {
            if (!is_array($input['document'])) {
                throw SifException::validation('Invalid historical invoice document');
            }

            $payload['document'] = $this->document($input['document']);
        }

        return $payload;
    }

    private function parseVisibleNumber(string $numVisible): array
    {
        if (preg_match('/^([A-Z])(\d{4})\/0*(\d+)$/', strtoupper($numVisible), $matches) !== 1) {
            throw SifException::validation('Invalid historical invoice visible number');
        }

        return [
            'series' => $matches[1],
            'year' => (int) $matches[2],
            'num_seq' => (int) $matches[3],
        ];
    }

    private function assertVisibleNumberConsistency(array $payload, array $parsed): void
    {
        if ($payload['series'] !== $parsed['series']
            || $payload['year'] !== $parsed['year']
            || $payload['num_seq'] !== $parsed['num_seq']
        ) {
            throw SifException::validation('Historical invoice number components do not match num_visible');
        }
    }

    private function idempotencyKey(array $input, string $numVisible): string
    {
        $explicit = $this->optionalString($input, ['idempotency_key']);
        if ($explicit !== null) {
            return $explicit;
        }

        return 'HISTORIC|FACT:' . $this->keyPart($numVisible);
    }

    private function lines(array $input): array
    {
        $lines = $this->requiredArray($input, ['lines'], 'historical invoice lines');
        if ($lines === []) {
            throw SifException::validation('Historical invoice requires at least one line');
        }

        foreach ($lines as $index => $line) {
            if (!is_array($line)) {
                throw SifException::validation("Invalid historical invoice line {$index}");
            }

            $this->requiredString($line, ['concept'], "historical invoice line {$index} concept");
            foreach (['quantity', 'unit_price', 'total'] as $field) {
                $this->assertNumeric($line[$field] ?? null, "historical invoice line {$index} {$field}");
            }

            $base = $line['import_base'] ?? $line['base'] ?? null;
            $taxableBase = $line['taxable_base'] ?? $line['base'] ?? null;
            $this->assertNumeric($base, "historical invoice line {$index} import base");
            $this->assertNumeric($taxableBase, "historical invoice line {$index} taxable base");
            $lines[$index] = $this->normalizeFiscalBlock($line);
        }

        return $lines;
    }

    private function billing(array $input): array
    {
        $billing = $this->requiredArray($input, ['billing'], 'historical invoice billing');
        $this->requiredString($billing, ['name'], 'historical invoice billing name');
        $this->requiredString($billing, ['nif'], 'historical invoice billing nif');

        return $billing;
    }

    private function totals(array $input): array
    {
        $totals = $this->requiredArray($input, ['totals'], 'historical invoice totals');
        foreach (['import_base', 'taxable_base', 'total'] as $field) {
            $this->assertNumeric($totals[$field] ?? null, "historical invoice total {$field}");
        }

        foreach (['discount', 'iva_pct', 'iva_import'] as $field) {
            if (array_key_exists($field, $totals) && $totals[$field] !== null && $totals[$field] !== '') {
                $this->assertNumeric($totals[$field], "historical invoice total {$field}");
            }
        }

        return $this->normalizeFiscalBlock($totals);
    }

    private function issuer(array $input): ?array
    {
        $issuer = $this->optional($input, ['issuer']);
        if ($issuer !== null) {
            if (!is_array($issuer)) {
                throw SifException::validation('Invalid historical invoice issuer');
            }

            return [
                'nif' => $this->requiredString($issuer, ['nif'], 'historical invoice issuer nif'),
                'name' => $this->optionalString($issuer, ['name']),
            ];
        }

        $nif = $this->optionalString($input, ['issuer_nif', 'emissor_nif']);
        $name = $this->optionalString($input, ['issuer_name', 'emissor_nom']);
        if ($nif === null && $name === null) {
            return null;
        }
        if ($nif === null) {
            throw SifException::validation('Missing historical invoice issuer nif');
        }

        return ['nif' => $nif, 'name' => $name];
    }

    private function normalizeFiscalBlock(array $block): array
    {
        $inversion = $this->optional($block, ['inversion_subjecte_passiu', 'inversio_subjecte_passiu']);
        if ($inversion !== null && !in_array($inversion, [0, 1, '0', '1', false, true], true)) {
            throw SifException::validation('Invalid historical reverse-charge flag');
        }
        $block['inversion_subjecte_passiu'] = $inversion === null ? 0 : ((int) (bool) $inversion);

        $block['exemption_reason'] = $this->optionalString(
            $block,
            ['exemption_reason', 'causa_exempcio_no_subjecta']
        );
        if ($block['exemption_reason'] !== null) {
            $block['exemption_reason'] = strtoupper($block['exemption_reason']);
            if (!in_array($block['exemption_reason'], ['E1', 'E2', 'E3', 'E4', 'E5', 'E6', 'E7', 'E8'], true)) {
                throw SifException::validation('Invalid historical invoice exemption reason');
            }
            if (strtoupper(trim((string) ($block['iva_regim'] ?? ''))) !== 'EXEMPT') {
                throw SifException::validation('Historical exemption reason requires EXEMPT IVA regime');
            }
        }

        foreach ([
            'rec_equivalence_pct' => ['rec_equivalence_pct', 'recarrec_equivalencia_pct'],
            'rec_equivalence_import' => ['rec_equivalence_import', 'recarrec_equivalencia_import'],
        ] as $canonical => $keys) {
            $value = $this->optional($block, $keys);
            if ($value !== null && $value !== '') {
                $this->assertNumeric($value, "historical invoice {$canonical}");
                $block[$canonical] = $value;
            } else {
                $block[$canonical] = null;
            }
        }

        return $block;
    }

    private function assertNumeric(mixed $value, string $label): void
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            throw SifException::validation("Invalid {$label}");
        }
    }

    private function assertInvoiceType(string $type): void
    {
        if (!in_array(strtoupper($type), ['F1', 'F2', 'R1', 'R2', 'R3', 'R4', 'R5'], true)) {
            throw SifException::validation('Invalid historical invoice type');
        }
    }

    private function assertDate(string $value, string $label): void
    {
        foreach (['Y-m-d H:i:s', 'Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date !== false
                && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
                && $date->format($format) === $value
            ) {
                return;
            }
        }

        throw SifException::validation("Invalid {$label}");
    }

    private function relations(array $input): array
    {
        $relations = $this->optional($input, ['relations']);
        if (is_array($relations) && $relations !== []) {
            foreach ($relations as $index => $relation) {
                if (!is_array($relation)) {
                    throw SifException::validation("Invalid historical invoice relation {$index}");
                }

                $sourceType = $this->requiredString(
                    $relation,
                    ['source_type'],
                    "historical invoice relation {$index} source type"
                );
                $visible = $this->optionalInt($relation, ['visible_alumne']) ?? 0;
                if (!in_array($visible, [0, 1], true)) {
                    throw SifException::validation("Invalid historical invoice relation {$index} visibility");
                }

                $relations[$index]['source_type'] = $sourceType;
                $relations[$index]['source_id'] = $this->optionalInt($relation, ['source_id']);
                $relations[$index]['factura_relacionada'] = $this->optionalInt($relation, ['factura_relacionada']);
                $relations[$index]['idpag'] = $this->optionalInt($relation, ['idpag']);
                $relations[$index]['visible_alumne'] = $visible;
            }

            return $relations;
        }
        if ($relations !== null && !is_array($relations)) {
            throw SifException::validation('Invalid historical invoice relations');
        }

        return [[
            'source_type' => 'HISTORIC_WEB_FACTURES',
            'source_id' => $this->optionalInt($input, ['legacy_id', 'source_id', 'id_factura_web']),
            'relation_type' => 'HISTORIC_LINK',
            'factura_relacionada' => $this->optionalInt($input, ['factura_relacionada']),
            'visible_alumne' => $this->optionalInt($input, ['visible_alumne']) ?? 0,
        ]];
    }

    private function document(array $document): array
    {
        $type = strtoupper($this->requiredString($document, ['type', 'tipus'], 'historical document type'));
        $path = $this->requiredString($document, ['path', 'path_fitxer'], 'historical document path');
        $hash = strtolower($this->requiredString($document, ['hash', 'hash_fitxer'], 'historical document hash'));

        if (!in_array($type, ['PDF', 'XML', 'QR'], true)) {
            throw SifException::validation('Invalid historical document type');
        }

        if (strlen($path) > 255 || preg_match('/^[0-9a-f]{64}$/', $hash) !== 1) {
            throw SifException::validation('Invalid historical document metadata');
        }

        return [
            'type' => $type,
            'path' => $path,
            'hash' => $hash,
            'status' => $this->optionalString($document, ['status', 'estat'], 'ARCHIVED'),
        ];
    }

    private function requiredArray(array $data, array $keys, string $label): array
    {
        $value = $this->optional($data, $keys);
        if (!is_array($value)) {
            throw SifException::validation("Missing {$label}");
        }

        return $value;
    }

    private function requiredString(array $data, array $keys, string $label): string
    {
        $value = $this->optionalString($data, $keys);
        if ($value === null) {
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

    private function optionalInt(array $data, array $keys): ?int
    {
        $value = $this->optional($data, $keys);
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw SifException::validation('Invalid historical relation value');
        }

        return (int) $value;
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
