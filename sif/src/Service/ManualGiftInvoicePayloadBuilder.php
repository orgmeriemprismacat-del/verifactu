<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class ManualGiftInvoicePayloadBuilder
{
    private LegacyGiftInvoicePayloadBuilder $giftPayloads;

    public function __construct(?LegacyGiftInvoicePayloadBuilder $giftPayloads = null)
    {
        $this->giftPayloads = $giftPayloads ?? new LegacyGiftInvoicePayloadBuilder();
    }

    public function buildFromSnapshot(array $snapshot, array $input): array
    {
        $gift = $this->requiredArray($snapshot, 'gift');
        $giftId = $this->giftId($gift);
        $amount = $this->amount($this->required($input, ['amount', 'import', 'pagament'], 'payment amount'));
        $movementDate = $this->requiredString($input, ['movement_date', 'data_pag', 'dataPag'], 'movement_date');
        $method = $this->method($this->optionalString($input, ['method'], 'TRANSFERENCIA'));
        $reference = $this->optionalString($input, ['reference', 'referencia', 'referencia_bancaria']);
        $bank = $this->optionalString($input, ['bank', 'banc']);

        $invoiceKey = $this->invoiceIdempotencyKey($method, $giftId, $amount, $movementDate, $reference, $bank);
        $payload = $this->giftPayloads->build($snapshot);
        $this->assertAmountMatchesInvoiceTotal($amount, $payload);

        $payload['idempotency_key'] = $invoiceKey;
        $payload['source_channel'] = 'INTRANET';
        $payload['created_by'] = $this->optionalString($input, ['created_by', 'user', 'usuari'], 'passar-pagaments-regal');
        $payload['payment'] = $this->paymentBlock($invoiceKey, $amount, $movementDate, $method, $reference, $bank, $input);

        return $payload;
    }

    private function paymentBlock(
        string $invoiceKey,
        string $amount,
        string $movementDate,
        string $method,
        ?string $reference,
        ?string $bank,
        array $input
    ): array {
        $payment = [
            'idempotency_key' => 'PAYMENT|' . $invoiceKey,
            'movement_type' => 'CHARGE',
            'method' => $method,
            'source_channel' => 'INTRANET',
            'amount' => $amount,
            'movement_date' => $movementDate,
            'provider_ref' => $reference ?? $invoiceKey,
            'allocation_type' => 'INVOICE_PAYMENT',
        ];

        foreach ([
            'reference' => $reference,
            'bank' => $bank,
            'notes' => $this->optionalString($input, ['notes', 'obs', 'observations']),
        ] as $key => $value) {
            if ($value !== null) {
                $payment[$key] = $value;
            }
        }

        return $payment;
    }

    private function invoiceIdempotencyKey(
        string $method,
        int $giftId,
        string $amount,
        string $movementDate,
        ?string $reference,
        ?string $bank
    ): string {
        if ($reference !== null) {
            return $method . '|REGAL|ID:' . $giftId . '|REF:' . $this->keyPart($reference);
        }

        return $method
            . '|REGAL|ID:' . $giftId
            . '|DATA:' . $this->keyPart(substr($movementDate, 0, 10))
            . '|IMPORT:' . $amount
            . '|BANC:' . $this->keyPart($bank ?? 'NULL');
    }

    private function assertAmountMatchesInvoiceTotal(string $amount, array $payload): void
    {
        $total = $this->amount($payload['totals']['total'] ?? null);
        if ($amount !== $total) {
            throw SifException::validation('Manual gift payment amount must match gift invoice total');
        }
    }

    private function giftId(array $gift): int
    {
        $value = $this->optional($gift, ['ID', 'id']);
        if ($value === null || $value === '' || !is_numeric($value)) {
            throw SifException::validation('Missing gift ID');
        }

        $giftId = (int) $value;
        if ($giftId <= 0) {
            throw SifException::validation('Invalid gift ID');
        }

        return $giftId;
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

    private function requiredArray(array $data, string $key): array
    {
        if (!array_key_exists($key, $data) || !is_array($data[$key])) {
            throw SifException::validation("Missing snapshot {$key}");
        }

        return $data[$key];
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
