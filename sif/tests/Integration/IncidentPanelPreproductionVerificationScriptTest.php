<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\ScriptRunner;
use Prisma\Sif\Tests\Support\TestDatabase;

final class IncidentPanelPreproductionVerificationScriptTest
{
    public function testVerifierRefusesProductionBeforeChildChecks(): void
    {
        TestDatabase::fresh();

        $result = ScriptRunner::run('scripts/verify-incidents-panel-preproduction.php', [
            'SIF_ENV' => 'production',
        ]);

        Assert::same(1, $result['exit_code']);
        Assert::same('', $result['stderr']);
        $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

        Assert::same(false, $json['ok']);
        Assert::same(false, $json['production_authorized']);
        Assert::same(false, $json['checks']['environment_not_production']);
        Assert::same(false, array_key_exists('preflight', $json));
        Assert::same(false, array_key_exists('e2e', $json));
    }

    public function testVerifierAggregatesGreenPreflightAndBlockedE2e(): void
    {
        TestDatabase::fresh();

        $result = ScriptRunner::run(
            'scripts/verify-incidents-panel-preproduction.php',
            $this->validPanelEnvironment()
        );

        Assert::same(1, $result['exit_code']);
        Assert::same('', $result['stderr']);
        $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

        Assert::same(false, $json['ok']);
        Assert::same(true, $json['checks']['preflight_exit_zero']);
        Assert::same(true, $json['checks']['preflight_ok']);
        Assert::same(false, $json['checks']['e2e_exit_zero']);
        Assert::same(false, $json['checks']['e2e_ok']);
        Assert::same(true, $json['preflight']['ok']);
        Assert::same(false, $json['e2e']['ok']);
        Assert::same(false, $json['e2e']['checks']['panel_url_configured']);
    }

    public function testVerifierSourceSanitizesEvidenceAndDoesNotAuthorizeProduction(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/scripts/verify-incidents-panel-preproduction.php'
        );
        if ($source === false) {
            Assert::fail('Could not read UC-008 preproduction verifier');
        }

        Assert::stringContainsString('preflight-incidents-panel.php', $source);
        Assert::stringContainsString('e2e-incidents-panel.php', $source);
        Assert::stringContainsString('sanitizeEvidence', $source);
        Assert::stringContainsString("'production_authorized' => false", $source);
        Assert::stringContainsString("'secret'", $source);
        Assert::stringContainsString("'password'", $source);

        foreach (['issueInvoice(', 'registerPayment(', "'action' => 'resolve'", "'action' => 'dismiss'"] as $forbidden) {
            if (str_contains($source, $forbidden)) {
                Assert::fail('Preproduction verifier must remain read-only: ' . $forbidden);
            }
        }
    }

    private function validPanelEnvironment(): array
    {
        return [
            'SIF_ENV' => 'test',
            'SIF_INCIDENT_READ_ROLES' => 'AUDITOR_FISCAL,SIF_ADMIN,RESPONSABLE_TECNICA',
            'SIF_INCIDENT_MANAGE_ROLES' => 'SIF_ADMIN,RESPONSABLE_TECNICA',
            'SIF_INCIDENT_QUERY_MAX_RESULTS' => '100',
            'SIF_INTERNAL_API_KEY_ID' => 'internal-test-key',
            'SIF_INTERNAL_API_SECRET' => str_repeat('a', 40),
            'SIF_INTERNAL_INCIDENT_SIGNED_PATH' => '/api/incidents/manage.php',
            'SIF_PANEL_LAUNCH_KEY_ID' => 'panel-test-key',
            'SIF_PANEL_LAUNCH_SECRET' => str_repeat('b', 40),
            'SIF_PANEL_INCIDENTS_PATH' => '/sif/incidencies/',
            'SIF_PANEL_LAUNCH_MAX_SKEW' => '120',
            'SIF_PANEL_SESSION_NAME' => 'SIFPANELSESSID',
            'SIF_E2E_INCIDENT_PANEL_URL' => '',
            'SIF_E2E_INCIDENT_READ_ROLE' => 'AUDITOR_FISCAL',
        ];
    }
}
