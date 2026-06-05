<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Tests\Support\Assert;

final class HashCalculatorTest
{
    public function testHashIsStableForCanonicalPayload(): void
    {
        $payload = [
            'uuid_factura' => '11111111-1111-4111-8111-111111111111',
            'num_visible' => 'A2026/000001',
            'total' => '120.00',
        ];

        $hash = (new HashCalculator())->calculate($payload, null);

        Assert::same(64, strlen($hash));
        Assert::same($hash, (new HashCalculator())->calculate($payload, null));
    }

    public function testHashIgnoresAssociativeKeyOrder(): void
    {
        $first = [
            'num_visible' => 'A2026/000001',
            'billing' => [
                'nif' => '12345678Z',
                'name' => 'Client Exemple',
            ],
            'total' => '120.00',
        ];
        $second = [
            'total' => '120.00',
            'billing' => [
                'name' => 'Client Exemple',
                'nif' => '12345678Z',
            ],
            'num_visible' => 'A2026/000001',
        ];

        $calculator = new HashCalculator();

        Assert::same($calculator->calculate($first, null), $calculator->calculate($second, null));
    }

    public function testPreviousHashChangesResult(): void
    {
        $payload = [
            'uuid_factura' => '11111111-1111-4111-8111-111111111111',
            'num_visible' => 'A2026/000001',
            'total' => '120.00',
        ];

        $calculator = new HashCalculator();

        Assert::notSame(
            $calculator->calculate($payload, null),
            $calculator->calculate($payload, str_repeat('a', 64))
        );
    }
}
