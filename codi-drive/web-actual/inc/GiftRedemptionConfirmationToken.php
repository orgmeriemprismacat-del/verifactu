<?php

declare(strict_types=1);

/**
 * Versioned, authenticated token for the public UC-018 confirmation page.
 *
 * The token contains only the committed legacy enrollment ID. It uses
 * AES-256-GCM with a key derived from the existing server-side key material.
 * The URL representation is base64url and is intended to travel in the URL
 * fragment, so it is not sent in HTTP requests or Referer headers.
 */
final class GiftRedemptionConfirmationToken
{
    private const PREFIX = 'v2.';
    private const CIPHER = 'aes-256-gcm';
    private const AAD = 'UC018_CONFIRMATION_V2';
    private const NONCE_LENGTH = 12;
    private const TAG_LENGTH = 16;

    public static function issue(int $enrollmentId, string $keyMaterial): string
    {
        if ($enrollmentId <= 0) {
            throw new InvalidArgumentException('Invalid gift confirmation enrollment ID');
        }

        $key = self::key($keyMaterial);
        $nonce = random_bytes(self::NONCE_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            (string) $enrollmentId,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            self::AAD,
            self::TAG_LENGTH
        );

        if (!is_string($ciphertext) || strlen($tag) !== self::TAG_LENGTH) {
            throw new RuntimeException('Could not create gift confirmation token');
        }

        return self::PREFIX . self::base64UrlEncode($nonce . $tag . $ciphertext);
    }

    public static function parse(string $token, string $keyMaterial): int
    {
        $token = trim($token);
        if (!str_starts_with($token, self::PREFIX)) {
            throw new RuntimeException('Invalid gift confirmation token');
        }

        $raw = self::base64UrlDecode(substr($token, strlen(self::PREFIX)));
        if (strlen($raw) <= self::NONCE_LENGTH + self::TAG_LENGTH) {
            throw new RuntimeException('Invalid gift confirmation token');
        }

        $nonce = substr($raw, 0, self::NONCE_LENGTH);
        $tag = substr($raw, self::NONCE_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($raw, self::NONCE_LENGTH + self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            self::key($keyMaterial),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            self::AAD
        );

        if (!is_string($plaintext)
            || $plaintext === ''
            || ctype_digit($plaintext) === false
            || (int) $plaintext <= 0
        ) {
            throw new RuntimeException('Invalid gift confirmation token');
        }

        return (int) $plaintext;
    }

    private static function key(string $keyMaterial): string
    {
        if (trim($keyMaterial) === '') {
            throw new RuntimeException('Gift confirmation key is not configured');
        }

        return hash_hmac('sha256', self::AAD, $keyMaterial, true);
    }

    private static function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $encoded): string
    {
        if ($encoded === '' || preg_match('/^[A-Za-z0-9_-]+$/D', $encoded) !== 1) {
            throw new RuntimeException('Invalid gift confirmation token');
        }

        $padding = strlen($encoded) % 4;
        if ($padding !== 0) {
            $encoded .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
        if (!is_string($decoded)) {
            throw new RuntimeException('Invalid gift confirmation token');
        }

        return $decoded;
    }
}
