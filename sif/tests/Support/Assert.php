<?php

namespace Prisma\Sif\Tests\Support;

final class Assert
{
    public static function same(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException('Expected ' . self::export($expected) . ', got ' . self::export($actual));
        }
    }

    public static function notSame(mixed $unexpected, mixed $actual): void
    {
        if ($unexpected === $actual) {
            throw new \RuntimeException('Did not expect ' . self::export($actual));
        }
    }

    public static function matchesRegularExpression(string $pattern, string $actual): void
    {
        if (preg_match($pattern, $actual) !== 1) {
            throw new \RuntimeException("Expected value to match {$pattern}, got " . self::export($actual));
        }
    }

    public static function stringContainsString(string $needle, string $haystack): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new \RuntimeException('Expected string to contain ' . self::export($needle));
        }
    }

    public static function throws(string $expectedClass, callable $callback, ?int $expectedCode = null): \Throwable
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            if (!$exception instanceof $expectedClass) {
                throw new \RuntimeException('Expected exception ' . $expectedClass . ', got ' . $exception::class);
            }

            if ($expectedCode !== null && $exception->getCode() !== $expectedCode) {
                throw new \RuntimeException('Expected exception code ' . $expectedCode . ', got ' . $exception->getCode());
            }

            return $exception;
        }

        throw new \RuntimeException('Expected exception ' . $expectedClass . ' was not thrown');
    }

    public static function fail(string $message): void
    {
        throw new \RuntimeException($message);
    }

    private static function export(mixed $value): string
    {
        return var_export($value, true);
    }
}
