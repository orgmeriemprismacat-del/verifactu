<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\ScriptRunner;
use Prisma\Sif\Tests\Support\TestDatabase;

final class IncidentPanelPreflightScriptTest
{
    public function testConfiguredPanelPreflightReturnsGoWithoutMutatingData(): void
    {
        $db = TestDatabase::fresh();
        $result = ScriptRunner::run('scripts/preflight-incidents-panel.php', $this->validEnvironment());

        Assert::same(0, $result['exit_code']);
        Assert::same('', $result['stderr']);

        $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
        Assert::same(true, $json['ok']);
        Assert::same('uc-008-incident-panel-preflight', $json['scope']);
        Assert::same(false, $json['production_authorized']);
        Assert::same(true, $json['checks']['incident_manage_roles_can_read']);
        Assert::same(true, $json['checks']['panel_launch_secret_strong']);
        Assert::same(true, $json['checks']['internal_api_secret_strong']);
        Assert::same(true, $json['checks']['panel_files_present']);
        Assert::same(true, $json['checks']['errors_verifactu_table']);
        Assert::same(true, $json['checks']['sif_incident_action_table']);
        Assert::same(true, $json['checks']['internal_api_request_table']);
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM errors_verifactu')->fetchColumn());
    }

    public function testPanelPreflightFailsClosedForMissingRolesAndWeakSecrets(): void
    {
        TestDatabase::fresh();

        $result = ScriptRunner::run('scripts/preflight-incidents-panel.php', [
            'SIF_INCIDENT_READ_ROLES' => '',
            'SIF_INCIDENT_MANAGE_ROLES' => '',
            'SIF_INTERNAL_API_KEY_ID' => 'internal',
            'SIF_INTERNAL_API_SECRET' => 'short',
            'SIF_PANEL_LAUNCH_KEY_ID' => 'panel',
            'SIF_PANEL_LAUNCH_SECRET' => 'short',
        ]);

        Assert::same(1, $result['exit_code']);
        $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

        Assert::same(false, $json['ok']);
        Assert::same(false, $json['checks']['incident_read_roles_configured']);
        Assert::same(false, $json['checks']['incident_manage_roles_configured']);
        Assert::same(false, $json['checks']['internal_api_secret_strong']);
        Assert::same(false, $json['checks']['panel_launch_secret_strong']);
    }

    public function testPanelPreflightRejectsManagerRoleWithoutReadPermission(): void
    {
        TestDatabase::fresh();

        $environment = $this->validEnvironment();
        $environment['SIF_INCIDENT_READ_ROLES'] = 'AUDITOR_FISCAL';
        $environment['SIF_INCIDENT_MANAGE_ROLES'] = 'SIF_ADMIN';

        $result = ScriptRunner::run('scripts/preflight-incidents-panel.php', $environment);

        Assert::same(1, $result['exit_code']);
        $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

        Assert::same(false, $json['checks']['incident_manage_roles_can_read']);
        Assert::same(['SIF_ADMIN'], $json['missing_manage_read_roles']);
    }

    public function testPanelPreflightIsReadOnlyAndChecksRequiredSurface(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preflight-incidents-panel.php');
        if ($source === false) {
            Assert::fail('Could not read incident panel preflight');
        }

        Assert::stringContainsString('incident_read_roles_configured', $source);
        Assert::stringContainsString('incident_manage_roles_can_read', $source);
        Assert::stringContainsString('panel_launch_secret_strong', $source);
        Assert::stringContainsString('internal_api_secret_strong', $source);
        Assert::stringContainsString('public/sif/incidencies/index.php', $source);
        Assert::stringContainsString('sif_incident_action', $source);
        Assert::stringContainsString('internal_api_request', $source);

        foreach (['issueInvoice(', 'registerPayment(', 'UPDATE errors_verifactu', 'DELETE FROM'] as $forbidden) {
            if (str_contains($source, $forbidden)) {
                Assert::fail('Incident panel preflight must be read-only: ' . $forbidden);
            }
        }
    }

    private function validEnvironment(): array
    {
        return [
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
        ];
    }
}
