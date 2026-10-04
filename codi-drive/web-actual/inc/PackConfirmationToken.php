<?php

final class PackConfirmationToken
{
    const PREFIX = 'v2.';
    const CIPHER = 'AES-256-CBC';
    const MAC_BYTES = 32;
    const TTL_SECONDS = 86400;
    const CLOCK_SKEW_SECONDS = 300;
    const DOMAIN = "UC015_PACK_CONFIRMATION_V2\0";

    public static function encode($idInscripcio, $keyEncr, $issuedAt = null)
    {
        $idInscripcio = filter_var(
            $idInscripcio,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $keyEncr = (string) $keyEncr;

        if ($idInscripcio === false || $keyEncr === '') {
            throw new RuntimeException('No es pot generar el token de confirmació PACK');
        }

        $issuedAt = $issuedAt === null ? time() : (int) $issuedAt;
        if ($issuedAt <= 0) {
            throw new RuntimeException('Data del token de confirmació PACK invàlida');
        }
        $plain = (string) $idInscripcio . '|' . (string) $issuedAt;

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        if (!is_int($ivLength) || $ivLength <= 0) {
            throw new RuntimeException('Xifrat de confirmació PACK no disponible');
        }

        $iv = random_bytes($ivLength);
        $ciphertext = openssl_encrypt(
            $plain,
            self::CIPHER,
            self::encryptionKey($keyEncr),
            OPENSSL_RAW_DATA,
            $iv
        );
        if (!is_string($ciphertext) || $ciphertext === '') {
            throw new RuntimeException('No es pot xifrar la confirmació PACK');
        }

        $mac = hash_hmac(
            'sha256',
            self::DOMAIN . $iv . $ciphertext,
            self::macKey($keyEncr),
            true
        );

        return self::PREFIX . self::base64UrlEncode($iv . $mac . $ciphertext);
    }

    public static function decode($token, $keyEncr, $now = null)
    {
        $token = trim((string) $token);
        $keyEncr = (string) $keyEncr;

        if ($keyEncr === ''
            || strpos($token, self::PREFIX) !== 0
            || strlen($token) > 2048
        ) {
            throw new RuntimeException('Token de confirmació PACK invàlid');
        }

        $raw = self::base64UrlDecode(substr($token, strlen(self::PREFIX)));
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        if (!is_int($ivLength) || $ivLength <= 0) {
            throw new RuntimeException('Xifrat de confirmació PACK no disponible');
        }

        $minimumLength = $ivLength + self::MAC_BYTES + 1;
        if (strlen($raw) < $minimumLength) {
            throw new RuntimeException('Token de confirmació PACK incomplet');
        }

        $iv = substr($raw, 0, $ivLength);
        $mac = substr($raw, $ivLength, self::MAC_BYTES);
        $ciphertext = substr($raw, $ivLength + self::MAC_BYTES);

        $calculatedMac = hash_hmac(
            'sha256',
            self::DOMAIN . $iv . $ciphertext,
            self::macKey($keyEncr),
            true
        );

        if (!hash_equals($mac, $calculatedMac)) {
            throw new RuntimeException('Integritat del token de confirmació PACK no vàlida');
        }

        $plain = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            self::encryptionKey($keyEncr),
            OPENSSL_RAW_DATA,
            $iv
        );
        if (!is_string($plain)
            || preg_match('/^([1-9][0-9]*)\\|([1-9][0-9]*)$/D', $plain, $matches) !== 1
        ) {
            throw new RuntimeException('Payload de confirmació PACK invàlid');
        }

        $idInscripcio = filter_var(
            $matches[1],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $issuedAt = filter_var(
            $matches[2],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($idInscripcio === false || $issuedAt === false) {
            throw new RuntimeException('Identitat de confirmació PACK fora de rang');
        }

        $now = $now === null ? time() : (int) $now;
        if ($now <= 0
            || $issuedAt > $now + self::CLOCK_SKEW_SECONDS
            || $now - $issuedAt > self::TTL_SECONDS
        ) {
            throw new RuntimeException('Token de confirmació PACK caducat o amb data invàlida');
        }

        return (int) $idInscripcio;
    }

    private static function encryptionKey($masterKey)
    {
        return hash_hmac(
            'sha256',
            "UC015_PACK_CONFIRMATION_V2_ENCRYPTION\0",
            (string) $masterKey,
            true
        );
    }

    private static function macKey($masterKey)
    {
        return hash_hmac(
            'sha256',
            "UC015_PACK_CONFIRMATION_V2_MAC\0",
            (string) $masterKey,
            true
        );
    }

    private static function base64UrlEncode($raw)
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function base64UrlDecode($encoded)
    {
        $encoded = (string) $encoded;
        if ($encoded === ''
            || preg_match('/^[A-Za-z0-9_-]+$/D', $encoded) !== 1
        ) {
            throw new RuntimeException('Codificació del token de confirmació PACK invàlida');
        }

        $remainder = strlen($encoded) % 4;
        if ($remainder !== 0) {
            $encoded .= str_repeat('=', 4 - $remainder);
        }

        $raw = base64_decode(strtr($encoded, '-_', '+/'), true);
        if (!is_string($raw)) {
            throw new RuntimeException('No es pot descodificar el token de confirmació PACK');
        }

        return $raw;
    }
}
