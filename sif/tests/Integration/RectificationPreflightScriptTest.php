<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RectificationPreflightScriptTest
{
    public function testPreflightChecksUc005ReadinessWithoutMutatingFiscalData(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/scripts/preflight-rectification.php'
        );

        if ($source === false) {
            Assert::fail('Could not read UC-005 rectification preflight script');
        }

        foreach ([
            "PHP_SAPI !== 'cli'",
            'uc005_feature_enabled',
            'uc005_write_roles_configured',
            'internal_api_key_id_configured',
            'internal_api_secret_configured',
            'rectification_signed_path_configured',
            'issuer_nif_configured',
            'issuer_name_configured',
            'document_generator_version_configured',
            'aeat_system_name_configured',
            'aeat_system_id_configured',
            'aeat_system_version_configured',
            'aeat_installation_id_configured',
            'internal_api_request_table',
            'sif_audit_event_table',
            'operational_event_table',
            'factura_table',
            'factura_linia_table',
            'factura_registres_table',
            'factura_rectificacio_table',
            'factura_registre_control_table',
            'fiscal_sequence_table',
            'fiscal_chain_state_table',
            'fiscal_queue_table',
            'fact_rels_table',
            'document_job_table',
            'factura_documents_table',
            'fiscal_chain_state_seeded',
            "'G00000000'",
        ] as $required) {
            Assert::stringContainsString($required, $source);
        }

        foreach ([
            'issueInvoice(',
            'issueByUuid(',
            'issueByNumVisible(',
            'registerPayment(',
            'INSERT INTO factura',
            'UPDATE factura',
            'DELETE FROM factura',
        ] as $mutation) {
            if (str_contains($source, $mutation)) {
                Assert::fail('UC-005 preflight must not mutate fiscal data: ' . $mutation);
            }
        }

        if (str_contains($source, "result['secret']")) {
            Assert::fail('UC-005 preflight must never expose the internal API secret.');
        }
    }
}
