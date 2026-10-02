<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class PackPaymentPrivacyBoundaryTest
{
    public function testPackRedsysPayloadUsesNameNotDniAndOmitsEmailFromReturnUrls(): void
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
            Assert::stringContainsString(
                'if ($validatedPackCheckout === null)',
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
            $emailOk = strpos(
                $source,
                '$urlOK .= "?email=".rawurlencode($email)',
                $legacyReturnGuard === false ? 0 : $legacyReturnGuard
            );
            $emailKo = strpos(
                $source,
                '$urlKO .= "?email=".rawurlencode($email)',
                $legacyReturnGuard === false ? 0 : $legacyReturnGuard
            );
            $packCallbackBranch = strpos(
                $source,
                "if ( $tipusInsc == 'P' )",
                $legacyReturnGuard === false ? 0 : $legacyReturnGuard
            );

            Assert::same(true, $returnUrlBase !== false);
            Assert::same(true, $legacyReturnGuard !== false);
            Assert::same(true, $emailOk !== false);
            Assert::same(true, $emailKo !== false);
            Assert::same(true, $packCallbackBranch !== false);
            Assert::same(true, $returnUrlBase < $legacyReturnGuard);
            Assert::same(true, $legacyReturnGuard < $emailOk);
            Assert::same(true, $legacyReturnGuard < $emailKo);
            Assert::same(true, $emailOk < $packCallbackBranch);
            Assert::same(true, $emailKo < $packCallbackBranch);

            $guardSlice = substr(
                $source,
                (int) $legacyReturnGuard,
                (int) $packCallbackBranch - (int) $legacyReturnGuard
            );
            Assert::stringContainsString(
                '$urlOK .= "?email=".rawurlencode($email)',
                $guardSlice
            );
            Assert::stringContainsString(
                '$urlKO .= "?email=".rawurlencode($email)',
                $guardSlice
            );
        }
    }

    public function testPaymentResponsePagesTreatEmailAsOptionalEscapedHint(): void
    {
        $root = dirname(__DIR__, 3);

        $legacyPages = [
            $root . '/codi-drive/web-actual/respostaOkPagamentAutomatic.php',
            $root . '/codi-drive/web-actual/respostaKoPagamentAutomatic.php',
        ];
        foreach ($legacyPages as $path) {
            $source = file_get_contents($path);
            if (!is_string($source)) {
                Assert::fail('Could not load Redsys response page: ' . $path);
            }

            Assert::stringContainsString("(\$_GET['email'] ?? '')", $source);
            Assert::stringContainsString('FILTER_VALIDATE_EMAIL', $source);
            Assert::stringContainsString(
                "htmlspecialchars(\$emailRaw, ENT_QUOTES, 'UTF-8')",
                $source
            );
            Assert::stringContainsString("\$emailHint = \$email !== ''", $source);

            if (str_contains($source, "\$email = \$_GET['email'];")) {
                Assert::fail('Redsys response page must not trust raw email query data.');
            }
        }

        $payPages = [
            $root . '/codi-drive/pay-prisma-cat-canvis-verifactu/respostaOkPagamentAutomatic.php',
            $root . '/codi-drive/pay-prisma-cat-canvis-verifactu/respostaKoPagamentAutomatic.php',
        ];
        foreach ($payPages as $path) {
            $source = file_get_contents($path);
            if (!is_string($source)) {
                Assert::fail('Could not load pay.prisma.cat Redsys response page: ' . $path);
            }

            Assert::stringContainsString(
                "require_once __DIR__ . '/CoursePaymentReturnStatus.php'",
                $source
            );
            Assert::stringContainsString('uc014RenderPaymentReturn(', $source);
        }

        $shared = file_get_contents(
            $root . '/codi-drive/pay-prisma-cat-canvis-verifactu/CoursePaymentReturnStatus.php'
        );
        if (!is_string($shared)) {
            Assert::fail('Could not load CoursePaymentReturnStatus.php');
        }

        Assert::stringContainsString("(\$_GET['email'] ?? '')", $shared);
        Assert::stringContainsString('FILTER_VALIDATE_EMAIL', $shared);
        Assert::stringContainsString(
            "htmlspecialchars(\$view['email'], ENT_QUOTES, 'UTF-8')",
            $shared
        );
    }
}
