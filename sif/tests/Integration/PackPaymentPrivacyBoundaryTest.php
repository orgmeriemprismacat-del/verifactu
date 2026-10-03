<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class PackPaymentPrivacyBoundaryTest
{
    public function testPackRedsysPayloadUsesNameNotDniAndKeepsEmailOutOfPackReturnUrls(): void
    {
        $root = dirname(__DIR__, 3);
        $paths = [
            $root . '/codi-drive/web-actual/pagina_efectuar_pagament_grup_automatic.php',
            $root . '/codi-drive/pay-prisma-cat-canvis-verifactu/pagina_efectuar_pagament_grup_automatic.php',
        ];

        foreach ($paths as $path) {
            $source = file_get_contents($path);
            if (!is_string($source)) {
                Assert::fail('Could not load Redsys PACK checkout: ' . $path);
            }

            Assert::stringContainsString(
                "\$producto = 'Pack P' . (string) \$validatedPackCheckout['pack_id']",
                $source
            );
            Assert::stringContainsString(
                "\$redsysTitular = trim(\$nomTitularPag)",
                $source
            );
            Assert::stringContainsString(
                'setParameter("DS_MERCHANT_TITULAR",$redsysTitular)',
                $source
            );

            $returnUrlBase = strpos(
                $source,
                '$urlOK="https://www.prisma.cat/respostaOkPagamentAutomatic.php";'
            );
            $legacyReturnGuard = strpos(
                $source,
                'if ($validatedPackCheckout === null)',
                $returnUrlBase === false ? 0 : $returnUrlBase
            );
            $packCallbackBranch = strpos(
                $source,
                "if ( \$tipusInsc == 'P' )",
                $legacyReturnGuard === false ? 0 : $legacyReturnGuard
            );

            Assert::same(true, $returnUrlBase !== false);
            Assert::same(true, $legacyReturnGuard !== false);
            Assert::same(true, $packCallbackBranch !== false);
            Assert::same(true, $returnUrlBase < $legacyReturnGuard);
            Assert::same(true, $legacyReturnGuard < $packCallbackBranch);

            $legacyOnlyReturnSlice = substr(
                $source,
                (int) $legacyReturnGuard,
                (int) $packCallbackBranch - (int) $legacyReturnGuard
            );

            Assert::stringContainsString(
                '$urlOK .= "?email=".rawurlencode($email)',
                $legacyOnlyReturnSlice
            );
            Assert::stringContainsString(
                '$urlKO .= "?email=".rawurlencode($email)',
                $legacyOnlyReturnSlice
            );
            Assert::same(
                1,
                substr_count($source, '$urlOK .= "?email=".rawurlencode($email)')
            );
            Assert::same(
                1,
                substr_count($source, '$urlKO .= "?email=".rawurlencode($email)')
            );

            $packProductStart = strpos(
                $source,
                "\$producto = 'Pack P' . (string) \$validatedPackCheckout['pack_id']"
            );
            $packProductEnd = strpos(
                $source,
                '}\n      else {',
                $packProductStart === false ? 0 : $packProductStart
            );
            Assert::same(true, $packProductStart !== false);
            Assert::same(true, $packProductEnd !== false);

            $packProductSlice = substr(
                $source,
                (int) $packProductStart,
                (int) $packProductEnd - (int) $packProductStart
            );
            Assert::same(false, str_contains($packProductSlice, '$dniTitularPag.'));
            Assert::stringContainsString('$redsysTitular = trim($nomTitularPag)', $packProductSlice);
        }
    }

    public function testPaymentResponsePagesDoNotExposeEmailAndUseNoStoreHeaders(): void
    {
        $root = dirname(__DIR__, 3);

        $responsePages = [
            $root . '/codi-drive/web-actual/respostaOkPagamentAutomatic.php',
            $root . '/codi-drive/web-actual/respostaKoPagamentAutomatic.php',
            $root . '/codi-drive/pay-prisma-cat-canvis-verifactu/respostaOkPagamentAutomatic.php',
            $root . '/codi-drive/pay-prisma-cat-canvis-verifactu/respostaKoPagamentAutomatic.php',
        ];

        foreach ($responsePages as $path) {
            $source = file_get_contents($path);
            if (!is_string($source)) {
                Assert::fail('Could not load Redsys response page: ' . $path);
            }

            Assert::stringContainsString('Cache-Control: private, no-store, max-age=0', $source);
            Assert::stringContainsString('Referrer-Policy: no-referrer', $source);
            Assert::same(false, str_contains($source, "\$_GET['email']"));
            Assert::same(false, str_contains($source, 'FILTER_VALIDATE_EMAIL'));
        }

        $shared = file_get_contents(
            $root . '/codi-drive/pay-prisma-cat-canvis-verifactu/CoursePaymentReturnStatus.php'
        );
        if (!is_string($shared)) {
            Assert::fail('Could not load CoursePaymentReturnStatus.php');
        }

        Assert::stringContainsString("\$_GET['order']", $shared);
        Assert::stringContainsString("\$_GET['idPag']", $shared);
        Assert::same(false, str_contains($shared, "\$_GET['email']"));
        Assert::same(false, str_contains($shared, 'FILTER_VALIDATE_EMAIL'));

        foreach ([
            $root . '/codi-drive/pay-prisma-cat-canvis-verifactu/respostaOkPagamentAutomatic.php',
            $root . '/codi-drive/pay-prisma-cat-canvis-verifactu/respostaKoPagamentAutomatic.php',
        ] as $path) {
            $source = file_get_contents($path);
            Assert::stringContainsString(
                "require_once __DIR__ . '/CoursePaymentReturnStatus.php'",
                $source
            );
            Assert::stringContainsString('uc014RenderPaymentReturn(', $source);
        }
    }
}
