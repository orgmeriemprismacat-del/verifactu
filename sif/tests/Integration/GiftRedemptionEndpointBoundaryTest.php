<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class GiftRedemptionEndpointBoundaryTest
{
    public function testGiftRedemptionEndpointIsPostHmacRoleGuardedAndUsesTrustedServices(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents(
            $root . '/public/api/gifts/redemption/redeem.php'
        );
        if (!is_string($source)) {
            Assert::fail('Could not read gift redemption endpoint');
        }

        Assert::stringContainsString(
            '$_SERVER[\'REQUEST_METHOD\']',
            $source
        );
        Assert::stringContainsString(
            "!== 'POST'",
            $source
        );
        Assert::stringContainsString(
            'InternalApiAuthenticator',
            $source
        );
        Assert::stringContainsString(
            'gift_redemption_signed_path',
            $source
        );
        Assert::stringContainsString(
            'assertGiftRedemptionRole',
            $source
        );
        Assert::stringContainsString(
            'GiftEnrollmentStager',
            $source
        );
        Assert::stringContainsString(
            'GiftRedemptionService',
            $source
        );
        Assert::stringContainsString(
            'trusted_price_snapshot',
            $source
        );
        Assert::same(false, str_contains($source, '$_GET'));
        Assert::same(false, str_contains($source, '?gift_code='));
    }

    public function testGiftRedemptionConfigurationFailsClosedWithoutExplicitRoles(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents($root . '/config/sif.php');
        if (!is_string($source)) {
            Assert::fail('Could not read SIF config');
        }

        Assert::stringContainsString(
            'SIF_INTERNAL_GIFT_REDEMPTION_SIGNED_PATH',
            $source
        );
        Assert::stringContainsString(
            'SIF_GIFT_REDEMPTION_MANAGE_ROLES',
            $source
        );
        Assert::stringContainsString(
            "'gift_redemption' => [",
            $source
        );
    }

    public function testInternalApiReplayLedgerStoresBodyHashNotRequestBody(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents(
            $root . '/src/Repository/InternalApiRequestRepository.php'
        );
        if (!is_string($source)) {
            Assert::fail('Could not read internal API request repository');
        }

        Assert::stringContainsString('BODY_HASH', $source);
        Assert::same(false, str_contains($source, 'REQUEST_BODY'));
        Assert::same(false, str_contains($source, 'RAW_BODY'));
    }
}
