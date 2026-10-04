<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Tests\Support\Assert;

require_once dirname(__DIR__, 3) . '/codi-drive/web-actual/inc/LegacyPaymentToken.php';

final class LegacyPaymentTokenTest
{
    public function testValidTokenIsAuthenticatedBeforeReturningPositiveIdentifier(): void
    {
        $key = 'uc020-test-key';
        $token = $this->encode(12345, $key);

        Assert::same(12345, \LegacyPaymentToken::decode($token, $key));
    }

    public function testTamperedTokenIsRejected(): void
    {
        $key = 'uc020-test-key';
        $token = $this->encode(12345, $key);
        $raw = base64_decode($token, true);
        if ($raw === false) {
            Assert::fail('Could not decode fixture token.');
        }

        $last = strlen($raw) - 1;
        $raw[$last] = chr(ord($raw[$last]) ^ 1);
        $tampered = base64_encode($raw);

        Assert::throws(\RuntimeException::class, static function () use ($tampered, $key): void {
            \LegacyPaymentToken::decode($tampered, $key);
        }, 400);
    }

    public function testMalformedOrNonPositivePayloadIsRejected(): void
    {
        Assert::throws(\RuntimeException::class, static function (): void {
            \LegacyPaymentToken::decode('not-base64-@@', 'key');
        }, 400);

        $token = $this->encodeRaw('0', 'key');
        Assert::throws(\RuntimeException::class, static function () use ($token): void {
            \LegacyPaymentToken::decode($token, 'key');
        }, 400);
    }

    public function testLegacyEndpointsUseStructuredQueryParameterAndJsEncodesToken(): void
    {
        $root = dirname(__DIR__, 3);
        foreach ([
            'ajax/mostrar_confirmacio_inscripcio_automatic.php',
            'ajax/mostrar_pagina_pagament_automatic.php',
        ] as $relative) {
            $source = file_get_contents($root . '/codi-drive/web-actual/' . $relative);
            if ($source === false) {
                Assert::fail('Could not read token endpoint ' . $relative);
            }

            Assert::stringContainsString("LegacyPaymentToken::decode", $source);
            Assert::stringContainsString("\$_GET['keyEncr']", $source);
            Assert::same(false, str_contains($source, 'REQUEST_URI'));
            Assert::same(false, str_contains($source, 'base64_decode($encr)'));
        }

        foreach ([
            'js1619773569/mostrarConfirmacioInscripcioAutomatic.min.js',
            'js1619773569/mostrarPagamentAutomatic.min.js',
        ] as $relative) {
            $source = file_get_contents($root . '/codi-drive/web-actual/' . $relative);
            if ($source === false) {
                Assert::fail('Could not read token JS ' . $relative);
            }
            Assert::stringContainsString('encodeURIComponent(keyEncr)', $source);
        }
    }

    private function encode(int $id, string $key): string
    {
        return $this->encodeRaw((string) $id, $key);
    }

    private function encodeRaw(string $payload, string $key): string
    {
        $cipher = 'AES-128-CBC';
        $ivLength = openssl_cipher_iv_length($cipher);
        $iv = str_repeat("\x01", $ivLength);
        $ciphertext = openssl_encrypt($payload, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            Assert::fail('Could not encrypt fixture.');
        }

        $mac = hash_hmac('sha256', $ciphertext, $key, true);
        return base64_encode($iv . $mac . $ciphertext);
    }
}
