<?php

declare(strict_types=1);

namespace Prisma\Sif\Domain;

/**
 * Exact decimal monetary parsing/formatting in integer cents.
 *
 * Rejects values with more than two meaningful decimal places, scientific
 * notation and malformed inputs instead of rounding them silently.
 */
final class DecimalAmount
{
    public static function cents(mixed $value): int
    {
        $text = self::text($value);

        if (preg_match('/^(-?)(\d{1,10})(?:\.(\d{1,2}))?$/D', $text, $match) !== 1) {
            throw new \InvalidArgumentException(
                'Monetary amount must be a plain decimal with at most two decimal places.'
            );
        }

        $cents = ((int) $match[2] * 100)
            + (int) str_pad($match[3] ?? '', 2, '0');

        return ($match[1] ?? '') === '-' ? -$cents : $cents;
    }

    public static function normalize(mixed $value): string
    {
        return self::format(self::cents($value));
    }

    public static function format(int $cents): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);
        $formatted = intdiv($absolute, 100)
            . '.'
            . str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        return $negative && $absolute !== 0 ? '-' . $formatted : $formatted;
    }

    private static function text(mixed $value): string
    {
        if (is_int($value) || is_string($value)) {
            return trim((string) $value);
        }

        if (is_float($value)) {
            if (!is_finite($value)) {
                throw new \InvalidArgumentException('Monetary amount must be finite.');
            }

            $text = rtrim(rtrim(sprintf('%.14F', $value), '0'), '.');

            return $text === '-0' ? '0' : $text;
        }

        if ($value instanceof \Stringable) {
            return trim((string) $value);
        }

        throw new \InvalidArgumentException('Unsupported monetary amount type.');
    }
}
