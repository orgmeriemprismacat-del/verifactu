<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class PackDeploymentParityBoundaryTest
{
    public function testCriticalPackAdaptersStayByteIdenticalAcrossWebAndPayCopies(): void
    {
        $root = dirname(__DIR__, 3);
        $pairs = [
            [
                $root . '/codi-drive/web-actual/inc/PackPaymentGate.php',
                $root . '/codi-drive/pay-prisma-cat-canvis-verifactu/inc/PackPaymentGate.php',
            ],
            [
                $root . '/codi-drive/web-actual/inc/SifPaymentIntentClient.php',
                $root . '/codi-drive/pay-prisma-cat-canvis-verifactu/inc/SifPaymentIntentClient.php',
            ],
        ];

        foreach ($pairs as [$webPath, $payPath]) {
            $web = file_get_contents($webPath);
            $pay = file_get_contents($payPath);

            if (!is_string($web) || !is_string($pay)) {
                Assert::fail('Could not load duplicated PACK adapter pair');
            }

            Assert::same(
                hash('sha256', $web),
                hash('sha256', $pay)
            );
        }
    }

    public function testCheckoutCopiesKeepSamePackBusinessBoundaryDespitePresentationDifferences(): void
    {
        $root = dirname(__DIR__, 3);
        $paths = [
            $root . '/codi-drive/web-actual/pagina_efectuar_pagament_grup_automatic.php',
            $root . '/codi-drive/pay-prisma-cat-canvis-verifactu/pagina_efectuar_pagament_grup_automatic.php',
        ];

        $required = [
            'PackPaymentGate::assertCanPrepare',
            'SifPaymentIntentClient',
            "getenv('SIF_REDSYS_CALLBACK_URL')",
            "getenv('SIF_REDSYS_PAYMENT_URL')",
            "'https://sis.redsys.es/sis/realizarPago'",
            "'https://sis-t.redsys.es:25443/sis/realizarPago'",
            "\$producto = 'Pack P' . (string) \$validatedPackCheckout['pack_id']",
            "\$redsysTitular = trim(\$nomTitularPag)",
            '\$urlOK="https://www.prisma.cat/respostaOkPagamentAutomatic.php";',
            '\$urlKO="https://www.prisma.cat/respostaKoPagamentAutomatic.php";',
            "if ( \$tipusInsc == 'P' )",
            '\$url = \$packCallbackUrl;',
        ];

        foreach ($paths as $path) {
            $source = file_get_contents($path);
            if (!is_string($source)) {
                Assert::fail('Could not load PACK checkout copy: ' . $path);
            }

            foreach ($required as $marker) {
                Assert::stringContainsString($marker, $source);
            }
        }
    }

    public function testPackReturnUrlsRemainOnPublicWebBoundary(): void
    {
        $root = dirname(__DIR__, 3);
        $paths = [
            $root . '/codi-drive/web-actual/pagina_efectuar_pagament_grup_automatic.php',
            $root . '/codi-drive/pay-prisma-cat-canvis-verifactu/pagina_efectuar_pagament_grup_automatic.php',
        ];

        foreach ($paths as $path) {
            $source = file_get_contents($path);
            if (!is_string($source)) {
                Assert::fail('Could not load PACK checkout return boundary: ' . $path);
            }

            Assert::stringContainsString(
                '\$urlOK="https://www.prisma.cat/respostaOkPagamentAutomatic.php";',
                $source
            );
            Assert::stringContainsString(
                '\$urlKO="https://www.prisma.cat/respostaKoPagamentAutomatic.php";',
                $source
            );
        }
    }
}
