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
        $payload['type'] = $this->optionalString($input, ['type', 'tipus_factura'], 'F1');
        $payload['idempotency_key'] = $this->idempotencyKey($input, $numVisible);
        $payload['source_channel'] = 'MIGRACIO';
        $payload['source_type'] = 'HISTORIC_WEB_FACTURES';
        $payload['created_by'] = $this->optionalString($input, ['created_by', 'user', 'usuari'], 'historic-migration');
        $payload['invoice_status'] = $this->optionalString($input, ['invoice_status', 'estat_factura'], 'HISTORICAL');
        $payload['aeat_status'] = 'NO_VERIFACTU';
        $payload['payment_status'] = $this->optionalString($input, ['payment_status', 'estat_cobrament'], 'UNKNOWN');
        $payload['issue_date'] = $this->optionalString($input, ['issue_date', 'data_emissio'], date('Y-m-d H:i:s'));
        $payload['operation_date'] = $this->optionalString($input, ['operation_date', 'data_operacio']);
        $payload['payment_date'] = $this->optionalString($input, ['payment_date', 'data_pagament']);
        $payload['billing'] = $this->requiredArray($input, ['billing'], 'historical invoice billing');
        $payload['totals'] = $this->requiredArray($input, ['totals'], 'historical invoice totals');
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

        return $lines;
    }

    private function relations(array $input): array
    {
        $relations = $this->optional($input, ['relations']);
        if (is_array($relations) && $relations !== []) {
            return $relations;
        }

        return [[
            'source_type' => 'HISTORIC_WEB_FACTURES',
            'source_id' => $this->optionalInt($input, ['legacy_id', 'source_id', 'id_factura_web']),
            'relation_type' => 'HISTORIC_LINK',
            'factura_relacionada' => $this->optionalInt($input, ['factura_relacionada']),
            'visible_alumne' => $this->optionalInt($input, ['visible_alumne']) ?? 1,
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
