<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class FiscalRecordPayloadBuilder
{
    public function cancellation(array $invoice, array $previousRecord, array $input): array
    {
        $payload = $this->basePayload('ANULACIO', 'RegistroAnulacion', $invoice, $previousRecord, $input);
        $payload['cancellation_mode'] = $input['cancellation_mode'] ?? 'NORMAL';
        if (!in_array($payload['cancellation_mode'], ['NORMAL', 'RECHAZO_PREVIO', 'SIN_REGISTRO_PREVIO'], true)) {
            throw SifException::validation('Invalid cancellation mode');
        }
        return $payload;
    }

    public function subsanation(array $invoice, array $previousRecord, array $input): array
    {
        $payload = $this->basePayload('SUBSANACIO', 'RegistroAlta', $invoice, $previousRecord, $input);
        $payload['subsanation_kind'] = $this->subsanationKind(
            $this->requiredString($input, ['subsanation_kind', 'tipus_subsanacio'], 'subsanation kind')
        );
        $payload['correction_summary'] = $this->optionalString(
            $input,
            ['correction_summary', 'resum_correccio']
        );
        if (isset($payload['aeat_original'])) {
            if (!is_array($input['corrected_fields'] ?? null)) {
                throw SifException::validation('Official subsanation requires complete corrected_fields');
            }
            $payload['corrected_fields'] = $input['corrected_fields'];
        }

        return $payload;
    }

    public function idempotencyKey(string $recordType, array $invoice, array $payload, array $input): string
    {
        $reference = $this->optionalString($input, ['reference', 'referencia']);
        if ($reference !== null) {
            return $recordType . '|REF:' . $this->keyPart($reference);
        }

        $key = $recordType
            . '|FACT:' . $this->keyPart((string) $invoice['NUM_VISIBLE'])
            . '|MOTIU:' . $this->keyPart((string) $payload['reason']);

        if (isset($payload['subsanation_kind'])) {
            $key .= '|TIPUS:' . $this->keyPart((string) $payload['subsanation_kind']);
        }

        return $key;
    }

    private function basePayload(
        string $recordType,
        string $aeatRecordType,
        array $invoice,
        array $previousRecord,
        array $input
    ): array {
        $payload = [
            'record_type' => $recordType,
            'aeat_record_type' => $aeatRecordType,
            'uuid_factura' => $invoice['UUID_FACTURA'],
            'num_visible' => $invoice['NUM_VISIBLE'],
            'original_invoice_state' => $invoice['ESTAT_FACTURA'],
            'original_aeat_state' => $invoice['ESTAT_AEAT'],
            'previous_record' => [
                'id' => (int) $previousRecord['ID'],
                'fiscal_order' => (int) $previousRecord['FISCAL_ORDER'],
                'tipus_registre' => $previousRecord['TIPUS_REGISTRE'],
                'hash' => $previousRecord['HASH_FACT'],
                'estat_aeat' => $previousRecord['ESTAT_AEAT'],
            ],
            'reason' => strtoupper($this->requiredString($input, ['reason', 'motiu'], 'fiscal record reason')),
            'detail' => $this->optionalString($input, ['detail', 'details', 'detall']),
            'created_by' => $this->optionalString($input, ['created_by', 'user', 'usuari']),
            'reference' => $this->optionalString($input, ['reference', 'referencia']),
        ];
        $previousPayload = json_decode((string) ($previousRecord['PAYLOAD_JSON'] ?? '{}'), true);
        if (isset($previousPayload['aeat'])) {
            $payload['aeat_original'] = $previousPayload['aeat'];
        }
        // Stable request fingerprint excludes mutable state and generated chain data.
        $payload['request_hash'] = (new PayloadIdempotencyValidator())->calculateHash([
            'invoice' => $invoice['UUID_FACTURA'], 'type' => $recordType, 'input' => $input,
        ]);
        return $payload;
    }

    private function subsanationKind(string $value): string
    {
        $kind = strtoupper(trim($value));
        $aliases = [
            'SUBSANACIO' => 'SUBSANACION',
            'SUBSANATION' => 'SUBSANACION',
        ];
        $kind = $aliases[$kind] ?? $kind;

        if (!in_array($kind, ['SUBSANACION', 'RECHAZO_PREVIO', 'SIN_REGISTRO_PREVIO', 'SUBSANACION_RECHAZADA'], true)) {
            throw SifException::validation('Invalid subsanation kind');
        }

        return $kind;
    }

    private function requiredString(array $data, array $keys, string $label): string
    {
        $value = $this->optionalString($data, $keys);
        if ($value === null) {
            throw SifException::validation("Missing {$label}");
        }

        return $value;
    }

    private function optionalString(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $value = trim((string) $data[$key]);

            return $value === '' ? null : $value;
        }

        return null;
    }

    private function keyPart(string $value): string
    {
        $part = preg_replace('/\s+/', '_', trim($value));

        if ($part !== null && strlen($part) > 48) {
            return substr($part, 0, 31) . '_' . substr(hash('sha256', $part), 0, 16);
        }

        return $part === null || $part === '' ? 'NULL' : $part;
    }
}
