<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\DecimalAmount;
use Prisma\Sif\Tests\Support\Assert;

final class DecimalAmountTest
{
    public function testParsesAndFormatsExactCentsWithoutFloatArithmetic(): void
    {
        Assert::same(7300, DecimalAmount::cents('73.00'));
        Assert::same(7301, DecimalAmount::cents('73.01'));
        Assert::same(-125, DecimalAmount::cents('-1.25'));
        Assert::same('0.10', DecimalAmount::normalize('0.1'));
        Assert::same('73.00', DecimalAmount::format(7300));
        Assert::same('-1.25', DecimalAmount::format(-125));
    }

    public function testRejectsMoreThanTwoDecimalsScientificNotationAndMalformedValues(): void
    {
        Assert::throws(\InvalidArgumentException::class, static function (): void {
            DecimalAmount::cents('25.005');
        });
        Assert::throws(\InvalidArgumentException::class, static function (): void {
            DecimalAmount::cents('1e2');
        });
        Assert::throws(\InvalidArgumentException::class, static function (): void {
            DecimalAmount::cents('abc');
        });
    }
}
