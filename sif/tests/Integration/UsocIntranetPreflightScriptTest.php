<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class UsocIntranetPreflightScriptTest
{
    public function testPreflightRequiresCoreTablesServicesApiAndLegacyConnectivity(): void
    {
        $root = dirname(__DIR__, 3);
        $script = file_get_contents($root . '/sif/scripts/preflight-usoc-intranet.php');

        if ($script === false) {
            Assert::fail('Could not read USOC intranet preflight script');
        }

        Assert::stringContainsString('usoc_financing_case_table', $script);
        Assert::stringContainsString('usoc_validation_decision_table', $script);
        Assert::stringContainsString('legacy_database_configured', $script);
        Assert::stringContainsString('legacy_database_connectivity', $script);
        Assert::stringContainsString("ConnectionFactory::makeLegacy", $script);
        Assert::stringContainsString("SELECT 1", $script);

        Assert::stringContainsString('usoc_lifecycle_plan_service', $script);
        Assert::stringContainsString('UsocLifecyclePlanService::class', $script);
        Assert::stringContainsString('usoc_validation_decision_service', $script);
        Assert::stringContainsString('UsocValidationDecisionService::class', $script);

        Assert::stringContainsString('usoc_api_endpoint_file', $script);
        Assert::stringContainsString('/public/api/usoc/manage.php', $script);

        Assert::stringContainsString('SIF_INTERNAL_USOC_URL', $script);
        Assert::stringContainsString('SIF_USOC_UI_ENABLED', $script);
        Assert::stringContainsString('SIF_USOC_READ_ROLES', $script);
        Assert::stringContainsString('SIF_USOC_MANAGE_ROLES', $script);
        Assert::stringContainsString('SIF_LEGACY_DB_DSN', $script);

        Assert::stringContainsString('$ok = !in_array(false, $checks, true);', $script);
    }
}
