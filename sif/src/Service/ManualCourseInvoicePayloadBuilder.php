<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class ManualCourseInvoicePayloadBuilder
{
    private LegacyCourseInvoicePayloadBuilder $coursePayloads;

    public function __construct(?LegacyCourseInvoicePayloadBuilder $coursePayloads = null)
    {
        $this->coursePayloads = $coursePayloads ?? new LegacyCourseInvoicePayloadBuilder();
    }

    public function buildFromSnapshot(array $snapshot, array $input): array
    {
        $idpag = $this->idpag($snapshot, $input);
        $amount = $this->amount($this->required($input, ['amount', 'import', 'pagament'], 'payment amount'));
        $movementDate = $this->requiredString($input, ['movement_date', 'data_pag', 'dataPag'], 'movement_date');
        $method = $this->method($this->optionalString($input, ['method'], 'TRANSFERENCIA'));
        $reference = $this->optionalString($input, ['reference', 'referencia', 'referencia_bancaria']);
        $bank = $this->optionalString($input, ['bank', 'banc']);

        $snapshot['payment'] = array_replace($snapshot['payment'] ?? [], ['amount' => $amount]);

        $invoiceKey = $this->invoiceIdempotencyKey($method, $idpag, $amount, $movementDate, $reference, $bank);
        $payload = $this->coursePayloads->build($snapshot);
        $payload['idempotency_key'] = $invoiceKey;
        $payload['source_channel'] = 'INTRANET';
        $payload['created_by'] = $this->optionalString($input, ['created_by', 'user', 'usuari'], 'passar-pagaments');
        $payload['relations'] = $this->withManualRelation($payload['relations'] ?? [], $idpag);
        $payload['payment'] = $this->paymentBlock($invoiceKey, $idpag, $amount, $movementDate, $method, $reference, $bank, $input);

        return $payload;
    }

    private function paymentBlock(
        string $invoiceKey,
        int $idpag,
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
            'idpag' => $idpag,
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
        int $idpag,
        string $amount,
        string $movementDate,
        ?string $reference,
        ?string $bank
    ): string {
        if ($reference !== null) {
            return $method . '|CURS|IDPAG:' . $idpag . '|REF:' . $this->keyPart($reference);
        }

        return $method
            . '|CURS|IDPAG:' . $idpag
            . '|DATA:' . $this->keyPart(substr($movementDate, 0, 10))
            . '|IMPORT:' . $amount
            . '|BANC:' . $this->keyPart($bank ?? 'NULL');
    }

    private function withManualRelation(array $relations, int $idpag): array
    {
        if ($relations === []) {
            $relations[] = [
                'source_type' => 'INSCRIPCIO',
                'source_id' => $idpag,
                'visible_alumne' => 1,
            ];
        }

        $relations[0]['idpag'] = $idpag;

        return $relations;
    }

    private function idpag(array $snapshot, array $input): int
    {
        $inscription = $snapshot['inscription'] ?? [];
        if (!is_array($inscription)) {
            throw SifException::validation('Missing snapshot inscription');
        }

        $value = $this->optional($input, ['idpag', 'IDPAG'])
            ?? $this->optional($inscription, ['IDPAG', 'idpag']);

        if ($value === null || $value === '' || !is_numeric($value)) {
            throw SifException::validation('Missing manual course IDPAG');
        }

        $idpag = (int) $value;
        if ($idpag <= 0) {
            throw SifException::validation('Invalid manual course IDPAG');
        }

        return $idpag;
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
