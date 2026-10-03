<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class PackDocumentationConsistencyTest
{
    public function testIntegratedPackDocumentationMatchesExecutablePackNContract(): void
    {
        $root = dirname(__DIR__, 3);
        $integratedPath = $root . '/documentacio/07-uml-integrat/uc-015-comprar-pack.md';
        $sequencesPath = $root . '/documentacio/07-uml-integrat/uc-015-sequencies-actual-final.md';

        $integrated = file_get_contents($integratedPath);
        $sequences = file_get_contents($sequencesPath);
        if (!is_string($integrated) || !is_string($sequences)) {
            Assert::fail('Could not load UC-015 documentation');
        }

        Assert::stringContainsString('PACK N', $integrated);
        Assert::stringContainsString('N components (N ≥ 2)', $integrated);
        Assert::stringContainsString('PACK_BASE', $integrated);
        Assert::stringContainsString('PACK_DISCOUNT', $integrated);
        Assert::stringContainsString('PACK_TOTAL', $integrated);
        Assert::stringContainsString('pagament complet únic del PACK', $integrated);

        foreach ([
            'adaptació pendent',
            'serveis parcials',
            'Classificació parts fiscals [PENDENT]',
            'Comprar pack de dos cursos',
            'descompte només curs 2',
        ] as $stalePhrase) {
            Assert::same(false, str_contains($integrated, $stalePhrase));
        }

        Assert::same(false, str_contains($sequences, 'majoritàriament implementada'));
        Assert::stringContainsString(
            'Seqüència FINAL: **implementada** al flux PACK asíncron',
            $sequences
        );
    }
}
