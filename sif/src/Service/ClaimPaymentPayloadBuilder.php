<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class ClaimPaymentPayloadBuilder
{
    public function forExistingInvoice(string $uuidFactura, array $input): array
    {
        $uuidFactura = trim($uuidFactura);
        if ($uuidFactura === '') {
            throw SifException::validation('Missing claim payment invoice UUID');
        }

        $amount = $this->amount($this->required($input, ['amount', 'import', 'pagament'], 'payment amount'));
        $movementDate = $this->requiredString($input, ['movement_date', 'data_pag', 'dataPag'], 'movement_date');
        $method = $this->method($this->optionalString($input, ['method'], 'TRANSFERENCIA'));
        $reference = $this->claimReference($input);
        $bank = $this->optionalString($input, ['bank', 'banc']);
        $createdBy = $this->optionalString($input, ['created_by', 'user', 'usuari']);

        $payload = [
            'idempotency_key' => $this->idempotencyKey($uuidFactura, $input, $amount, $movementDate, $reference, $createdBy),
            'movement_type' => 'CHARGE',
            'method' => $method,
            'source_channel' => 'INTRANET',
            'amount' => $amount,
            'movement_date' => $movementDate,
            'allocations' => [[
                'uuid_factura' => $uuidFactura,
                'amount' => $amount,
                'allocation_type' => 'CLAIM_PAYMENT',
            ]],
        ];

        foreach ([
            'reference' => $reference,
            'bank' => $bank,
            'created_by' => $createdBy,
            'notes' => $this->optionalString($input, ['notes', 'obs', 'observations']),
        ] as $key => $value) {
            if ($value !== null) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    private function claimReference(array $input): ?string
    {
        return $this->optionalString($input, [
            'claim_reference',
            'reclamation_ref',
            'reclamacio_ref',
            'reference',
            'referencia',
            'referencia_bancaria',
        ]);
    }

    private function idempotencyKey(
        string $uuidFactura,
        array $input,
        string $amount,
        string $movementDate,
        ?string $reference,
        ?string $createdBy
    ): string {
        if ($reference !== null) {
            return 'CLAIM|REF:' . $this->keyPart($reference);
        }

        if ($createdBy === null) {
            throw SifException::validation('Claim payment without reference requires created_by');
        }

        $invoiceRef = $this->optionalString($input, ['num_visible', 'invoice_ref', 'num_fact', 'numFact'], $uuidFactura);
        $date = substr($movementDate, 0, 10);

        return 'CLAIM'
            . '|FACT:' . $this->keyPart((string) $invoiceRef)
            . '|DATA:' . $this->keyPart($date)
            . '|IMPORT:' . $amount
            . '|USUARI:' . $this->keyPart($createdBy);
    }

    private function method(?string $value): string
    {
        $method = strtoupper(trim((string) $value));
        if (!in_array($method, ['TRANSFERENCIA', 'MANUAL'], true)) {
            throw SifException::validation('Invalid claim payment method');
        }

        return $method;
    }

    private function amount(mixed $value): string
    {
        if (!is_numeric($value)) {
            throw SifException::validation('Invalid payment amount');
        }

        $amount = (float) $value;
        if ($amount <= 0.0) {
            throw SifException::validation('Invalid payment amount');
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
