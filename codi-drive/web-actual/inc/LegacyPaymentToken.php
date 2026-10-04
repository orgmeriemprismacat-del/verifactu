<?php

final class LegacyPaymentToken
{
    public static function decode(string $encoded, string $key): int
    {
        $encoded = trim($encoded);
        if ($encoded === '' || strlen($encoded) > 4096 || $key === '') {
            throw new RuntimeException('Token de pagament no vàlid.', 400);
        }

        $decoded = base64_decode($encoded, true);
        if ($decoded === false) {
            throw new RuntimeException('Token de pagament no vàlid.', 400);
        }

        $cipher = 'AES-128-CBC';
        $ivLength = openssl_cipher_iv_length($cipher);
        $macLength = 32;
        if (strlen($decoded) <= $ivLength + $macLength) {
            throw new RuntimeException('Token de pagament no vàlid.', 400);
        }

        $iv = substr($decoded, 0, $ivLength);
        $receivedMac = substr($decoded, $ivLength, $macLength);
        $ciphertext = substr($decoded, $ivLength + $macLength);
        $calculatedMac = hash_hmac('sha256', $ciphertext, $key, true);

        if (!hash_equals($receivedMac, $calculatedMac)) {
            throw new RuntimeException('Token de pagament no vàlid.', 400);
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            $cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        if ($plaintext === false || !preg_match('/^[1-9][0-9]*$/D', (string) $plaintext)) {
            throw new RuntimeException('Token de pagament no vàlid.', 400);
        }

        return (int) $plaintext;
    }
}
