<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Tests\Support\Assert;

final class UuidGeneratorTest
{
    public function testGenerateReturnsVersionFourUuid(): void
    {
        $uuid = (new UuidGenerator())->generate();

        Assert::matchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    public function testGenerateReturnsDifferentValues(): void
    {
        $generator = new UuidGenerator();

        Assert::notSame($generator->generate(), $generator->generate());
    }
}
