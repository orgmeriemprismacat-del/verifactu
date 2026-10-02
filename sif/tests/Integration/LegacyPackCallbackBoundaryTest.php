<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class LegacyPackCallbackBoundaryTest
{
    public function testProductionLegacyPackCallbacksArePhysicallyRemoved(): void
    {
        foreach ([
            'codi-drive/pay-prisma-cat-canvis-verifactu/realitzaPagamentPackAutomatic.php',
            'codi-drive/web-actual/realitzaPagamentPackAutomatic.php',
        ] as $relativePath) {
            $path = dirname(__DIR__, 3) . '/' . $relativePath;
            Assert::same(false, is_file($path));
        }
    }

    public function testLegacyPackTestHarnessIsFailClosedOutsideExplicitTestEnvironment(): void
    {
        $source = $this->read(
            'codi-drive/web-actual/realitzaPagamentPackAutomaticProva.php'
        );

        Assert::stringContainsString('SIF_PACK_LEGACY_TEST_CALLBACK_ENABLED', $source);
        Assert::stringContainsString(
            "in_array(\$sifEnv, ['test', 'preproduction'], true)",
            $source
        );
        Assert::stringContainsString('http_response_code(410)', $source);

        $guard = strpos($source, 'SIF_PACK_LEGACY_TEST_CALLBACK_ENABLED');
        $firstMail = strpos($source, 'new Mail()');
        $legacyInvoiceInsert = strpos($source, 'INSERT INTO factures');

        Assert::same(true, $guard !== false);
        Assert::same(true, $firstMail !== false);
        Assert::same(true, $legacyInvoiceInsert !== false);
        Assert::same(true, $guard < $firstMail);
        Assert::same(true, $guard < $legacyInvoiceInsert);
    }

    private function read(string $relativePath): string
    {
        $path = dirname(__DIR__, 3) . '/' . $relativePath;
        $source = file_get_contents($path);
        if (!is_string($source)) {
            Assert::fail('Could not load legacy PACK callback boundary ' . $relativePath);
        }

        return $source;
    }
}
