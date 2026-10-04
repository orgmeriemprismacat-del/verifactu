<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualTransferPreproductionPreflightTest
{
    public function testPreflightRequiresUc022RuntimeAndBothLegacyConnections(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/scripts/preflight-uc022-manual-transfer.php'
        );

        if ($source === false) {
            Assert::fail('Could not read UC-022 preproduction preflight');
        }

        foreach ([
            'payment_action_event',
            'operational_event',
            'sif_audit_event',
            'internal_api_request',
            'notification_outbox',
            'manual_transfer_signed_path',
            'manual_transfer_roles',
            'legacy_database_connectivity',
            'legacy_intranet_database_connectivity',
            'ManualTransferCommandService',
            'ManualTransferLegacyProjectionService',
            'ManualTransferNotificationService',
            'SIF_INTERNAL_API_SECRET',
            'SIF_MANUAL_TRANSFER_ROLES',
        ] as $needle) {
            Assert::stringContainsString($needle, $source);
        }
    }

    public function testEvidenceVerifierIsNonMutatingAndHashesBankIdentity(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/scripts/verify-uc022-preproduction.php'
        );

        if ($source === false) {
            Assert::fail('Could not read UC-022 preproduction verifier');
        }

        Assert::stringContainsString('UC022_VERIFY_BANK_EVENT_ID', $source);
        Assert::stringContainsString('BANK_EVENT_SHA256', $source);
        Assert::stringContainsString('payment_action_event', $source);
        Assert::stringContainsString('legacy_sync_terminal', $source);
        Assert::stringContainsString('num_visible_sha256', $source);
        Assert::stringContainsString('bank_event_sha256', $source);

        foreach (['INSERT ', 'UPDATE ', 'DELETE ', 'REPLACE '] as $mutation) {
            if (str_contains(strtoupper($source), $mutation)) {
                Assert::fail('UC-022 evidence verifier must remain read-only: ' . trim($mutation));
            }
        }
    }
}
