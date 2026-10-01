<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\ScriptRunner;

final class IncidentPanelEvidenceValidationScriptTest
{
    public function testValidEvidencePairClosesEnvironmentGate(): void
    {
        [$preproduction, $menu] = $this->evidenceFiles(
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
                [$preproduction, $menu]
            );

            Assert::same(0, $result['exit_code']);
            Assert::same('', $result['stderr']);

            $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
            Assert::same(true, $json['ok']);
            Assert::same(true, $json['checks']['preproduction_ok']);
            Assert::same(true, $json['checks']['preproduction_sha256_valid']);
            Assert::same(true, $json['checks']['menu_sha256_valid']);
            Assert::same(64, strlen((string) $json['inputs']['preproduction_sha256']));
            Assert::same(64, strlen((string) $json['inputs']['menu_sha256']));
            Assert::same(true, isset($json['validated_at']) && is_string($json['validated_at']));
            Assert::same(true, $json['checks']['menu_unique_target']);
            Assert::same(true, $json['checks']['menu_already_present']);
            Assert::same(true, $json['checks']['preproduction_no_secrets']);
            Assert::same(true, $json['checks']['menu_no_secrets']);
        } finally {
            @unlink($preproduction);
            @unlink($menu);
        }
    }

    public function testMenuCandidateStateDoesNotCloseEnvironmentGate(): void
    {
        [$preproduction, $menu] = $this->evidenceFiles(
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
                [$preproduction, $menu]
            );

            Assert::same(1, $result['exit_code']);
            $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

            Assert::same(false, $json['ok']);
            Assert::same(false, $json['checks']['menu_unique_target']);
            Assert::same(false, $json['checks']['menu_already_present']);
        } finally {
            @unlink($preproduction);
            @unlink($menu);
        }
    }

    public function testEvidenceContainingSecretsIsRejected(): void
    {
        [$preproduction, $menu] = $this->evidenceFiles(
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
                [$preproduction, $menu]
            );

            Assert::same(1, $result['exit_code']);
            $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

            Assert::same(false, $json['checks']['preproduction_no_secrets']);
        } finally {
            @unlink($preproduction);
            @unlink($menu);
        }
    }

    public function testTestEnvironmentCannotClosePreproductionGate(): void
    {
        [$preproduction, $menu] = $this->evidenceFiles(
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
                [$preproduction, $menu]
            );

            Assert::same(1, $result['exit_code']);
            $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

            Assert::same(false, $json['ok']);
            Assert::same(false, $json['checks']['preproduction_environment_valid']);
        } finally {
            @unlink($preproduction);
            @unlink($menu);
        }
    }

    public function testTopLevelOkWithoutGreenChildChecksCannotCloseEnvironmentGate(): void
    {
        [$preproduction, $menu] = $this->evidenceFiles(
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
                [$preproduction, $menu]
            );

            Assert::same(1, $result['exit_code']);
            $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

            Assert::same(false, $json['ok']);
            Assert::same(false, $json['checks']['preproduction_preflight_ok']);
            Assert::same(false, $json['checks']['preproduction_e2e_ok']);
        } finally {
            @unlink($preproduction);
            @unlink($menu);
        }
    }

    public function testWrongMenuScopeCannotCloseEnvironmentGate(): void
    {
        [$preproduction, $menu] = $this->evidenceFiles(
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
                [$preproduction, $menu]
            );

            Assert::same(1, $result['exit_code']);
            $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

            Assert::same(false, $json['ok']);
            Assert::same(false, $json['checks']['menu_scope_valid']);
        } finally {
            @unlink($preproduction);
            @unlink($menu);
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
        Assert::stringContainsString('menu_ok', $source);
        Assert::stringContainsString('menu_scope_valid', $source);
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

    private function evidenceFiles(array $preproduction, array $menu): array
    {
        $preproductionPath = tempnam(sys_get_temp_dir(), 'uc008-pre-');
        $menuPath = tempnam(sys_get_temp_dir(), 'uc008-menu-');

        if ($preproductionPath === false || $menuPath === false) {
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

        return [$preproductionPath, $menuPath];
    }
}
