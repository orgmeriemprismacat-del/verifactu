<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RedsysGiftCutoverBoundaryTest
{
    public function testGiftCheckoutUsesSignedSifIntentAndExplicitCutoverFlags(): void
    {
        $source = $this->read(
            'codi-drive/pay-prisma-cat-canvis-verifactu/pagina_efectuar_pagament_regal_automatic.php'
        );

        Assert::stringContainsString('SifRedsysGiftIntentClient.php', $source);
        Assert::stringContainsString('SIF_REDSYS_GIFT_CUTOVER_ENABLED', $source);
        Assert::stringContainsString('SIF_REDSYS_GIFT_LEGACY_DRAIN_CONFIRMED', $source);
        Assert::stringContainsString('SIF_REDSYS_CALLBACK_URL', $source);
        Assert::stringContainsString('$url = $sifMerchantUrl;', $source);
        Assert::stringContainsString('$url = $legacyMerchantUrl;', $source);
        Assert::stringContainsString('DS_MERCHANT_MERCHANTDATA', $source);
    }

    public function testGiftCheckoutDoesNotTrustPostedAmountOrHardcodeRedsysSecrets(): void
    {
        $source = $this->read(
            'codi-drive/pay-prisma-cat-canvis-verifactu/pagina_efectuar_pagament_regal_automatic.php'
        );

        Assert::stringContainsString("getenv('REDSYS_MERCHANT_CODE')", $source);
        Assert::stringContainsString("getenv('REDSYS_TERMINAL')", $source);
        Assert::stringContainsString("getenv('REDSYS_MERCHANT_KEY')", $source);
        Assert::stringContainsString("getenv('REDSYS_GATEWAY_URL')", $source);
        Assert::stringContainsString("$importPag = (string) $intent['amount'];", $source);

        if (str_contains($source, '$id=time()')
            || str_contains($source, '$id = time()')
            || preg_match('/\$kc\s*=\s*[\'"][A-Za-z0-9+\/=]{16,}[\'"]/', $source) === 1
        ) {
            Assert::fail('Gift checkout must not generate order from time() or embed a Redsys key.');
        }

        if (str_contains($source, '$_POST[\'import\'] * 100')
            || str_contains($source, '$_POST["import"] * 100')
        ) {
            Assert::fail('Gift checkout must not derive Redsys amount from posted amount.');
        }
    }

    public function testGiftMerchantUrlCarriesNoUnsignedPiiOrAmount(): void
    {
        $source = $this->read(
            'codi-drive/pay-prisma-cat-canvis-verifactu/pagina_efectuar_pagament_regal_automatic.php'
        );

        foreach (['?codiCurs=', '&codiRegal=', '&dni=', '&import='] as $forbidden) {
            if (str_contains($source, $forbidden)) {
                Assert::fail('Gift MerchantURL contains unsigned functional context: ' . $forbidden);
            }
        }

        if (str_contains($source, 'respostaOkPagamentRegal.php?email=')
            || str_contains($source, 'respostaKoPagamentRegal.php?email=')
        ) {
            Assert::fail('Gift browser return URL must not expose buyer email.');
        }
    }

    public function testGiftInternalClientRequiresHttpsAndTlsVerification(): void
    {
        $source = $this->read(
            'codi-drive/pay-prisma-cat-canvis-verifactu/SifRedsysGiftIntentClient.php'
        );

        Assert::stringContainsString("str_starts_with($baseUrl, 'https://')", $source);
        Assert::stringContainsString('CURLOPT_SSL_VERIFYPEER => true', $source);
        Assert::stringContainsString('CURLOPT_SSL_VERIFYHOST => 2', $source);
        Assert::stringContainsString('/api/redsys/gift-intent.php', $source);
    }

    public function testGiftLegacyCallbackFailsClosedAfterCutoverDrain(): void
    {
        $source = $this->read(
            'codi-drive/pay-prisma-cat-canvis-verifactu/realitzaPagamentRegalAutomatic.php'
        );

        Assert::stringContainsString('SIF_REDSYS_GIFT_CUTOVER_ENABLED', $source);
        Assert::stringContainsString('SIF_REDSYS_GIFT_LEGACY_DRAIN_CONFIRMED', $source);
        Assert::stringContainsString('if ($giftCutoverEnabled && $legacyDrainConfirmed)', $source);
        Assert::stringContainsString('http_response_code(410)', $source);

        $guard = strpos($source, 'SIF_REDSYS_GIFT_CUTOVER_ENABLED');
        $firstInclude = strpos($source, 'include(');
        Assert::same(true, $guard !== false && $firstInclude !== false && $guard < $firstInclude);
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
