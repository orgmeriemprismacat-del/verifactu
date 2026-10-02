<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class GiftRedemptionPreproductionBoundaryTest
{
    public function testPreflightIsReadOnlyFailClosedAndCoversFullUc018Circuit(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents(
            $root . '/scripts/preflight-gift-redemption.php'
        );
        if (!is_string($source)) {
            Assert::fail('Could not read UC-018 gift redemption preflight');
        }

        foreach ([
            "PHP_SAPI !== 'cli'",
            'environment_is_test_or_preproduction',
            'internal_api_secret_strong',
            'gift_manage_roles_configured',
            'gift_redemption_circuit_present',
            'gift_notification_governance_present',
            'gift_concurrency_evidence_present',
            'historical_unused_gifts_covered',
            'notification_outbox_table',
            'notification_delivery_attempt_table',
            "'production_authorized' => false",
            "'read_only' => true",
        ] as $required) {
            Assert::stringContainsString($required, $source);
        }

        foreach ([
            'INSERT INTO ',
            'UPDATE regal',
            'DELETE FROM ',
            'issueInvoice(',
            'registerPayment(',
            '->execute(' . "\n" . '        $sifDb',
        ] as $forbidden) {
            Assert::same(false, str_contains($source, $forbidden));
        }
    }

    public function testVerifierIsDryRunByDefaultAndNeverReadsGiftCodeFromCli(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents(
            $root . '/scripts/verify-gift-redemption-preproduction.php'
        );
        if (!is_string($source)) {
            Assert::fail('Could not read UC-018 gift redemption verifier');
        }

        foreach ([
            "'mode' => \$execute ? 'execute' : 'dry-run'",
            "in_array('--execute'",
            'SIF_GIFT_REDEMPTION_TEST_ENROLLMENT_ID',
            'SIF_GIFT_REDEMPTION_TEST_CODE',
            "'gift_code_on_cli_forbidden' => true",
            "'smtp_executed' => false",
            'GiftRedemptionOrchestrator',
            'GiftRedemptionNotificationBundleService',
            'notification_bundle_has_six_messages',
            'notificationMap',
            'notification_bundle_hash',
            'replay_same_operation',
            'one_compensation_allocation',
            'one_consume_event',
            'no_new_invoice',
            'no_new_charge',
        ] as $required) {
            Assert::stringContainsString($required, $source);
        }

        Assert::same(false, str_contains($source, 'MailSMTPComvive'));
        Assert::same(false, str_contains($source, 'claimNotificationBundle'));
        Assert::same(false, str_contains($source, 'completeNotificationBundle'));
        Assert::same(false, str_contains($source, '$argv[2]'));
        Assert::same(false, str_contains($source, '--gift-code='));
    }

    public function testGoNoGoReferencesGiftRedemptionPreflightVerifierAndCircuit(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents(
            $root . '/scripts/go-no-go-preproduction.php'
        );
        if (!is_string($source)) {
            Assert::fail('Could not read go/no-go preproduction script');
        }

        foreach ([
            'gift_redemption_preflight_present',
            'preflight-gift-redemption.php',
            'gift_redemption_verifier_present',
            'verify-gift-redemption-preproduction.php',
            'gift_redemption_circuit_present',
            'gift_redemption_concurrency_evidence_present',
            'GiftRedemptionOrchestrator.php',
            'GiftRedemptionNotificationBundleService.php',
            'NotificationOutboxDeliveryService.php',
        ] as $required) {
            Assert::stringContainsString($required, $source);
        }
    }
}
