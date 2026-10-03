<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\ScriptRunner;

final class IncidentPanelEvidenceValidationScriptTest
{
    public function testValidEvidencePairClosesEnvironmentGate(): void
    {
        [$preproduction, $menu, $manager] = $this->evidenceFiles(
            [
                'ok' => true,
                'scope' => 'uc-008-preproduction-verification',
                'environment' => 'preproduction',
                'production_authorized' => false,
                'checks' => ['preflight_ok' => true, 'e2e_ok' => true],
            ],
            [
                'ok' => true,
                'scope' => 'uc-008-intranet-menu-discovery',
                'read_only' => true,
                'target_url' => '/sif-verifactu.php',
                'existing_target_count' => 1,
                'status' => 'ALREADY_PRESENT',
            ]
        );

        try {
            $result = ScriptRunner::run(
                'scripts/validate-uc008-evidence.php',
                [],
                [$preproduction, $menu, $manager]
            );

            Assert::same(0, $result['exit_code']);
            Assert::same('', $result['stderr']);

            $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
            Assert::same(true, $json['ok']);
            Assert::same(true, $json['checks']['preproduction_ok']);
            Assert::same(true, $json['checks']['preproduction_sha256_valid']);
            Assert::same(true, $json['checks']['menu_sha256_valid']);
            Assert::same(true, $json['checks']['manager_e2e_sha256_valid']);
            Assert::same(64, strlen((string) $json['inputs']['preproduction_sha256']));
            Assert::same(64, strlen((string) $json['inputs']['menu_sha256']));
            Assert::same(64, strlen((string) $json['inputs']['manager_e2e_sha256']));
            Assert::same(true, $json['checks']['manager_e2e_ok']);
            Assert::same(true, $json['checks']['manager_e2e_synthetic_incident']);
            Assert::same(true, $json['checks']['manager_e2e_assign_correlation_matches']);
            Assert::same(true, $json['checks']['manager_e2e_add_evidence_correlation_matches']);
            Assert::same(true, $json['checks']['manager_e2e_resolve_correlation_matches']);
            Assert::same(true, $json['checks']['manager_e2e_resolve_evidence_payload_present']);
            Assert::same(true, $json['checks']['manager_e2e_database_query_ok']);
            Assert::same(true, isset($json['validated_at']) && is_string($json['validated_at']));
            Assert::same(true, $json['checks']['menu_environment_valid']);
            Assert::same(true, $json['checks']['menu_unique_target']);
            Assert::same(true, $json['checks']['menu_already_present']);
            Assert::same(true, $json['checks']['preproduction_no_secrets']);
            Assert::same(true, $json['checks']['menu_no_secrets']);
        } finally {
            @unlink($preproduction);
            @unlink($menu);
            @unlink($manager);
        }
    }

    public function testMenuCandidateStateDoesNotCloseEnvironmentGate(): void
    {
        [$preproduction, $menu, $manager] = $this->evidenceFiles(
            [
                'ok' => true,
                'scope' => 'uc-008-preproduction-verification',
                'environment' => 'preproduction',
                'production_authorized' => false,
                'checks' => ['preflight_ok' => true, 'e2e_ok' => true],
            ],
            [
                'ok' => true,
                'scope' => 'uc-008-intranet-menu-discovery',
                'read_only' => true,
                'target_url' => '/sif-verifactu.php',
                'existing_target_count' => 0,
                'status' => 'CONFIRM_PARENT_ROLES_ORDER_BEFORE_INSERT',
            ]
        );

        try {
            $result = ScriptRunner::run(
                'scripts/validate-uc008-evidence.php',
                [],
                [$preproduction, $menu, $manager]
            );

            Assert::same(1, $result['exit_code']);
            $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

            Assert::same(false, $json['ok']);
            Assert::same(false, $json['checks']['menu_unique_target']);
            Assert::same(false, $json['checks']['menu_already_present']);
        } finally {
            @unlink($preproduction);
            @unlink($menu);
            @unlink($manager);
        }
    }

    public function testEvidenceContainingSecretsIsRejected(): void
    {
        [$preproduction, $menu, $manager] = $this->evidenceFiles(
            [
                'ok' => true,
                'scope' => 'uc-008-preproduction-verification',
                'environment' => 'preproduction',
                'production_authorized' => false,
                'checks' => ['preflight_ok' => true, 'e2e_ok' => true],
                'panel_secret' => 'must-not-be-stored',
            ],
            [
                'ok' => true,
                'scope' => 'uc-008-intranet-menu-discovery',
                'read_only' => true,
                'target_url' => '/sif-verifactu.php',
                'existing_target_count' => 1,
                'status' => 'ALREADY_PRESENT',
            ]
        );

        try {
            $result = ScriptRunner::run(
                'scripts/validate-uc008-evidence.php',
                [],
                [$preproduction, $menu, $manager]
            );

            Assert::same(1, $result['exit_code']);
            $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

            Assert::same(false, $json['checks']['preproduction_no_secrets']);
        } finally {
            @unlink($preproduction);
            @unlink($menu);
            @unlink($manager);
        }
    }

    public function testTestEnvironmentCannotClosePreproductionGate(): void
    {
        [$preproduction, $menu, $manager] = $this->evidenceFiles(
            [
                'ok' => true,
                'scope' => 'uc-008-preproduction-verification',
                'environment' => 'test',
                'production_authorized' => false,
                'checks' => ['preflight_ok' => true, 'e2e_ok' => true],
            ],
            [
                'ok' => true,
                'scope' => 'uc-008-intranet-menu-discovery',
                'read_only' => true,
                'target_url' => '/sif-verifactu.php',
                'existing_target_count' => 1,
                'status' => 'ALREADY_PRESENT',
            ]
        );

        try {
            $result = ScriptRunner::run(
                'scripts/validate-uc008-evidence.php',
                [],
                [$preproduction, $menu, $manager]
            );

            Assert::same(1, $result['exit_code']);
            $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

            Assert::same(false, $json['ok']);
            Assert::same(false, $json['checks']['preproduction_environment_valid']);
        } finally {
            @unlink($preproduction);
            @unlink($menu);
            @unlink($manager);
        }
    }

    public function testTopLevelOkWithoutGreenChildChecksCannotCloseEnvironmentGate(): void
    {
        [$preproduction, $menu, $manager] = $this->evidenceFiles(
            [
                'ok' => true,
                'scope' => 'uc-008-preproduction-verification',
                'environment' => 'preproduction',
                'production_authorized' => false,
                'checks' => [],
            ],
            [
                'ok' => true,
                'scope' => 'uc-008-intranet-menu-discovery',
                'read_only' => true,
                'target_url' => '/sif-verifactu.php',
                'existing_target_count' => 1,
                'status' => 'ALREADY_PRESENT',
            ]
        );

        try {
            $result = ScriptRunner::run(
                'scripts/validate-uc008-evidence.php',
                [],
                [$preproduction, $menu, $manager]
            );

            Assert::same(1, $result['exit_code']);
            $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

            Assert::same(false, $json['ok']);
            Assert::same(false, $json['checks']['preproduction_preflight_ok']);
            Assert::same(false, $json['checks']['preproduction_e2e_ok']);
        } finally {
            @unlink($preproduction);
            @unlink($menu);
            @unlink($manager);
        }
    }

    public function testWrongMenuScopeCannotCloseEnvironmentGate(): void
    {
        [$preproduction, $menu, $manager] = $this->evidenceFiles(
            [
                'ok' => true,
                'scope' => 'uc-008-preproduction-verification',
                'environment' => 'preproduction',
                'production_authorized' => false,
                'checks' => ['preflight_ok' => true, 'e2e_ok' => true],
            ],
            [
                'ok' => true,
                'scope' => 'some-other-menu-check',
                'read_only' => true,
                'target_url' => '/sif-verifactu.php',
                'existing_target_count' => 1,
                'status' => 'ALREADY_PRESENT',
            ]
        );

        try {
            $result = ScriptRunner::run(
                'scripts/validate-uc008-evidence.php',
                [],
                [$preproduction, $menu, $manager]
            );

            Assert::same(1, $result['exit_code']);
            $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

            Assert::same(false, $json['ok']);
            Assert::same(false, $json['checks']['menu_scope_valid']);
        } finally {
            @unlink($preproduction);
            @unlink($menu);
            @unlink($manager);
        }
    }

    public function testMenuEvidenceFromProductionCannotCloseGate(): void
    {
        [$preproduction, $menu, $manager] = $this->evidenceFiles(
            [
                'ok' => true,
                'scope' => 'uc-008-preproduction-verification',
                'environment' => 'preproduction',
                'production_authorized' => false,
                'checks' => ['preflight_ok' => true, 'e2e_ok' => true],
            ],
            [
                'ok' => true,
                'scope' => 'uc-008-intranet-menu-discovery',
                'environment' => 'production',
                'read_only' => true,
                'target_url' => '/sif-verifactu.php',
                'existing_target_count' => 1,
                'status' => 'ALREADY_PRESENT',
            ]
        );

        try {
            $result = ScriptRunner::run(
                'scripts/validate-uc008-evidence.php',
                [],
                [$preproduction, $menu, $manager]
            );

            Assert::same(1, $result['exit_code']);
            $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
            Assert::same(false, $json['ok']);
            Assert::same(false, $json['checks']['menu_environment_valid']);
        } finally {
            @unlink($preproduction);
            @unlink($menu);
            @unlink($manager);
        }
    }

    public function testManagerEvidenceFromTestEnvironmentCannotCloseGate(): void
    {
        [$preproduction, $menu, $manager] = $this->evidenceFiles(
            [
                'ok' => true,
                'scope' => 'uc-008-preproduction-verification',
                'environment' => 'preproduction',
                'production_authorized' => false,
                'checks' => ['preflight_ok' => true, 'e2e_ok' => true],
            ],
            [
                'ok' => true,
                'scope' => 'uc-008-intranet-menu-discovery',
                'read_only' => true,
                'target_url' => '/sif-verifactu.php',
                'existing_target_count' => 1,
                'status' => 'ALREADY_PRESENT',
            ],
            [
                'ok' => true,
                'scope' => 'uc-008-manager-e2e-evidence',
                'environment' => 'test',
                'read_only' => true,
                'production_authorized' => false,
                'checks' => $this->validManagerChecks(),
            ]
        );

        try {
            $result = ScriptRunner::run(
                'scripts/validate-uc008-evidence.php',
                [],
                [$preproduction, $menu, $manager]
            );

            Assert::same(1, $result['exit_code']);
            $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
            Assert::same(false, $json['ok']);
            Assert::same(false, $json['checks']['manager_e2e_environment_valid']);
        } finally {
            @unlink($preproduction);
            @unlink($menu);
            @unlink($manager);
        }
    }

    public function testValidatorSourceDoesNotMutateFiscalOrMenuState(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/validate-uc008-evidence.php');
        if ($source === false) {
            Assert::fail('Could not read UC-008 evidence validator');
        }

        Assert::stringContainsString('uc-008-evidence-validation', $source);
        Assert::stringContainsString('preproduction_no_secrets', $source);
        Assert::stringContainsString('preproduction_environment_valid', $source);
        Assert::stringContainsString('preproduction_preflight_ok', $source);
        Assert::stringContainsString('preproduction_e2e_ok', $source);
        Assert::stringContainsString('preproduction_sha256', $source);
        Assert::stringContainsString('menu_sha256', $source);
        Assert::stringContainsString('manager_e2e_sha256', $source);
        Assert::stringContainsString('manager_e2e_scope_valid', $source);
        Assert::stringContainsString('manager_e2e_environment_valid', $source);
        Assert::stringContainsString('menu_ok', $source);
        Assert::stringContainsString('menu_scope_valid', $source);
        Assert::stringContainsString('menu_environment_valid', $source);
        Assert::stringContainsString('menu_already_present', $source);
        Assert::stringContainsString("'production_authorized' => false", $source);

        foreach ([
            'issueInvoice(',
            'registerPayment(',
            'INSERT INTO apartats',
            'UPDATE apartats',
            'DELETE FROM apartats',
        ] as $forbidden) {
            if (stripos($source, $forbidden) !== false) {
                Assert::fail('Evidence validator must remain read-only: ' . $forbidden);
            }
        }
    }

    private function evidenceFiles(
        array $preproduction,
        array $menu,
        ?array $manager = null
    ): array {
        $menu += ['environment' => 'preproduction'];

        $manager ??= [
            'ok' => true,
            'scope' => 'uc-008-manager-e2e-evidence',
            'environment' => 'preproduction',
            'read_only' => true,
            'production_authorized' => false,
            'checks' => $this->validManagerChecks(),
        ];

        $preproductionPath = tempnam(sys_get_temp_dir(), 'uc008-pre-');
        $menuPath = tempnam(sys_get_temp_dir(), 'uc008-menu-');
        $managerPath = tempnam(sys_get_temp_dir(), 'uc008-manager-');

        if ($preproductionPath === false || $menuPath === false || $managerPath === false) {
            throw new \RuntimeException('Could not create temporary evidence files');
        }

        file_put_contents(
            $preproductionPath,
            json_encode($preproduction, JSON_THROW_ON_ERROR)
        );
        file_put_contents(
            $menuPath,
            json_encode($menu, JSON_THROW_ON_ERROR)
        );
        file_put_contents(
            $managerPath,
            json_encode($manager, JSON_THROW_ON_ERROR)
        );

        return [$preproductionPath, $menuPath, $managerPath];
    }

    private function validManagerChecks(): array
    {
        return [
            'manage_roles_configured' => true,
            'incident_exists' => true,
            'synthetic_manager_incident' => true,
            'incident_resolved' => true,
            'assignee_present' => true,
            'resolved_at_present' => true,
            'resolution_notes_present' => true,
            'closure_criteria_present' => true,
            'assign_present' => true,
            'assign_manager_role' => true,
            'assign_actor_present' => true,
            'assign_correlation_matches' => true,
            'add_evidence_present' => true,
            'add_evidence_manager_role' => true,
            'add_evidence_actor_present' => true,
            'add_evidence_correlation_matches' => true,
            'add_evidence_payload_present' => true,
            'resolve_present' => true,
            'resolve_manager_role' => true,
            'resolve_actor_present' => true,
            'resolve_correlation_matches' => true,
            'resolve_evidence_payload_present' => true,
            'database_query_ok' => true,
        ];
    }
}
