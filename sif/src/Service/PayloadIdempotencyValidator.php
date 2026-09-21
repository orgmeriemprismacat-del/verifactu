<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Contract\PayloadIdempotencyValidatorInterface;
use Prisma\Sif\Exception\SifException;

final class PayloadIdempotencyValidator implements PayloadIdempotencyValidatorInterface
{
    public function calculateHash(array|string $payload): string
    {
        if (is_string($payload)) {
            return hash('sha256', $payload);
        }

        try {
            $json = json_encode(
                $this->canonicalize($payload),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            throw SifException::validation('Invalid idempotency payload encoding');
        }

        return hash('sha256', $json);
    }

    public function assertMatches(array|string $payload, string $storedHash): void
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $storedHash) !== 1) {
            throw SifException::conflict('Stored idempotency payload hash is unavailable or invalid');
        }

        if (!hash_equals($storedHash, $this->calculateHash($payload))) {
            throw SifException::conflict('Idempotency key already exists with different payload');
        }
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
