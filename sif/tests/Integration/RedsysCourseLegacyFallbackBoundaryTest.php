<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RedsysCourseLegacyFallbackBoundaryTest
{
    public function testCurrentCheckoutUsesServerAuthoritativeFractionalStateAndEscapesPostOutput(): void
    {
        $checkout = $this->read('codi-drive/web-actual/pagina_efectuar_pagament_automatic.php');
        $gate = $this->read('codi-drive/web-actual/inc/JasomNovicePaymentGate.php');

        Assert::stringContainsString('i.FRACCIONAT', $gate);
        Assert::stringContainsString("'fractional' => (int) $fractional", $gate);
        Assert::stringContainsString('if (!$fractional && $requested !== $pending)', $gate);
        Assert::stringContainsString("\$validatedCheckout['fractional'] ? '1' : '0'", $checkout);
        Assert::stringContainsString("REDSYS_MERCHANT_CODE_NOT_CONFIGURED", $checkout);
        Assert::stringContainsString("REDSYS_MERCHANT_KEY_NOT_CONFIGURED", $checkout);
        Assert::stringContainsString("REDSYS_GATEWAY_URL_NOT_CONFIGURED", $checkout);
        Assert::stringContainsString("REDSYS_TERMINAL_NOT_CONFIGURED", $checkout);
        Assert::stringContainsString('DS_MERCHANT_MERCHANTDATA', $checkout);
        Assert::stringContainsString("'UC014I' . (int) \$idPag", $checkout);

        if (str_contains($checkout, 'echo $_POST[')) {
            Assert::fail('UC-014 checkout must not render raw POST values.');
        }
        if (str_contains($checkout, 'Clave recuperada de CANALES')) {
            Assert::fail('UC-014 checkout must not embed Redsys credentials.');
        }
    }

    public function testCandidateIntentBridgeKeepsMoneyAsCanonicalDecimalString(): void
    {
        $checkout = $this->read('codi-drive/pay-prisma-cat-canvis-verifactu/pagina_efectuar_pagament_automatic.php');
        $client = $this->read('codi-drive/pay-prisma-cat-canvis-verifactu/SifRedsysCourseIntentClient.php');

        Assert::stringContainsString("\$importPagare = (string) \$validatedCheckout['payment_amount'];", $checkout);
        Assert::stringContainsString('public function create(int $idPag, string $requestedAmount', $client);
        Assert::stringContainsString("'requested_amount' => \$requestedAmount", $client);

        if (str_contains($checkout, '(float) $importPagare')) {
            Assert::fail('UC-014 candidate bridge must not cast authoritative money to float.');
        }
        if (str_contains($client, 'number_format($requestedAmount')) {
            Assert::fail('UC-014 SIF intent client must not round a float at the HTTP boundary.');
        }
    }

    public function testCheckoutMinimisesPiiSentToRedsys(): void
    {
        foreach ([
            'codi-drive/web-actual/pagina_efectuar_pagament_automatic.php',
            'codi-drive/pay-prisma-cat-canvis-verifactu/pagina_efectuar_pagament_automatic.php',
        ] as $path) {
            $checkout = $this->read($path);

            Assert::stringContainsString(
                "\$producto='Curs ' . \$cursPag . ' | ' . stripslashes(\$titolPag);",
                $checkout
            );
            Assert::stringContainsString(
                'setParameter("DS_MERCHANT_TITULAR",$nomTitularPag)',
                $checkout
            );

            if (str_contains($checkout, '$producto=$dniTitularPag')) {
                Assert::fail('UC-014 product description must not include DNI.');
            }
            if (str_contains($checkout, 'setParameter("DS_MERCHANT_TITULAR",$dniTitularPag)')) {
                Assert::fail('UC-014 Redsys titular must not use DNI as the holder name.');
            }
        }
    }

    public function testCurrentLegacyCallbackValidatesRedsysBeforeFiscalOrNotificationEffects(): void
    {
        $callback = $this->read('codi-drive/web-actual/realitzaPagamentAutomatic.php');

        foreach ([
            'REDSYS_MERCHANT_KEY_NOT_CONFIGURED',
            'INVALID_REDSYS_SIGNATURE',
            'REDSYS_AMOUNT_MISMATCH',
            'REDSYS_CURRENCY_MISMATCH',
            'REDSYS_TERMINAL_MISMATCH',
            'REDSYS_MERCHANT_CODE_MISMATCH',
            'REDSYS_TRANSACTION_TYPE_MISMATCH',
            'INVALID_REDSYS_RESPONSE_CODE',
            'INVALID_REDSYS_MERCHANT_CONTEXT',
            'REDSYS_IDPAG_NOT_UNIQUE_OR_MISSING',
            "getParameter('Ds_MerchantData')",
            'hash_equals',
        ] as $needle) {
            Assert::stringContainsString($needle, $callback);
        }

        $validation = strpos($callback, 'INVALID_REDSYS_SIGNATURE');
        $firstInvoiceInsert = strpos($callback, 'INSERT INTO factures');
        $firstMail = strpos($callback, 'sendMessage()');

        Assert::same(true, $validation !== false);
        Assert::same(true, $firstInvoiceInsert !== false && $validation < $firstInvoiceInsert);
        Assert::same(true, $firstMail === false || $validation < $firstMail);

        if (str_contains($callback, 'Clave recuperada de CANALES')) {
            Assert::fail('UC-014 callback must not embed Redsys credentials.');
        }
        if (str_contains($callback, '$_GET[')) {
            Assert::fail('UC-014 legacy callback must not trust functional context from query string.');
        }
        if (str_contains($callback, 'echo $textDadesComanda')
            || str_contains($callback, 'echo $textManeresPagar')
        ) {
            Assert::fail('UC-014 server callback must not expose business/payment details in HTTP body.');
        }
        if (str_contains($callback, 'intval($codiResposta)>=0')) {
            Assert::fail('UC-014 legacy callback must validate Ds_Response as numeric before authorization.');
        }
    }

    public function testCandidateCallbacksDoNotNotifyBeforeRedsysValidation(): void
    {
        foreach ([
            'codi-drive/pay-prisma-cat-canvis-verifactu/doit.php',
            'codi-drive/pay-prisma-cat-canvis-verifactu/realitzaPagamentAutomatic.php',
        ] as $path) {
            $callback = $this->read($path);
            $validation = strpos($callback, 'INVALID_REDSYS_SIGNATURE');
            $firstMail = strpos($callback, 'sendMessage()');

            Assert::same(true, $validation !== false);
            Assert::same(true, $firstMail === false || $validation < $firstMail);
            Assert::stringContainsString('SIF_REDSYS_COURSE_CUTOVER_ENABLED', $callback);
            Assert::stringContainsString("getParameter('Ds_MerchantData')", $callback);
            Assert::stringContainsString('INVALID_REDSYS_MERCHANT_CONTEXT', $callback);
            Assert::stringContainsString('REDSYS_CURRENCY_MISMATCH', $callback);
            Assert::stringContainsString('REDSYS_TERMINAL_MISMATCH', $callback);
            Assert::stringContainsString('REDSYS_MERCHANT_CODE_MISMATCH', $callback);
            Assert::stringContainsString('REDSYS_TRANSACTION_TYPE_MISMATCH', $callback);
            Assert::stringContainsString('INVALID_REDSYS_RESPONSE_CODE', $callback);
            if (str_contains($callback, '$_GET[')) {
                Assert::fail('Candidate legacy callback must not trust functional context from query string.');
            }
            if (str_contains($callback, 'intval($codiResposta)>=0')) {
                Assert::fail('Candidate callback must validate Ds_Response as numeric before authorization.');
            }
        }
    }

    public function testPaymentPageInitialisesNoviceStateInsteadOfComparingIt(): void
    {
        $source = $this->read('codi-drive/web-actual/PagamentCursAutomatic.php');

        if (str_contains($source, '$recentTitulat == 0;')) {
            Assert::fail('UC-014 payment page leaves recentTitulat uninitialised.');
        }

        Assert::same(2, substr_count($source, '$recentTitulat = 0;'));
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
