<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class PackCheckoutBoundaryTest
{
    public function testPackCheckoutUsesServerAuthoritativeHolderAndEscapesPostedHtml(): void
    {
        $root = dirname(__DIR__, 3);
        $paths = [
            $root . '/codi-drive/web-actual/pagina_efectuar_pagament_grup_automatic.php',
            $root . '/codi-drive/pay-prisma-cat-canvis-verifactu/pagina_efectuar_pagament_grup_automatic.php',
        ];

        foreach ($paths as $path) {
            $source = file_get_contents($path);
            if (!is_string($source)) {
                Assert::fail('Could not load PACK checkout page: ' . $path);
            }

            Assert::stringContainsString(
                "htmlspecialchars((string) (\$_POST['dni'] ?? ''), ENT_QUOTES, 'UTF-8')",
                $source
            );
            Assert::stringContainsString(
                "htmlspecialchars((string) (\$_POST['nom-titular'] ?? ''), ENT_QUOTES, 'UTF-8')",
                $source
            );
            Assert::stringContainsString(
                "\$validatedPackCheckout['snapshot']['billing']['nif']",
                $source
            );
            Assert::stringContainsString(
                "\$validatedPackCheckout['snapshot']['billing']['name']",
                $source
            );
            Assert::stringContainsString(
                "\$validatedPackCheckout['snapshot']['billing']['email']",
                $source
            );
            Assert::stringContainsString(
                "if (\$dniTitularPag === '' || trim(\$nomTitularPag) === '' || trim(\$email) === '')",
                $source
            );
        }
    }
}
