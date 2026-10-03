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

            $legacyGuardNeedle = 'if ($validatedPackCheckout === null) {'
                . PHP_EOL
                . '         $urlOK .= "?email=".rawurlencode($email);'
                . PHP_EOL
                . '         $urlKO .= "?email=".rawurlencode($email);'
                . PHP_EOL
                . '      }';
            Assert::stringContainsString($legacyGuardNeedle, $source);

            $packBranchStart = strpos($source, "if ( \$tipusInsc == 'P' )");
            $amountStart = strpos($source, '$amount=$importPagare * 100;', $packBranchStart === false ? 0 : $packBranchStart);
            if ($packBranchStart === false || $amountStart === false || $amountStart <= $packBranchStart) {
                Assert::fail('Could not isolate authoritative PACK callback branch: ' . $path);
            }

            $packBranch = substr(
                $source,
                $packBranchStart,
                $amountStart - $packBranchStart
            );
            Assert::stringContainsString('$url = $packCallbackUrl;', $packBranch);
            Assert::same(false, str_contains($packBranch, '?email='));
            Assert::same(false, str_contains($packBranch, 'rawurlencode($email)'));
        }
    }

    public function testPaymentResponsePagesDoNotExposeEmailHintsOrTrustBrowserReturn(): void
    {
        $root = dirname(__DIR__, 3);

        $webPages = [
            $root . '/codi-drive/web-actual/respostaOkPagamentAutomatic.php',
            $root . '/codi-drive/web-actual/respostaKoPagamentAutomatic.php',
        ];
        foreach ($webPages as $path) {
            $source = file_get_contents($path);
            if (!is_string($source)) {
                Assert::fail('Could not load Redsys response page: ' . $path);
            }

            Assert::stringContainsString("Referrer-Policy: no-referrer", $source);
            Assert::same(false, str_contains($source, "\$_GET['email']"));
            Assert::same(false, str_contains($source, 'FILTER_VALIDATE_EMAIL'));
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
            Assert::same(false, str_contains($source, "\$_GET['email']"));
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
        Assert::stringContainsString("'authoritative' => true", $shared);
    }
}
