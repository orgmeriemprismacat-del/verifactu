<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class PackConfirmationTokenBoundaryTest
{
    public function testEnrollmentEndpointUsesVersionedConfirmationTokenHelper(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents(
            $root . '/codi-drive/web-actual/ajax/enviarInscripcioPack.php'
        );
        if (!is_string($source)) {
            Assert::fail('Could not read PACK enrollment endpoint');
        }

        Assert::stringContainsString(
            "require_once __DIR__ . '/../inc/PackConfirmationToken.php'",
            $source
        );
        Assert::stringContainsString(
            'return PackConfirmationToken::encode($idInscripcio, $keyEncr);',
            $source
        );
        Assert::same(
            false,
            str_contains($source, "hash_hmac('sha256', \$ciphertextRaw")
        );
    }

    public function testConfirmationConsumerReadsParsedQueryAndDelegatesCrypto(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents(
            $root
            . '/codi-drive/web-actual/ajax/mostrar_pagina_confirmacio_pagament_grup_automatic.php'
        );
        if (!is_string($source)) {
            Assert::fail('Could not read PACK confirmation consumer');
        }

        Assert::stringContainsString("\$_GET['keyEncr']", $source);
        Assert::stringContainsString(
            'PackConfirmationToken::decode($encr, $keyEncr)',
            $source
        );
        Assert::stringContainsString('Cache-Control: private, no-store', $source);
        Assert::stringContainsString('Referrer-Policy: no-referrer', $source);

        Assert::same(false, str_contains($source, 'REQUEST_URI'));
        Assert::same(false, str_contains($source, 'openssl_decrypt('));
        Assert::same(false, str_contains($source, 'hash_hmac('));
        Assert::same(false, str_contains($source, 'substr(explode('));
    }

    public function testTokenHelperAuthenticatesIvAndCiphertextBeforeDecrypting(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents(
            $root . '/codi-drive/web-actual/inc/PackConfirmationToken.php'
        );
        if (!is_string($source)) {
            Assert::fail('Could not read PACK confirmation token helper');
        }

        Assert::stringContainsString("const PREFIX = 'v2.'", $source);
        Assert::stringContainsString(
            'self::DOMAIN . $iv . $ciphertext',
            $source
        );
        Assert::stringContainsString('hash_equals($mac, $calculatedMac)', $source);

        $decode = strpos($source, 'public static function decode');
        Assert::same(true, $decode !== false);
        $decodeSource = substr($source, (int) $decode);
        $verify = strpos($decodeSource, 'hash_equals($mac, $calculatedMac)');
        $decrypt = strpos($decodeSource, 'openssl_decrypt(');

        Assert::same(true, $verify !== false);
        Assert::same(true, $decrypt !== false);
        Assert::same(true, $verify < $decrypt);
    }

    public function testBrowserEncodesQueryTokenAndBumpsAssetVersion(): void
    {
        $root = dirname(__DIR__, 3);
        $js = file_get_contents(
            $root
            . '/codi-drive/web-actual/js1619773569/mostrarConfirmacioPagamentGrupAutomatic.min.js'
        );
        $page = file_get_contents(
            $root . '/codi-drive/web-actual/pagina_confirmacio_grup_automatic.php'
        );
        if (!is_string($js) || !is_string($page)) {
            Assert::fail('Could not read PACK confirmation browser boundary');
        }

        Assert::stringContainsString(
            '?keyEncr=" + encodeURIComponent(keyEncr)',
            $js
        );
        Assert::stringContainsString(
            'mostrarConfirmacioPagamentGrupAutomatic.min.js?ver=2.1',
            $page
        );
    }
}
