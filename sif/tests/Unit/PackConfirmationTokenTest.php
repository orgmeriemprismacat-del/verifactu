<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Tests\Support\Assert;

require_once dirname(__DIR__, 3)
    . '/codi-drive/web-actual/inc/PackConfirmationToken.php';

final class PackConfirmationTokenTest
{
    private const KEY = 'uc015-test-key-32-bytes-long-0001';

    public function testRoundTripUsesVersionedUrlSafeToken(): void
    {
        $token = \PackConfirmationToken::encode(12345, self::KEY);

        Assert::matchesRegularExpression('/^v2\.[A-Za-z0-9_-]+$/D', $token);
        Assert::same(false, str_contains($token, '+'));
        Assert::same(false, str_contains($token, '/'));
        Assert::same(false, str_contains($token, '='));
        Assert::same(12345, \PackConfirmationToken::decode($token, self::KEY));
    }

    public function testTamperedIvIsRejectedBeforeItCanChangePlaintext(): void
    {
        $token = \PackConfirmationToken::encode(43210, self::KEY);
        $raw = $this->raw($token);
        $raw[0] = chr(ord($raw[0]) ^ 0x01);

        Assert::throws(
            \RuntimeException::class,
            fn (): int => \PackConfirmationToken::decode(
                $this->token($raw),
                self::KEY
            )
        );
    }

    public function testTamperedCiphertextIsRejected(): void
    {
        $token = \PackConfirmationToken::encode(98765, self::KEY);
        $raw = $this->raw($token);
        $last = strlen($raw) - 1;
        $raw[$last] = chr(ord($raw[$last]) ^ 0x01);

        Assert::throws(
            \RuntimeException::class,
            fn (): int => \PackConfirmationToken::decode(
                $this->token($raw),
                self::KEY
            )
        );
    }

    public function testWrongKeyIsRejected(): void
    {
        $token = \PackConfirmationToken::encode(24680, self::KEY);

        Assert::throws(
            \RuntimeException::class,
            fn (): int => \PackConfirmationToken::decode(
                $token,
                'different-uc015-key'
            )
        );
    }

    public function testLegacyUnversionedTokenIsRejectedFailClosed(): void
    {
        $legacy = base64_encode(random_bytes(64));

        Assert::throws(
            \RuntimeException::class,
            fn (): int => \PackConfirmationToken::decode(
                $legacy,
                self::KEY
            )
        );
    }

    private function raw(string $token): string
    {
        $encoded = substr($token, 3);
        $remainder = strlen($encoded) % 4;
        if ($remainder !== 0) {
            $encoded .= str_repeat('=', 4 - $remainder);
        }

        $raw = base64_decode(strtr($encoded, '-_', '+/'), true);
        if (!is_string($raw)) {
            Assert::fail('Could not decode test confirmation token');
        }

        return $raw;
    }

    private function token(string $raw): string
    {
        return 'v2.' . rtrim(
            strtr(base64_encode($raw), '+/', '-_'),
            '='
        );
    }
}
