<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class ManualInvoicePayloadBuilder
{
    public function build(array $input): array
    {
        $createdBy = $this->requiredInternalUser($input);
        $idempotencyKey = $this->idempotencyKey($input, $createdBy);

        $payload = $input;
        $payload['idempotency_key'] = $idempotencyKey;
        $payload['series'] = $this->optionalString($input, ['series', 'tipus_serie'], 'A');
        $payload['type'] = $this->optionalString($input, ['type', 'tipus_factura'], 'F1');
        $payload['source_channel'] = 'INTRANET';
        $payload['source_type'] = 'MANUAL';
        $payload['created_by'] = $createdBy;

        if (array_key_exists('payment', $payload) && $payload['payment'] !== null) {
            if (!is_array($payload['payment'])) {
                throw SifException::validation('Invalid manual invoice payment block');
            }

            $payload['payment'] = $this->paymentBlock($payload['payment'], $payload, $idempotencyKey);
        }

        return $payload;
    }

    private function paymentBlock(array $payment, array $payload, string $invoiceKey): array
    {
        $method = $this->paymentMethod($this->optionalString($payment, ['method'], 'MANUAL'));
        $amount = $this->amount(
            $this->optional($payment, ['amount', 'import'])
                ?? ($payload['totals']['total'] ?? null)
        );
        $movementDate = $this->requiredString(
            $payment,
            ['movement_date', 'data_pag', 'dataPag'],
            'manual invoice payment movement_date'
        );
        $reference = $this->optionalString($payment, [
            'reference',
            'referencia',
            'referencia_bancaria',
            'provider_ref',
        ]);

        return array_replace($payment, [
            'idempotency_key' => $this->optionalString($payment, ['idempotency_key'], 'PAYMENT|' . $invoiceKey),
            'movement_type' => $this->optionalString($payment, ['movement_type'], 'CHARGE'),
            'method' => $method,
            'source_channel' => 'INTRANET',
            'amount' => $amount,
            'movement_date' => $movementDate,
            'provider_ref' => $this->optionalString($payment, ['provider_ref'], $reference ?? $invoiceKey),
            'reference' => $reference,
            'allocation_type' => $this->optionalString($payment, ['allocation_type'], 'INVOICE_PAYMENT'),
        ]);
    }

    private function idempotencyKey(array $input, string $createdBy): string
    {
        $explicit = $this->optionalString($input, ['idempotency_key']);
        if ($explicit !== null) {
            return $explicit;
        }

        $reference = $this->optionalString($input, [
            'reference',
            'referencia',
            'manual_ref',
            'external_ref',
            'invoice_ref',
        ]);

        if ($reference !== null) {
            return 'INTRANET|MANUAL|REF:' . $this->keyPart($reference);
        }

        $date = substr($this->optionalString($input, ['issue_date', 'data', 'date', 'created_at'], date('Y-m-d')), 0, 10);

        return 'INTRANET|MANUAL|USUARI:' . $this->keyPart($createdBy)
            . '|DATA:' . $this->keyPart($date)
            . '|HASH:' . $this->payloadHash($input);
    }

    private function payloadHash(array $input): string
    {
        $hashInput = [
            'billing' => $input['billing'] ?? [],
            'totals' => $input['totals'] ?? [],
            'lines' => $input['lines'] ?? [],
            'relations' => $input['relations'] ?? [],
        ];
        $this->sortRecursive($hashInput);

        $json = json_encode($hashInput, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false) {
            throw new \RuntimeException('Could not encode manual invoice idempotency payload.');
        }

        return substr(hash('sha256', $json), 0, 16);
    }

    private function sortRecursive(array &$value): void
    {
        if ($this->isList($value)) {
            foreach ($value as &$child) {
                if (is_array($child)) {
                    $this->sortRecursive($child);
                }
            }

            return;
        }

        ksort($value);
        foreach ($value as &$child) {
            if (is_array($child)) {
                $this->sortRecursive($child);
            }
        }
    }

    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    private function requiredInternalUser(array $input): string
    {
        return $this->requiredString($input, ['created_by', 'user', 'usuari'], 'manual invoice internal user');
    }

    private function paymentMethod(?string $value): string
    {
        $method = strtoupper(trim((string) $value));
        if (!in_array($method, ['TRANSFERENCIA', 'MANUAL'], true)) {
            throw SifException::validation('Invalid manual invoice payment method');
        }

        return $method;
    }

    private function amount(mixed $value): string
    {
        if (!is_numeric($value)) {
            throw SifException::validation('Invalid manual invoice payment amount');
        }

        $amount = (float) $value;
        if ($amount <= 0.0) {
            throw SifException::validation('Invalid manual invoice payment amount');
        }

        return number_format($amount, 2, '.', '');
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

    private function optional(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            if ((string) $data[$key] === '') {
                continue;
            }

            return $data[$key];
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
