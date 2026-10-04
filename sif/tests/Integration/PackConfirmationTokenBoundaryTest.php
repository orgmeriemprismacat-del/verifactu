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

        Assert::stringContainsString("\$_COOKIE['uc015_pack_confirmation']", $source);
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
        Assert::stringContainsString("const CIPHER = 'AES-256-CBC'", $source);
        Assert::stringContainsString('const TTL_SECONDS = 86400', $source);
        Assert::stringContainsString(
            'self::DOMAIN . $iv . $ciphertext',
            $source
        );
        Assert::stringContainsString('self::encryptionKey($keyEncr)', $source);
        Assert::stringContainsString('self::macKey($keyEncr)', $source);
        Assert::stringContainsString(
            'UC015_PACK_CONFIRMATION_V2_ENCRYPTION',
            $source
        );
        Assert::stringContainsString(
            'UC015_PACK_CONFIRMATION_V2_MAC',
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
            'ajax/mostrar_pagina_confirmacio_pagament_grup_automatic.php',
            $js
        );
        Assert::same(false, str_contains($js, 'keyEncr'));
        Assert::same(false, str_contains($js, 'window.location.hash'));

        $enrollmentJs = file_get_contents(
            $root
            . '/codi-drive/web-actual/js1619773569/mostrarInscripcioPack.min.js'
        );
        $enrollmentPage = file_get_contents(
            $root . '/codi-drive/web-actual/pagina_inscripcio_pack.php'
        );
        $htaccess = file_get_contents(
            $root . '/codi-drive/web-actual/.htaccess'
        );
        if (!is_string($enrollmentJs)
            || !is_string($enrollmentPage)
            || !is_string($htaccess)
        ) {
            Assert::fail('Could not read PACK fragment routing boundary');
        }

        Assert::stringContainsString(
            'formConfirmacio.method = "POST"',
            $enrollmentJs
        );
        Assert::stringContainsString(
            'formConfirmacio.action = "https://www.prisma.cat/packs/confirmacio/"',
            $enrollmentJs
        );
        Assert::stringContainsString(
            'inputTokenConfirmacio.name = "confirmationToken"',
            $enrollmentJs
        );
        Assert::same(
            false,
            str_contains($enrollmentJs, 'packs/confirmacio/#')
        );
        Assert::stringContainsString(
            'mostrarInscripcioPack.min.js?ver=7.7',
            $enrollmentPage
        );
        Assert::stringContainsString(
            'RewriteRule ^packs/confirmacio/?$ /pagina_confirmacio_grup_automatic.php [L,QSA]',
            $htaccess
        );
        Assert::stringContainsString(
            'mostrarConfirmacioPagamentGrupAutomatic.min.js?ver=2.3',
            $page
        );
        Assert::stringContainsString('Referrer-Policy: no-referrer', $page);
        Assert::stringContainsString('Cache-Control: private, no-store', $page);
        Assert::stringContainsString("'secure' => true", $page);
        Assert::stringContainsString("'httponly' => true", $page);
        Assert::stringContainsString("'samesite' => 'Strict'", $page);
        Assert::stringContainsString(
            "'/ajax/mostrar_pagina_confirmacio_pagament_grup_automatic.php'",
            $page
        );
        Assert::stringContainsString(
            "\$_POST['confirmationToken']",
            $page
        );
        Assert::stringContainsString(
            'uc015FragmentMigration',
            $page
        );
        Assert::stringContainsString(
            "header('Location: https://www.prisma.cat/packs/confirmacio/', true, 303)",
            $page
        );
        Assert::stringContainsString('X-Robots-Tag: noindex', $page);
        Assert::stringContainsString("Content-Security-Policy: frame-ancestors 'none'", $page);
        Assert::stringContainsString('X-Frame-Options: DENY', $page);
        Assert::stringContainsString("'send_page_view': false", $page);

        $migration = strpos($page, 'uc015FragmentMigration');
        $google = strpos($page, 'www.googletagmanager.com');
        $jquery = strpos($page, 'cdnjs.cloudflare.com/ajax/libs/jquery');
        Assert::same(true, $migration !== false);
        Assert::same(true, $google !== false);
        Assert::same(true, $jquery !== false);
        Assert::same(true, $migration < $google);
        Assert::same(true, $migration < $jquery);
    }
}
