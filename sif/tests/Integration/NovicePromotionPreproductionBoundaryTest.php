<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class NovicePromotionPreproductionBoundaryTest
{
    public function testPreflightIsReadOnlyAndFailsClosedOutsideTestOrPreproduction(): void
    {
        $source = $this->read('sif/scripts/preflight-novice-promotion.php');

        Assert::stringContainsString(
            "'environment_is_test_or_preproduction' => in_array(\$env, ['test', 'preproduction'], true)",
            $source
        );

        foreach ([
            'INSERT INTO',
            'UPDATE ',
            'DELETE FROM',
            'TRUNCATE ',
            'DROP TABLE',
            'ALTER TABLE',
        ] as $mutation) {
            if (stripos($source, $mutation) !== false) {
                Assert::fail('UC-111 preflight must remain read-only: ' . $mutation);
            }
        }

        Assert::stringContainsString("exit(\$failed === [] ? 0 : 1);", $source);
    }

    public function testPreflightCoversRequiredUc111RuntimeSurfaces(): void
    {
        $source = $this->read('sif/scripts/preflight-novice-promotion.php');

        foreach ([
            'wrapping_key_is_64_hex',
            'novice_manage_roles_configured',
            'novice_signed_path_correct',
            'sif_database_connectivity',
            'legacy_database_connectivity',
            'commercial_operation_table',
            'discount_validation_table',
            'commercial_entitlement_table',
            'commercial_entitlement_event_table',
            'novice_promotion_grant_table',
            'novice_promotion_code_outbox_table',
            'novice_promotion_application_table',
            'grant_service_present',
            'code_preparation_service_present',
            'student_summary_service_present',
            'secretary_projector_present',
            'api_endpoint_present',
            'local_test_runner_present',
        ] as $check) {
            Assert::stringContainsString("'" . $check . "'", $source);
        }

        foreach ([
            'SIF_NOVICE_PROMOTION_UI_ENABLED',
            'SIF_APP_ROOT',
            'SIF_INTERNAL_NOVICE_PROMOTION_URL',
            'SIF_NOVICE_PROMO_WRAP_KEY_HEX',
            'SIF_NOVICE_PROMOTION_MANAGE_ROLES',
            'SIF_INTERNAL_API_SECRET',
        ] as $environmentVariable) {
            Assert::stringContainsString("'" . $environmentVariable . "'", $source);
        }
    }

    public function testPreflightDoesNotPrintSecretValues(): void
    {
        $source = $this->read('sif/scripts/preflight-novice-promotion.php');

        Assert::same(false, str_contains($source, "'wrapping_key_hex' => \$wrapKey"));
        Assert::same(false, str_contains($source, "'internal_api_secret' =>"));
        Assert::same(false, str_contains($source, "'password' =>"));
        Assert::stringContainsString("'wrapping_key_configured' =>", $source);
        Assert::stringContainsString("'internal_api_secret_configured' =>", $source);
    }

    private function read(string $relativePath): string
    {
        $path = dirname(__DIR__, 3) . '/' . $relativePath;
        $content = file_get_contents($path);
        if ($content === false) {
            Assert::fail('Could not read ' . $relativePath);
        }

        return $content;
    }
}
