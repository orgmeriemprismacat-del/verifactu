<?php

declare(strict_types=1);

namespace Prisma\Sif\Contract;

interface PayloadIdempotencyValidatorInterface
{
    /** Arrays are recursively canonicalized; strings are hashed as exact bytes. */
    public function calculateHash(array|string $payload): string;

    /** @throws \Prisma\Sif\Exception\SifException when the payload differs or the hash is invalid. */
    public function assertMatches(array|string $payload, string $storedHash): void;
}
