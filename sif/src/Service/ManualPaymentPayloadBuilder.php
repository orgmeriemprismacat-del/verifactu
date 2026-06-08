<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class ManualPaymentPayloadBuilder
{
    public function forExistingInvoice(string $uuidFactura, array $input): array
    {
        $uuidFactura = trim($uuidFactura);
        if ($uuidFactura === '') {
            throw SifException::validation('Missing payment invoice UUID');
        }

        $amount = $this->amount($this->required($input, ['amount', 'import', 'pagament'], 'payment amount'));
        $movementDate = $this->requiredString($input, ['movement_date', 'data_pag', 'dataPag'], 'movement_date');
        $method = $this->method($this->optionalString($input, ['method'], 'TRANSFERENCIA'));
        $reference = $this->optionalString($input, ['reference', 'referencia', 'referencia_bancaria']);
        $bank = $this->optionalString($input, ['bank', 'banc']);

        $payload = [
            'idempotency_key' => $this->idempotencyKey($method, $uuidFactura, $input, $amount, $movementDate, $reference, $bank),
            'movement_type' => 'CHARGE',
            'method' => $method,
            'source_channel' => 'INTRANET',
            'amount' => $amount,
            'movement_date' => $movementDate,
            'allocations' => [[
                'uuid_factura' => $uuidFactura,
                'amount' => $amount,
                'allocation_type' => $this->optionalString($input, ['allocation_type'], 'INVOICE_PAYMENT'),
            ]],
        ];

        foreach (['reference' => $reference, 'bank' => $bank, 'notes' => $this->optionalString($input, ['notes', 'obs', 'observations'])] as $key => $value) {
            if ($value !== null) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    private function idempotencyKey(
        string $method,
        string $uuidFactura,
        array $input,
        string $amount,
        string $movementDate,
        ?string $reference,
        ?string $bank
    ): string {
        if ($reference !== null) {
            return $method . '|REF:' . $this->keyPart($reference);
        }

        $invoiceRef = $this->optionalString($input, ['num_visible', 'invoice_ref', 'num_fact', 'numFact'], $uuidFactura);
        $date = substr($movementDate, 0, 10);

        return $method
            . '|FACT:' . $this->keyPart((string) $invoiceRef)
            . '|DATA:' . $this->keyPart($date)
            . '|IMPORT:' . $amount
            . '|BANC:' . $this->keyPart($bank ?? 'NULL');
    }

    private function method(?string $value): string
    {
        $method = strtoupper(trim((string) $value));
        if (!in_array($method, ['TRANSFERENCIA', 'MANUAL'], true)) {
            throw SifException::validation('Invalid manual payment method');
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
