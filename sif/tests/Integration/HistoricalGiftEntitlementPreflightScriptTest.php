<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class HistoricalGiftEntitlementPreflightScriptTest
{
    public function testPreflightIsReadOnlyAndBlocksUncoveredUnusedGifts(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents(
            $root . '/scripts/preflight-historical-gift-entitlements.php'
        );
        if (!is_string($source)) {
            Assert::fail('Could not read historical gift entitlement preflight');
        }

        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('HistoricalGiftEntitlementBackfillService', $source);
        Assert::stringContainsString('unused_historical_gifts_covered', $source);
        Assert::stringContainsString('ready_to_backfill_queue_empty', $source);
        Assert::stringContainsString('unused_review_queue_empty', $source);
        Assert::stringContainsString('production_authorized', $source);
        Assert::stringContainsString("'read_only' => true", file_get_contents(
            $root . '/scripts/inventory-historical-gift-entitlements.php'
        ));

        foreach ([
            'issueInvoice(',
            'registerPayment(',
            'backfillOne(',
            'UPDATE regal',
            'INSERT INTO commercial_entitlement',
        ] as $forbidden) {
            Assert::same(false, str_contains($source, $forbidden));
        }
    }

    public function testProcessorRequiresOneGiftAndRefusesProduction(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents(
            $root . '/scripts/process-historical-gift-entitlement-backfill.php'
        );
        if (!is_string($source)) {
            Assert::fail('Could not read historical gift entitlement processor');
        }

        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('--gift-id=', $source);
        Assert::stringContainsString('backfillOne($sifDb, $legacyDb, $giftId)', $source);
        Assert::same(false, str_contains($source, 'foreach ($inventory'));
        Assert::same(false, str_contains($source, 'UPDATE regal'));
    }
}
