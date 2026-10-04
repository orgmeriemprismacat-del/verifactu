<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RectificationHttpEndpointTest
{
    public function testEndpointIsSignedScopedAndDisabledByDefault(): void
    {
        $endpoint = file_get_contents(
            dirname(__DIR__, 2) . '/public/api/factures/rectify.php'
        );
        $config = file_get_contents(
            dirname(__DIR__, 2) . '/config/sif.php'
        );

        if ($endpoint === false || $config === false) {
            Assert::fail('Could not read UC-005 endpoint or SIF configuration');
        }

        Assert::stringContainsString('InternalApiAuthenticator', $endpoint);
        Assert::stringContainsString('InternalApiRequestRepository', $endpoint);
        Assert::stringContainsString('InternalRectificationScopeResolver', $endpoint);
        Assert::stringContainsString('FiscalCorrectionDecisionGuard', $endpoint);
        Assert::stringContainsString('FiscalCorrectionDecisionResolver', $endpoint);
        Assert::stringContainsString('FiscalCorrectionDecisionRepository', $endpoint);
        Assert::stringContainsString("classification_event_uuid", $endpoint);
        Assert::stringContainsString('RectificationCommandService', $endpoint);
        Assert::stringContainsString('rectification_signed_path', $endpoint);
        Assert::stringContainsString("['enabled'] ?? false", $endpoint);
        Assert::stringContainsString('UC-005 rectification execution is disabled', $endpoint);
        Assert::stringContainsString('$commands->preview(', $endpoint);
        Assert::stringContainsString('$commands->confirm(', $endpoint);

        Assert::stringContainsString('SIF_UC005_RECTIFICATION_ENABLED', $config);
        Assert::stringContainsString("?: '0'", $config);

        if (str_contains($endpoint, "$payload['classification']")) {
            Assert::fail('UC-005 endpoint must not trust inline fiscal classification from the request body');
        }

        if (str_contains($endpoint, "payload['created_by']")) {
            Assert::fail('UC-005 endpoint must never trust created_by from the request body');
        }

        if (str_contains($endpoint, 'JsonResponse::fromInput()')) {
            Assert::fail('UC-005 signed endpoint must authenticate the exact raw request body');
        }
    }
}
