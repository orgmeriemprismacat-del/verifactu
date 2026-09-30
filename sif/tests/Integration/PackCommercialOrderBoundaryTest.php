<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class PackCommercialOrderBoundaryTest
{
    public function testPackPresentationAndEnrollmentUseSameDeterministicOrder(): void
    {
        $root = dirname(__DIR__, 3);
        $paths = [
            $root . '/codi-drive/web-actual/ajax/enviarInscripcioPack.php',
            $root . '/codi-drive/web-actual/Pack.php',
            $root . '/codi-drive/web-actual/InfoPack.php',
        ];

        foreach ($paths as $path) {
            $source = file_get_contents($path);
            if (!is_string($source)) {
                Assert::fail('Could not load PACK commercial-order source: ' . $path);
            }

            Assert::stringContainsString(
                'ORDER BY c.DATAI, p.ID_CURS',
                $source
            );
        }
    }

    public function testPackOrdinalIsFrozenFromDeterministicComponentLoop(): void
    {
        $path = dirname(__DIR__, 3)
            . '/codi-drive/web-actual/ajax/enviarInscripcioPack.php';

        $source = file_get_contents($path);
        if (!is_string($source)) {
            Assert::fail('Could not load PACK enrollment source');
        }

        $queryOrder = strpos($source, 'ORDER BY c.DATAI, p.ID_CURS');
        $ordinalSnapshot = strpos($source, "'PACK|%s PACK_ORDINAL|%d");
        $ordinalValue = strpos($source, '$i + 1');

        Assert::same(true, $queryOrder !== false);
        Assert::same(true, $ordinalSnapshot !== false);
        Assert::same(true, $ordinalValue !== false);
        Assert::same(true, $queryOrder < $ordinalSnapshot);
        Assert::same(true, $ordinalSnapshot < $ordinalValue);
    }
}
