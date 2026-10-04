<?php

final class GiftCheckoutToken
{
    private const TTL_SECONDS = 1800;

    public static function issue(int $giftId): string
    {
        if ($giftId <= 0) {
            throw new RuntimeException('INVALID_GIFT_CHECKOUT_ID');
        }

        $expiresAt = time() + self::TTL_SECONDS;
        $payload = $giftId . '.' . $expiresAt;

        return $payload . '.' . self::signature($payload);
    }

    public static function verify(string $token): int
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 3) {
            throw new RuntimeException('INVALID_GIFT_CHECKOUT_TOKEN');
        }

        [$giftIdRaw, $expiresAtRaw, $signature] = $parts;
        if (!ctype_digit($giftIdRaw)
            || !ctype_digit($expiresAtRaw)
            || (int) $giftIdRaw <= 0
            || $signature === ''
        ) {
            throw new RuntimeException('INVALID_GIFT_CHECKOUT_TOKEN');
        }

        $expiresAt = (int) $expiresAtRaw;
        $now = time();
        if ($expiresAt < $now || $expiresAt > ($now + self::TTL_SECONDS + 60)) {
            throw new RuntimeException('EXPIRED_GIFT_CHECKOUT_TOKEN');
        }

        $payload = $giftIdRaw . '.' . $expiresAtRaw;
        $expected = self::signature($payload);
        if (!hash_equals($expected, $signature)) {
            throw new RuntimeException('INVALID_GIFT_CHECKOUT_TOKEN_SIGNATURE');
        }

        return (int) $giftIdRaw;
    }

    private static function signature(string $payload): string
    {
        $secret = (string) getenv('UC017_GIFT_CHECKOUT_HMAC_SECRET');
        if (strlen($secret) < 32) {
            throw new RuntimeException('UC017_GIFT_CHECKOUT_HMAC_SECRET_NOT_CONFIGURED');
        }

        return rtrim(
            strtr(base64_encode(hash_hmac('sha256', $payload, $secret, true)), '+/', '-_'),
            '='
        );
    }
}
