<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Service\RedsysDsOrderGenerator;
use Prisma\Sif\Tests\Support\Assert;

final class RedsysDsOrderGeneratorTest
{
    public function testGeneratesTwelveNumericCharactersCompatibleWithRedsys(): void
    {
        $generator = new RedsysDsOrderGenerator();

        for ($i = 0; $i < 50; $i++) {
            $order = $generator->generate();
            Assert::same(12, strlen($order));
            Assert::same(true, ctype_digit($order));
            Assert::same(true, preg_match('/^[0-9]{12}$/D', $order) === 1);
        }
    }
}
