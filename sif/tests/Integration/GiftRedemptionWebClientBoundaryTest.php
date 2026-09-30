<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class GiftRedemptionWebClientBoundaryTest
{
    public function testWebClientUsesPostHmacHttpsAndNoGiftCodeInUrl(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents(
            $root . '/codi-drive/web-actual/inc/SifGiftRedemptionClient.php'
        );
        if (!is_string($source)) {
            Assert::fail('Could not read gift redemption web client');
        }

        Assert::stringContainsString("CURLOPT_POST => true", $source);
        Assert::stringContainsString("X-SIF-Signature", $source);
        Assert::stringContainsString("hash('sha256', \$body)", $source);
        Assert::stringContainsString("requires HTTPS", $source);
        Assert::stringContainsString(
            "Gift redemption authority must be resolved inside SIF",
            $source
        );
        Assert::stringContainsString(
            "['holder_party_key', 'trusted_price_snapshot']",
            $source
        );
        Assert::same(false, str_contains($source, '?gift_code='));
        Assert::same(false, str_contains($source, 'http_build_query'));
    }

    public function testLegacyWriterRemainsUnwiredUntilRetryBoundaryIsHardened(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents(
            $root . '/codi-drive/web-actual/ajax/enviarInscripcioBescanvia.php'
        );
        if (!is_string($source)) {
            Assert::fail('Could not read legacy gift enrollment writer');
        }

        Assert::same(
            false,
            str_contains($source, 'SifGiftRedemptionClient')
        );
    }
}
