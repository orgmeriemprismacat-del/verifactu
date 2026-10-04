<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class EnrollmentFundTransferPayloadBuilder
{
    public function build(array $input): array
    {
        $key = $this->requiredString($input, ['idempotency_key'], 'idempotency_key');
        if (mb_strlen($key, 'UTF-8') > 160) {
            throw SifException::validation('Enrollment fund transfer idempotency key is too long');
        }

        $origin = $this->positiveInt(
            $this->required($input, ['source_enrollment_id', 'id_insc_origin', 'id_insc_origen'], 'source_enrollment_id'),
            'source_enrollment_id'
        );
        $destination = $this->positiveInt(
            $this->required($input, ['target_enrollment_id', 'id_insc_destination', 'id_insc_dest', 'id_insc_desti'], 'target_enrollment_id'),
            'target_enrollment_id'
        );

        if ($origin === $destination) {
            throw SifException::validation(
                'Enrollment fund transfer origin and destination must be different'
            );
        }

        $correlationId = $this->optionalString($input, ['correlation_id']);
        if ($correlationId === null) {
            $correlationId = 'UC006|TRANSFER|' . substr(hash('sha256', $key), 0, 32);
        }
        if (mb_strlen($correlationId, 'UTF-8') > 120) {
            throw SifException::validation('Enrollment fund transfer correlation ID is too long');
        }

        $order = $this->positiveInt($input['order'] ?? 1, 'order');

        return [
            'idempotency_key' => $key,
            'order' => $order,
            'id_insc_origin' => $origin,
            'id_insc_destination' => $destination,
            'amount' => $this->amount(
                $this->required($input, ['amount', 'import'], 'amount')
            ),
            'currency' => 'EUR',
            'uuid_operation' => $this->optionalString($input, ['uuid_operation']),
            'correlation_id' => $correlationId,
            'notes' => $this->optionalString($input, ['notes', 'obs', 'observations']),
        ];
    }

    private function amount(mixed $value): string
    {
        $text = trim(str_replace(',', '.', (string) $value));
        if (preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $text) !== 1) {
            throw SifException::validation('Invalid enrollment fund transfer amount');
        }

        $amount = (float) $text;
        if ($amount <= 0.0) {
            throw SifException::validation('Invalid enrollment fund transfer amount');
        }

        return number_format($amount, 2, '.', '');
    }

    private function positiveInt(mixed $value, string $field): int
    {
        $text = trim((string) $value);
        if (!ctype_digit($text) || (int) $text <= 0) {
            throw SifException::validation('Invalid ' . $field);
        }

        return (int) $text;
    }

    private function requiredString(array $input, array $keys, string $field): string
    {
        $value = $this->required($input, $keys, $field);
        $text = trim((string) $value);
        if ($text === '') {
            throw SifException::validation('Missing ' . $field);
        }

        return $text;
    }

    private function required(array $input, array $keys, string $field): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)
                && $input[$key] !== null
                && $input[$key] !== ''
            ) {
                return $input[$key];
            }
        }

        throw SifException::validation('Missing ' . $field);
    }

    private function optionalString(array $input, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $input) || $input[$key] === null) {
                continue;
            }

            $value = trim((string) $input[$key]);

            return $value === '' ? null : $value;
        }

        return null;
    }
}
