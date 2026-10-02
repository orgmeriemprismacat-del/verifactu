<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class InvoiceBeforePaymentPayloadBuilder
{
    public function build(array $input): array
    {
        if (array_key_exists('payment', $input) && $input['payment'] !== null) {
            throw SifException::validation('Invoice before payment must not include initial payment block');
        }

        $payload = $input;
        $payload['idempotency_key'] = $this->idempotencyKey($input);
        $payload['source_channel'] = 'INTRANET';
        $payload['created_by'] = $this->optionalString(
            $input,
            ['created_by', 'user', 'usuari'],
            'intranet-factura-abans-cobrar'
        );
        $payload['emesa_abans_cobrament'] = 1;
        $payload['uc004_invoice_before_payment'] = 1;
        $payload['relations'] = $this->originRelations($input);
        $payload = $this->applyUc004ExemptionClassification($payload);
        unset($payload['payment']);

        return $payload;
    }

    private function applyUc004ExemptionClassification(array $payload): array
    {
        if (isset($payload['totals']) && is_array($payload['totals'])) {
            $payload['totals'] = $this->applyUc004ExemptionToBlock(
                $payload['totals'],
                'totals'
            );
        }

        foreach ($payload['lines'] ?? [] as $index => $line) {
            if (!is_array($line)) {
                continue;
            }

            $payload['lines'][$index] = $this->applyUc004ExemptionToBlock(
                $line,
                'line ' . $index
            );
        }

        return $payload;
    }

    private function applyUc004ExemptionToBlock(array $block, string $label): array
    {
        if (strtoupper(trim((string) ($block['iva_regim'] ?? ''))) !== 'EXEMPT') {
            return $block;
        }

        $reason = strtoupper(trim((string) ($block['exemption_reason'] ?? '')));
        if ($reason === '') {
            $block['exemption_reason'] = 'E1';

            return $block;
        }

        if ($reason !== 'E1') {
            throw SifException::validation(
                'Invoice before payment exempt ' . $label
                . ' requires exemption_reason E1 for the current training-course flow'
            );
        }

        $block['exemption_reason'] = 'E1';

        return $block;
    }

    private function originRelations(array $input): array
    {
        if (!isset($input['relations']) || !is_array($input['relations']) || $input['relations'] === []) {
            throw SifException::validation(
                'Invoice before payment requires at least one INSCRIPCIO origin relation'
            );
        }

        $relations = [];
        $seenSourceIds = [];

        foreach ($input['relations'] as $index => $relation) {
            if (!is_array($relation)) {
                throw SifException::validation("Invalid invoice before payment relation {$index}");
            }

            $sourceType = strtoupper(trim((string) ($relation['source_type'] ?? '')));
            if ($sourceType !== 'INSCRIPCIO') {
                throw SifException::validation(
                    'Invoice before payment origin relations must use source_type INSCRIPCIO'
                );
            }

            $sourceId = $relation['source_id'] ?? null;
            if (is_string($sourceId) && ctype_digit($sourceId)) {
                $sourceId = (int) $sourceId;
            }

            if (!is_int($sourceId) || $sourceId <= 0) {
                throw SifException::validation(
                    'Invoice before payment origin relations require a positive source_id'
                );
            }

            if (isset($seenSourceIds[$sourceId])) {
                throw SifException::validation(
                    "Duplicate invoice before payment INSCRIPCIO source_id {$sourceId}"
                );
            }
            $seenSourceIds[$sourceId] = true;

            $relation['source_type'] = 'INSCRIPCIO';
            $relation['source_id'] = $sourceId;
            $relation['relation_type'] = 'ORIGIN';
            $relations[] = $relation;
        }

        return $relations;
    }

    private function idempotencyKey(array $input): string
    {
        $explicit = $this->optionalString($input, ['idempotency_key']);
        if ($explicit !== null) {
            return $explicit;
        }

        $reference = $this->optionalString($input, [
            'reference',
            'referencia',
            'invoice_ref',
            'external_ref',
            'factura_relacionada',
        ]);

        if ($reference === null) {
            throw SifException::validation('Missing invoice before payment idempotency key or reference');
        }

        return 'INTRANET|FACTURA_ABANS_COBRAR|REF:' . $this->keyPart($reference);
    }

    private function optionalString(array $data, array $keys, ?string $default = null): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $string = trim((string) $data[$key]);

            return $string === '' ? $default : $string;
        }

        return $default;
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
