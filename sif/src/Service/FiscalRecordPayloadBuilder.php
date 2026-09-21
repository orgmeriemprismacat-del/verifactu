<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class FiscalRecordPayloadBuilder
{
    public function cancellation(array $invoice, array $previousRecord, array $input): array
    {
        return $this->basePayload('ANULACIO', 'RegistroAnulacion', $invoice, $previousRecord, $input);
    }

    public function subsanation(array $invoice, array $previousRecord, array $input): array
    {
        $payload = $this->basePayload('SUBSANACIO', 'Subsanacion', $invoice, $previousRecord, $input);
        $payload['subsanation_kind'] = $this->subsanationKind(
            $this->requiredString($input, ['subsanation_kind', 'tipus_subsanacio'], 'subsanation kind')
        );
        $payload['correction_summary'] = $this->optionalString(
            $input,
            ['correction_summary', 'resum_correccio']
        );

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
        return [
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
    }

    private function subsanationKind(string $value): string
    {
        $kind = strtoupper(trim($value));
        $aliases = [
            'SUBSANACIO' => 'SUBSANACION',
            'SUBSANATION' => 'SUBSANACION',
        ];
        $kind = $aliases[$kind] ?? $kind;

        if (!in_array($kind, ['SUBSANACION', 'RECHAZO_PREVIO', 'SIN_REGISTRO_PREVIO'], true)) {
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
