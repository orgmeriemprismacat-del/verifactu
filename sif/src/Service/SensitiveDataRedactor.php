<?php

namespace Prisma\Sif\Service;

final class SensitiveDataRedactor
{
    private const SENSITIVE_KEYS = [
        'pan',
        'card',
        'card_number',
        'cardnumber',
        'cvv',
        'cvc',
        'ds_signature',
        'signature',
        'secret',
        'password',
        'merchant_key',
        'merchantkey',
    ];

    public function redact(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $keys = implode('|', array_map(
            static fn (string $key): string => preg_quote($key, '/'),
            self::SENSITIVE_KEYS
        ));

        $value = preg_replace(
            '/((?:"|\')?(?:' . $keys . ')(?:"|\')?\s*[:=]\s*)(?:"[^"]*"|\'[^\']*\'|[^\s,;&}]+)/i',
            '$1[REDACTED]',
            $value
        ) ?? $value;

        return preg_replace_callback(
            '/(?<!\d)(?:\d[ -]?){13,19}(?!\d)/',
            function (array $match): string {
                $digits = preg_replace('/\D+/', '', $match[0]) ?? '';
                if (strlen($digits) < 13 || strlen($digits) > 19 || !$this->passesLuhn($digits)) {
                    return $match[0];
                }

                return '[REDACTED_PAN]';
            },
            $value
        ) ?? $value;
    }

    private function passesLuhn(string $digits): bool
    {
        $sum = 0;
        $alternate = false;

        for ($index = strlen($digits) - 1; $index >= 0; $index--) {
            $digit = (int) $digits[$index];
            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
            $alternate = !$alternate;
        }

        return $sum % 10 === 0;
    }
}
