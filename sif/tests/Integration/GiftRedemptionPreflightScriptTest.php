<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class GiftRedemptionPreflightScriptTest
{
    public function testPreflightChecksSecurityCircuitAndHistoricalCoverageWithoutMutating(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents($root . '/scripts/preflight-gift-redemption.php');
        if (!is_string($source)) {
            Assert::fail('Could not read UC-018 gift redemption preflight');
        }

        Assert::stringContainsString('internal_api_key_id_configured', $source);
        Assert::stringContainsString('internal_api_secret_strong', $source);
        Assert::stringContainsString('internal_api_clock_skew_valid', $source);
        Assert::stringContainsString('gift_redemption_signed_path_exact', $source);
        Assert::stringContainsString('gift_redemption_manage_roles_configured', $source);
        Assert::stringContainsString('gift_redemption_endpoint_present', $source);
        Assert::stringContainsString('gift_redemption_orchestrator_present', $source);
        Assert::stringContainsString('gift_redemption_recovery_cli_present', $source);
        Assert::stringContainsString('web_client_present', $source);
        Assert::stringContainsString('legacy_writer_present', $source);
        Assert::stringContainsString('commercial_entitlement_table', $source);
        Assert::stringContainsString('enrollment_fund_movement_table', $source);
        Assert::stringContainsString('internal_api_request_table', $source);
        Assert::stringContainsString('legacy_regal_table', $source);
        Assert::stringContainsString('legacy_inscripcions_table', $source);
        Assert::stringContainsString('HistoricalGiftEntitlementBackfillService', $source);
        Assert::stringContainsString('historical_unused_gifts_covered', $source);
        Assert::stringContainsString('production_authorized', $source);
        Assert::stringContainsString("'NO-GO'", $source);
        Assert::stringContainsString("'GO'", $source);

        foreach ([
            'backfillOne(',
            'issueInvoice(',
            'registerPayment(',
            'redeem(',
            'INSERT INTO ',
            'UPDATE ',
            'DELETE FROM ',
        ] as $forbidden) {
            if (str_contains($source, $forbidden)) {
                Assert::fail(
                    'UC-018 preflight must be read-only: ' . $forbidden
                );
            }
        }
    }
}
