<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class LegacyPackCallbackBoundaryTest
{
    public function testLegacyPackCallbackIsDisabledByDefaultBeforeLegacyMutationCode(): void
    {
        $path = dirname(__DIR__, 2)
            . '/codi-drive/pay-prisma-cat-canvis-verifactu/realitzaPagamentPackAutomatic.php';

        $source = file_get_contents($path);
        if (!is_string($source)) {
            Assert::fail('Could not load legacy pack callback');
        }

        Assert::stringContainsString('SIF_PACK_LEGACY_CALLBACK_ENABLED', $source);
        Assert::stringContainsString("getenv('SIF_PACK_LEGACY_CALLBACK_ENABLED') ?: '0'", $source);
        Assert::stringContainsString('http_response_code(410)', $source);

        $guard = strpos($source, 'SIF_PACK_LEGACY_CALLBACK_ENABLED');
        $legacyInvoiceInsert = strpos($source, 'INSERT INTO factures');

        Assert::same(true, $guard !== false);
        Assert::same(true, $legacyInvoiceInsert !== false);
        Assert::same(true, $guard < $legacyInvoiceInsert);
    }
}
