<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\ScriptRunner;
use Prisma\Sif\Tests\Support\TestDatabase;

final class IncidentPanelE2eScriptTest
{
    public function testE2eRefusesProductionBeforeAnyHttpCall(): void
    {
        TestDatabase::fresh();

        $result = ScriptRunner::run('scripts/e2e-incidents-panel.php', [
            'SIF_ENV' => 'production',
            'SIF_E2E_INCIDENT_PANEL_URL' => 'https://example.invalid/sif/incidencies/',
        ]);

        Assert::same(1, $result['exit_code']);
        Assert::same('', $result['stderr']);
        $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

        Assert::same(false, $json['ok']);
        Assert::same(false, $json['production_authorized']);
        Assert::same(false, $json['checks']['environment_not_production']);
    }

    public function testE2eFailsClosedWhenPreproductionUrlIsMissing(): void
    {
        TestDatabase::fresh();

        $result = ScriptRunner::run('scripts/e2e-incidents-panel.php', [
            'SIF_ENV' => 'test',
            'SIF_E2E_INCIDENT_PANEL_URL' => '',
            'SIF_E2E_INCIDENT_READ_ROLE' => 'AUDITOR_FISCAL',
            'SIF_PANEL_LAUNCH_KEY_ID' => 'panel-test-key',
            'SIF_PANEL_LAUNCH_SECRET' => str_repeat('b', 40),
        ]);

        Assert::same(1, $result['exit_code']);
        $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

        Assert::same(false, $json['ok']);
        Assert::same(false, $json['checks']['panel_url_configured']);
        Assert::same(false, $json['checks']['panel_url_https']);
    }

    public function testPreproductionE2eRejectsProductionOrUnexpectedHostBeforeHttpCall(): void
    {
        TestDatabase::fresh();

        $result = ScriptRunner::run('scripts/e2e-incidents-panel.php', [
            'SIF_ENV' => 'preproduction',
            'SIF_E2E_INCIDENT_PANEL_URL' => 'https://pay.prisma.cat/sif/incidencies/',
            'SIF_E2E_INCIDENT_EXPECTED_HOST' => 'pay-pre.prisma.cat',
            'SIF_PRODUCTION_HOST' => 'pay.prisma.cat',
            'SIF_E2E_INCIDENT_READ_ROLE' => 'AUDITOR_FISCAL',
            'SIF_PANEL_LAUNCH_KEY_ID' => 'panel-test-key',
            'SIF_PANEL_LAUNCH_SECRET' => str_repeat('b', 40),
        ]);

        Assert::same(1, $result['exit_code']);
        Assert::same('', $result['stderr']);
        $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

        Assert::same(false, $json['ok']);
        Assert::same(true, $json['checks']['panel_expected_host_configured']);
        Assert::same(false, $json['checks']['panel_host_matches_expected']);
        Assert::same(false, $json['checks']['panel_host_not_production']);
    }

    public function testE2eSourceIsStrictlyReadOnlyForIncidentLifecycle(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/e2e-incidents-panel.php');
        if ($source === false) {
            Assert::fail('Could not read incident panel E2E script');
        }

        Assert::stringContainsString("'action' => 'summary'", $source);
        Assert::stringContainsString("'action' => 'list'", $source);
        Assert::stringContainsString("'action' => 'logout'", $source);
        Assert::stringContainsString('session_invalid_after_logout', $source);
        Assert::stringContainsString('read_only_actor_has_no_manage_controls', $source);
        Assert::stringContainsString('production_authorized', $source);
        Assert::stringContainsString('SIF_E2E_INCIDENT_EXPECTED_HOST', $source);
        Assert::stringContainsString('panel_host_matches_expected', $source);
        Assert::stringContainsString('panel_host_not_production', $source);

        foreach (['assign', 'evidence', 'resolve', 'dismiss', 'reopen', 'open'] as $action) {
            if (str_contains($source, "'action' => '" . $action . "'")) {
                Assert::fail('Incident panel E2E must not mutate lifecycle: ' . $action);
            }
        }
    }

    public function testE2eDoesNotDisableTlsVerificationOrPrintSecrets(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/e2e-incidents-panel.php');
        if ($source === false) {
            Assert::fail('Could not read incident panel E2E script');
        }

        foreach (['verify_peer', 'verify_peer_name', 'allow_self_signed'] as $tlsOverride) {
            if (str_contains($source, $tlsOverride)) {
                Assert::fail('Incident panel E2E must use default TLS verification: ' . $tlsOverride);
            }
        }

        if (str_contains($source, "'launch_secret' =>")) {
            Assert::fail('Incident panel E2E result must not serialize the launch secret.');
        }
    }
}
