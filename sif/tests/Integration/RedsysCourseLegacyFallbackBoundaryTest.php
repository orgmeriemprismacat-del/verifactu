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

    public function testCurrentLegacyCallbackValidatesRedsysBeforeFiscalOrNotificationEffects(): void
    {
        $callback = $this->read('codi-drive/web-actual/realitzaPagamentAutomatic.php');

        foreach ([
            'REDSYS_MERCHANT_KEY_NOT_CONFIGURED',
            'INVALID_REDSYS_SIGNATURE',
            'REDSYS_AMOUNT_MISMATCH',
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
            if (str_contains($callback, '$_GET[')) {
                Assert::fail('Candidate legacy callback must not trust functional context from query string.');
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
