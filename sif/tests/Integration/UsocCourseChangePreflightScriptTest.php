<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class UsocCourseChangePreflightScriptTest
{
    public function testPreflightChecksCourseChangeRuntimeWithoutExecutingEffects(): void
    {
        $root = dirname(__DIR__, 3);
        $script = file_get_contents(
            $root . '/sif/scripts/preflight-usoc-course-change.php'
        );

        if ($script === false) {
            Assert::fail('Could not read USOC course change preflight script');
        }

        Assert::stringContainsString("PHP_SAPI !== 'cli'", $script);
        Assert::stringContainsString('environment_not_production', $script);

        foreach ([
            'sif_database_connectivity',
            'usoc_financing_case_table',
            'usoc_lifecycle_execution_table',
            'usoc_lifecycle_execution_allows_course_change',
            'enrollment_fund_movement_table',
            'factura_table',
            'factura_rectificacio_table',
            'payment_transaction_table',
            'payment_allocation_table',
            'operational_event_table',
            'fiscal_chain_state_seeded',
            'legacy_web_database_connectivity',
            'legacy_inscripcions_table',
            'legacy_curs_table',
            'legacy_jornades_table',
            'legacy_preu_table',
            'legacy_descomptes_table',
            'legacy_intranet_database_connectivity',
            'legacy_params_table',
            'internal_api_key_id',
            'internal_api_secret',
            'internal_api_usoc_signed_path',
            'usoc_manage_roles',
            'usoc_entity_billing_name',
            'usoc_entity_billing_nif',
            'target_resolver_service',
            'fund_plan_service',
            'preview_service',
            'preparation_service',
            'destination_binding_service',
            'execution_service',
            'intranet_preview_endpoint_file',
            'intranet_prepare_endpoint_file',
            'intranet_course_change_endpoint_file',
            'intranet_course_change_ui_file',
        ] as $check) {
            Assert::stringContainsString("'" . $check . "'", $script);
        }

        Assert::stringContainsString(
            'ConnectionFactory::makeLegacy($config)',
            $script
        );
        Assert::stringContainsString(
            'ConnectionFactory::makeLegacyIntranet($config)',
            $script
        );
        Assert::stringContainsString(
            "columnDefinitionContains(",
            $script
        );
        Assert::stringContainsString(
            "'COURSE_CHANGE'",
            $script
        );

        foreach ([
            'SIF_INTERNAL_USOC_URL',
            'SIF_INTERNAL_USOC_SIGNED_PATH',
            'SIF_INTERNAL_API_KEY_ID',
            'SIF_INTERNAL_API_SECRET',
            'SIF_USOC_MANAGE_ROLES',
            'SIF_USOC_ENTITY_NAME',
            'SIF_USOC_ENTITY_NIF',
            'SIF_LEGACY_DB_DSN',
            'SIF_LEGACY_INTRANET_DB_DSN',
        ] as $env) {
            Assert::stringContainsString("'" . $env . "'", $script);
        }

        foreach ([
            'new UsocCourseChangeExecutionService(',
            '->executeCourseChange(',
            '->issueInvoice(',
            '->registerPayment(',
            'realitzarCanviCurs_modalCanviCurs(',
        ] as $mutation) {
            if (str_contains($script, $mutation)) {
                Assert::fail(
                    'USOC course change preflight must stay read-only: ' . $mutation
                );
            }
        }
    }
}
