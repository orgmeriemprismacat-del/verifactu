<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualTransferIntranetPreflightTest
{
    public function testIntranetPreflightChecksSecureUc022Configuration(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3)
            . '/codi-drive/intranet-nova-canvis-verifactu/scripts/preflight-uc022-intranet.php'
        );

        if ($source === false) {
            Assert::fail('Could not read UC-022 intranet preflight');
        }

        foreach ([
            'SIF_INTERNAL_API_BASE_URL',
            'SIF_INTERNAL_API_KEY_ID',
            'SIF_INTERNAL_API_SECRET',
            'SIF_INTERNAL_MANUAL_TRANSFER_SIGNED_PATH',
            'SIF_MANUAL_TRANSFER_ROLES',
            'base_url_https',
            'secret_sha256',
            'SifPaymentSessionGuard.php',
            'SifInternalApiClient.php',
            'SifManualTransferGateway.php',
            'registrarTransferenciaSif.php',
        ] as $needle) {
            Assert::stringContainsString($needle, $source);
        }

        Assert::stringContainsString("'/api/payments/manual-transfer.php'", $source);
        Assert::stringContainsString("strlen($secret) >= 32", $source);
    }
}
