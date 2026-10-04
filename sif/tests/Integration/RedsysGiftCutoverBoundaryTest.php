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
        Assert::stringContainsString('$urlPag = $sifMerchantUrl;', $source);
        Assert::stringContainsString('$urlPag = $legacyMerchantUrl;', $source);
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
        Assert::stringContainsString("\$importPag = (string) \$intent['amount'];", $source);

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

        if (preg_match('/<\?php\s+echo\s+\$_POST\[/i', $source) === 1) {
            Assert::fail('Gift checkout must not echo a raw POST value into HTML.');
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

    public function testGiftLegacyFallbackValidatesSignedContextBeforeEconomicEffects(): void
    {
        $source = $this->read(
            'codi-drive/pay-prisma-cat-canvis-verifactu/realitzaPagamentRegalAutomatic.php'
        );

        Assert::stringContainsString('createMerchantSignatureNotifForVersion', $source);
        Assert::stringContainsString('hash_equals(', $source);
        Assert::stringContainsString('INVALID_REDSYS_SIGNATURE', $source);
        Assert::stringContainsString('INVALID_REDSYS_MERCHANT_CONTEXT', $source);
        Assert::stringContainsString('REDSYS_AMOUNT_MISMATCH', $source);
        Assert::stringContainsString('REDSYS_CURRENCY_MISMATCH', $source);
        Assert::stringContainsString('REDSYS_TERMINAL_MISMATCH', $source);
        Assert::stringContainsString('REDSYS_MERCHANT_CODE_MISMATCH', $source);
        Assert::stringContainsString('REDSYS_TRANSACTION_TYPE_MISMATCH', $source);
        Assert::stringContainsString("getenv('REDSYS_MERCHANT_KEY')", $source);
        Assert::stringContainsString("SELECT CODI, NOM_CURS", $source);
        Assert::stringContainsString('FROM regal WHERE ID=?', $source);

        foreach ([
            "\$_GET['order']",
            "\$_GET['codiCurs']",
            "\$_GET['codiRegal']",
            "\$_GET['dni']",
            "\$_GET['import']",
        ] as $forbidden) {
            if (str_contains($source, $forbidden)) {
                Assert::fail('Gift legacy fallback still trusts unsigned query context: ' . $forbidden);
            }
        }

        if (preg_match('/\$kc\s*=\s*[\'"][A-Za-z0-9+\/=]{16,}[\'"]/', $source) === 1) {
            Assert::fail('Gift legacy callback still embeds a Redsys key.');
        }

        $signatureCheck = strpos($source, 'hash_equals(');
        $invoiceInsert = strpos($source, 'INSERT INTO factures');
        Assert::same(true, $signatureCheck !== false && $invoiceInsert !== false);
        Assert::same(true, $signatureCheck < $invoiceInsert);
    }

    public function testGiftInternalClientRequiresHttpsAndTlsVerification(): void
    {
        $source = $this->read(
            'codi-drive/pay-prisma-cat-canvis-verifactu/SifRedsysGiftIntentClient.php'
        );

        Assert::stringContainsString("str_starts_with(\$baseUrl, 'https://')", $source);
        Assert::stringContainsString('CURLOPT_SSL_VERIFYPEER => true', $source);
        Assert::stringContainsString('CURLOPT_SSL_VERIFYHOST => 2', $source);
        Assert::stringContainsString('/api/redsys/gift-intent.php', $source);
    }

    public function testGiftBrowserReturnUsesAuthoritativeSifStatus(): void
    {
        $helper = $this->read(
            'codi-drive/pay-prisma-cat-canvis-verifactu/GiftPaymentReturnStatus.php'
        );
        $client = $this->read(
            'codi-drive/pay-prisma-cat-canvis-verifactu/SifRedsysGiftStatusClient.php'
        );
        $ok = $this->read(
            'codi-drive/pay-prisma-cat-canvis-verifactu/respostaOkPagamentRegal.php'
        );

        Assert::stringContainsString('SifRedsysGiftStatusClient', $helper);
        Assert::stringContainsString("status === 'CONFIRMED'", $helper);
        Assert::stringContainsString('encara no està confirmat pel sistema autoritatiu', $helper);
        Assert::stringContainsString('/api/redsys/gift-status.php', $client);
        Assert::stringContainsString("str_starts_with(\$baseUrl, 'https://')", $client);
        Assert::stringContainsString("uc017RenderPaymentReturn('OK')", $ok);

        if (str_contains($ok, "\$_GET['email']")) {
            Assert::fail('Gift success return must not trust or render buyer email from query string.');
        }
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

    public function testGiftPaymentFormPostsSignedCheckoutTokenWithoutMutableGiftContext(): void
    {
        $source = $this->read(
            'codi-drive/pay-prisma-cat-canvis-verifactu/PagamentRegalAutomatic.php'
        );
        $checkout = $this->read(
            'codi-drive/pay-prisma-cat-canvis-verifactu/pagina_efectuar_pagament_regal_automatic.php'
        );

        Assert::stringContainsString('GiftCheckoutToken::issue($giftId)', $source);
        Assert::stringContainsString("id='giftToken' name='giftToken'", $source);
        Assert::stringContainsString('GiftCheckoutToken::verify(', $checkout);

        foreach ([
            "name='giftId'",
            "name='codiRegal'",
            "name='email'",
            "name='import'",
        ] as $forbidden) {
            if (str_contains($source, $forbidden)) {
                Assert::fail('Gift payment form still posts mutable legacy payment context: ' . $forbidden);
            }
        }
    }

    public function testGiftCheckoutJavascriptDoesNotSendPiiBeforeRedsys(): void
    {
        $js = $this->read(
            'codi-drive/pay-prisma-cat-canvis-verifactu/js/mostrarEfectuarPagamentRegal.min.js'
        );
        $retired = $this->read(
            'codi-drive/pay-prisma-cat-canvis-verifactu/ajax/efectuarPagamentRegalAutomatic.php'
        );

        Assert::stringContainsString("form.submit();", $js);
        Assert::stringContainsString("var submitting = false;", $js);
        Assert::stringContainsString("http_response_code(410)", $retired);
        Assert::stringContainsString("Endpoint de preparació llegat retirat", $retired);

        foreach (['codiRegal=', 'dni=', 'nomTit=', 'import=', 'efectuarPagamentRegalAutomatic.php?'] as $forbidden) {
            if (str_contains($js, $forbidden)) {
                Assert::fail('Gift checkout JS leaks legacy payment context: ' . $forbidden);
            }
        }

        if (str_contains($retired, '$_GET[') || str_contains($retired, 'new Mail(')) {
            Assert::fail('Retired gift preparation endpoint must not consume GET data or send mail.');
        }
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
