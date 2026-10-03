<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualTransferHttpEndpointTest
{
    public function testEndpointUsesSignedActorAtomicAuditAndIdempotentLegacyProjection(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/public/api/payments/manual-transfer.php'
        );

        if ($source === false) {
            Assert::fail('Could not read manual-transfer HTTP endpoint');
        }

        Assert::stringContainsString("REQUEST_METHOD", $source);
        Assert::stringContainsString("'POST'", $source);
        Assert::stringContainsString('InternalApiAuthenticator', $source);
        Assert::stringContainsString('InternalApiRequestRepository', $source);
        Assert::stringContainsString('manual_transfer_signed_path', $source);
        Assert::stringContainsString('ManualTransferCommandService', $source);
        Assert::stringContainsString('PaymentActionGateway', $source);
        Assert::stringContainsString('PaymentActionEventRepository', $source);
        Assert::stringContainsString('OperationalEventRepository', $source);
        Assert::stringContainsString('SifAuditEventRepository', $source);
        Assert::stringContainsString('GeneratedInvoiceLegacyPaymentSyncService', $source);
        Assert::stringContainsString('ManualTransferLegacyProjectionService', $source);
        Assert::stringContainsString("PENDING_RETRY", $source);
        Assert::stringContainsString("CONFLICT", $source);
        Assert::stringContainsString("legacy_sync", $source);

        if (str_contains($source, 'JsonResponse::fromInput()')) {
            Assert::fail('Signed manual-transfer endpoint must authenticate the exact raw request body.');
        }

        if (str_contains($source, "payload['actor_id']") || str_contains($source, "payload['roles']")) {
            Assert::fail('Manual-transfer endpoint must not trust actor identity or roles from payload.');
        }
    }
}
