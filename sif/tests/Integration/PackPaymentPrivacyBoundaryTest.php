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
            Assert::stringContainsString(
                '$urlOK .= "?email=".rawurlencode($email)',
                $source
            );
            Assert::stringContainsString(
                '$urlKO .= "?email=".rawurlencode($email)',
                $source
            );
        }
    }

    public function testPaymentResponsePagesTreatEmailAsOptionalEscapedHint(): void
    {
        $root = dirname(__DIR__, 3);
        $paths = [
            $root . '/codi-drive/web-actual/respostaOkPagamentAutomatic.php',
            $root . '/codi-drive/web-actual/respostaKoPagamentAutomatic.php',
            $root . '/codi-drive/pay-prisma-cat-canvis-verifactu/respostaOkPagamentAutomatic.php',
            $root . '/codi-drive/pay-prisma-cat-canvis-verifactu/respostaKoPagamentAutomatic.php',
        ];

        foreach ($paths as $path) {
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
    }
}
