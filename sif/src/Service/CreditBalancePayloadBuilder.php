<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class CreditBalancePayloadBuilder
{
    public function forCreditBalance(array $input): array
    {
        $payload = [
            'holder_type' => strtoupper($this->requiredString($input, ['holder_type', 'tipus_titular'], 'holder_type')),
            'holder_name' => $this->requiredString($input, ['holder_name', 'holder_nom', 'nom_titular'], 'holder_name'),
            'amount' => $this->amount($this->required($input, ['amount', 'import'], 'credit amount')),
            'source_type' => strtoupper($this->requiredString($input, ['source_type', 'origen'], 'source_type')),
        ];

        foreach ([
            'holder_id' => ['holder_id', 'id_titular'],
            'source_id' => ['source_id', 'id_origen'],
        ] as $key => $keys) {
            $value = $this->optional($input, $keys);
            if ($value !== null && $value !== '') {
                $payload[$key] = (int) $value;
            }
        }

        foreach ([
            'holder_nif_cif' => ['holder_nif_cif', 'nif_cif', 'nif'],
            'uuid_factura_origen' => ['uuid_factura_origen', 'invoice_origin_uuid'],
            'uuid_factura_rectificativa' => ['uuid_factura_rectificativa', 'rectification_invoice_uuid'],
            'review_after' => ['review_after', 'revisar_despres'],
        ] as $key => $keys) {
            $value = $this->optionalString($input, $keys);
            if ($value !== null) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    public function forCompensation(string $uuidCredit, string $uuidFactura, array $input, array $invoice): array
    {
        $uuidCredit = trim($uuidCredit);
        $uuidFactura = trim($uuidFactura);

        if ($uuidCredit === '') {
            throw SifException::validation('Missing credit UUID');
        }

        if ($uuidFactura === '') {
            throw SifException::validation('Missing compensation invoice UUID');
        }

        $amount = $this->amount($this->required($input, ['amount', 'import'], 'compensation amount'));
        $movementDate = $this->requiredString($input, ['movement_date', 'data_moviment', 'data'], 'movement_date');
        $invoiceRef = trim((string) ($invoice['NUM_VISIBLE'] ?? $uuidFactura));

        $payload = [
            'idempotency_key' => 'COMPENSACIO|UUID_CREDIT:' . $uuidCredit . '|FACT:' . $invoiceRef . '|IMPORT:' . $amount,
            'movement_type' => 'COMPENSATION',
            'method' => 'COMPENSACIO',
            'source_channel' => 'INTRANET',
            'amount' => $amount,
            'movement_date' => $movementDate,
            'provider_ref' => $uuidCredit,
            'allocations' => [[
                'uuid_factura' => $uuidFactura,
                'amount' => $amount,
                'allocation_type' => $this->optionalString($input, ['allocation_type'], 'CREDIT_COMPENSATION'),
            ]],
        ];

        $notes = $this->optionalString($input, ['notes', 'obs', 'observations']);
        if ($notes !== null) {
            $payload['notes'] = $notes;
        }

        return $payload;
    }

    private function amount(mixed $value): string
    {
        if (!is_numeric($value)) {
            throw SifException::validation('Invalid credit amount');
        }

        $amount = (float) $value;
        if ($amount <= 0.0) {
            throw SifException::validation('Invalid credit amount');
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
}
