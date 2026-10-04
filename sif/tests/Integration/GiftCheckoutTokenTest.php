<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class GiftCheckoutTokenTest
{
    public function testSignedGiftCheckoutTokenRoundTripsAndRejectsTampering(): void
    {
        require_once dirname(__DIR__, 3)
            . '/codi-drive/pay-prisma-cat-canvis-verifactu/GiftCheckoutToken.php';

        $previous = getenv('UC017_GIFT_CHECKOUT_HMAC_SECRET');
        putenv('UC017_GIFT_CHECKOUT_HMAC_SECRET=' . str_repeat('a', 64));

        try {
            $token = \GiftCheckoutToken::issue(77);
            Assert::same(77, \GiftCheckoutToken::verify($token));

            $tampered = preg_replace('/^77\\./', '78.', $token);
            Assert::same(true, is_string($tampered) && $tampered !== $token);
            Assert::throws(\RuntimeException::class, static function () use ($tampered): void {
                \GiftCheckoutToken::verify((string) $tampered);
            });
        } finally {
            if ($previous === false) {
                putenv('UC017_GIFT_CHECKOUT_HMAC_SECRET');
            } else {
                putenv('UC017_GIFT_CHECKOUT_HMAC_SECRET=' . $previous);
            }
        }
    }

    public function testGiftCheckoutTokenFailsClosedWithoutDedicatedSecret(): void
    {
        require_once dirname(__DIR__, 3)
            . '/codi-drive/pay-prisma-cat-canvis-verifactu/GiftCheckoutToken.php';

        $previous = getenv('UC017_GIFT_CHECKOUT_HMAC_SECRET');
        putenv('UC017_GIFT_CHECKOUT_HMAC_SECRET');

        try {
            Assert::throws(\RuntimeException::class, static function (): void {
                \GiftCheckoutToken::issue(77);
            });
        } finally {
            if ($previous !== false) {
                putenv('UC017_GIFT_CHECKOUT_HMAC_SECRET=' . $previous);
            }
        }
    }
}
