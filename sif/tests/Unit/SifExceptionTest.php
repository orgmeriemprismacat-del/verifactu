<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Tests\Support\Assert;

final class SifExceptionTest
{
    public function testValidationExceptionUsesHttpLikeCode422(): void
    {
        $exception = SifException::validation('Missing invoice field');

        Assert::same('Missing invoice field', $exception->getMessage());
        Assert::same(422, $exception->getCode());
    }

    public function testConflictExceptionUsesHttpLikeCode409(): void
    {
        $exception = SifException::conflict('Duplicate idempotency key');

        Assert::same('Duplicate idempotency key', $exception->getMessage());
        Assert::same(409, $exception->getCode());
    }
}
