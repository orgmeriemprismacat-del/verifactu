<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class ManualInstallmentPaymentPayloadBuilder
{
    public function forExistingInvoice(string $uuidFactura, array $input): array
    {
        $uuidFactura = trim($uuidFactura);
        if ($uuidFactura === '') {
            throw SifException::validation('Missing installment invoice UUID');
        }

        $amount = $this->amount($this->required($input, ['amount', 'import', 'pagament'], 'installment amount'));
        $movementDate = $this->requiredString($input, ['movement_date', 'data_pag', 'dataPag'], 'movement_date');
        $idInsc = $this->requiredInt($input, ['id_insc', 'id_inscripcio', 'inscription_id'], 'installment inscription');
        $user = $this->requiredString($input, ['user', 'usuari', 'created_by'], 'installment user');
        $reference = $this->optionalString($input, ['reference', 'referencia', 'referencia_bancaria']);
        $bank = $this->optionalString($input, ['bank', 'banc']);
        $operationId = $this->optionalString($input, ['operation_id', 'installment_id', 'external_event_id', 'receipt_id']);

        $payload = [
            'idempotency_key' => $this->idempotencyKey($idInsc, $amount, $movementDate, $user, $operationId),
            'movement_type' => 'CHARGE',
            'method' => 'MANUAL',
            'source_channel' => 'INTRANET',
            'amount' => $amount,
            'movement_date' => $movementDate,
            'provider_ref' => $this->providerRef($idInsc, $user, $operationId),
            'inscription_id' => $idInsc,
            'allocations' => [[
                'uuid_factura' => $uuidFactura,
                'amount' => $amount,
                'allocation_type' => $this->optionalString($input, ['allocation_type'], 'INSTALLMENT_PAYMENT'),
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
        int $idInsc,
        string $amount,
        string $movementDate,
        string $user,
        ?string $operationId = null
    ): string {
        if ($operationId !== null) {
            return 'MANUAL|FRACCIO|EVENT:' . $this->keyPart($operationId);
        }

        // Compatibilitat: mantenim exactament la clau històrica quan el canal encara
        // no disposa d'un identificador immutable del fet econòmic. Canviar-la
        // unilateralment faria que un reintent antic pogués crear un cobrament nou.
        return 'MANUAL|FRACCIO'
            . '|ID_INSC:' . $idInsc
            . '|DATA:' . $this->keyPart(substr($movementDate, 0, 10))
            . '|IMPORT:' . $amount
            . '|USUARI:' . $this->keyPart($user);
    }

    private function providerRef(int $idInsc, string $user, ?string $operationId): string
    {
        $ref = 'FRACCIO|ID_INSC:' . $idInsc . '|USUARI:' . $this->keyPart($user);
        if ($operationId !== null) {
            $ref .= '|EVENT:' . $this->keyPart($operationId);
        }

        return $ref;
    }

    private function amount(mixed $value): string
    {
        if (!is_numeric($value)) {
            throw SifException::validation('Invalid installment amount');
        }

        $amount = (float) $value;
        if ($amount <= 0.0) {
            throw SifException::validation('Invalid installment amount');
        }

        return number_format($amount, 2, '.', '');
    }

    private function requiredInt(array $data, array $keys, string $label): int
    {
        $value = $this->required($data, $keys, $label);
        if (!is_numeric($value) || (int) $value <= 0) {
            throw SifException::validation("Invalid {$label}");
        }

        return (int) $value;
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
