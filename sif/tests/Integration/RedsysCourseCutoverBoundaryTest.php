<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RedsysCourseCutoverBoundaryTest
{
    public function testCheckoutRequiresExplicitCutoverFlagBeforeUsingSifMerchantUrl(): void
    {
        $source = $this->read(
            'codi-drive/pay-prisma-cat-canvis-verifactu/pagina_efectuar_pagament_automatic.php'
        );

        Assert::stringContainsString('SIF_REDSYS_COURSE_CUTOVER_ENABLED', $source);
        Assert::stringContainsString('SIF_REDSYS_CALLBACK_URL', $source);
        Assert::stringContainsString('SIF_REDSYS_CALLBACK_URL_REQUIRED_FOR_CUTOVER', $source);
        Assert::stringContainsString('SIF_REDSYS_CALLBACK_URL_MUST_USE_HTTPS', $source);
        Assert::stringContainsString('if ($courseCutoverEnabled) {', $source);
        Assert::stringContainsString('$url = $sifMerchantUrl;', $source);
        Assert::stringContainsString('$url = $legacyMerchantUrl;', $source);

        if (str_contains($source, '$url = $sifMerchantUrl !== \'\' ? $sifMerchantUrl : $legacyMerchantUrl;')) {
            Assert::fail('SIF callback URL alone must not activate the course cutover.');
        }
    }

    public function testCheckoutRequiresConfiguredHttpsRedsysGateway(): void
    {
        $source = $this->read(
            'codi-drive/pay-prisma-cat-canvis-verifactu/pagina_efectuar_pagament_automatic.php'
        );

        Assert::stringContainsString("getenv('REDSYS_GATEWAY_URL')", $source);
        Assert::stringContainsString('REDSYS_GATEWAY_URL_NOT_CONFIGURED', $source);
        Assert::stringContainsString('REDSYS_GATEWAY_URL_MUST_USE_HTTPS', $source);
        Assert::stringContainsString('htmlspecialchars($gatewayUrl', $source);

        if (preg_match('/<form[^>]+action=[\"\']https:\/\/sis(?:-t)?\.redsys\.es/i', $source) === 1) {
            Assert::fail('Redsys gateway must not be hardcoded in the candidate checkout.');
        }
    }

    public function testLegacyCallbacksFailClosedBeforeAnyDependencyOrSideEffectDuringCutover(): void
    {
        foreach ([
            'codi-drive/pay-prisma-cat-canvis-verifactu/doit.php',
            'codi-drive/pay-prisma-cat-canvis-verifactu/realitzaPagamentAutomatic.php',
        ] as $relativePath) {
            $source = $this->read($relativePath);

            Assert::stringContainsString('SIF_REDSYS_COURSE_CUTOVER_ENABLED', $source);
            Assert::stringContainsString('http_response_code(410)', $source);
            Assert::stringContainsString('Callback legacy de curs retirat', $source);

            $guard = strpos($source, 'SIF_REDSYS_COURSE_CUTOVER_ENABLED');
            $firstInclude = strpos($source, 'include(');
            $firstRequire = strpos($source, 'require');

            Assert::same(true, $guard !== false);
            Assert::same(true, $firstInclude !== false || $firstRequire !== false);

            $firstDependency = $firstInclude;
            if ($firstDependency === false || ($firstRequire !== false && $firstRequire < $firstDependency)) {
                $firstDependency = $firstRequire;
            }

            Assert::same(true, $guard < $firstDependency);
        }
    }

    public function testCutoverCanBeRolledBackByDisablingSingleFlagBeforePermanentLegacyRemoval(): void
    {
        $checkout = $this->read(
            'codi-drive/pay-prisma-cat-canvis-verifactu/pagina_efectuar_pagament_automatic.php'
        );

        $cutoverBlock = strstr($checkout, 'if ($courseCutoverEnabled) {');
        if ($cutoverBlock === false) {
            Assert::fail('Course cutover block not found');
        }

        Assert::stringContainsString('} else {', $cutoverBlock);
        Assert::stringContainsString('$url = $legacyMerchantUrl;', $cutoverBlock);
    }

    private function read(string $relativePath): string
    {
        $path = dirname(__DIR__, 3) . '/' . $relativePath;
        $content = file_get_contents($path);
        if ($content === false) {
            Assert::fail('Could not read ' . $relativePath);
        }

        return $content;
    }
}
