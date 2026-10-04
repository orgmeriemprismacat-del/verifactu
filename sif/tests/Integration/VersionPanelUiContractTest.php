<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class VersionPanelUiContractTest
{
    public function testPanelDoesNotAcceptEditableRuntimeHashes(): void
    {
        $index = $this->read('public/sif/versions/index.php');
        $app = $this->read('public/sif/versions/app.js');

        Assert::stringContainsString('Registrar candidata des del runtime actual', $index);
        Assert::stringContainsString('Els hashes no són editables', $index);
        Assert::stringContainsString("action: 'register_current'", $app);

        foreach (['name="git_revision"', 'name="artifact_hash"', 'name="config_hash"', 'name="database_version"'] as $forbidden) {
            if (str_contains($index, $forbidden)) {
                Assert::fail('Runtime evidence must not be editable from the browser: ' . $forbidden);
            }
        }
    }

    public function testPanelHasExplicitDeclarationPreflightAndActivationSteps(): void
    {
        $index = $this->read('public/sif/versions/index.php');
        $app = $this->read('public/sif/versions/app.js');
        $actions = $this->read('public/sif/versions/actions.php');

        foreach (['declaration-form', 'preflight-form', 'activate-form'] as $id) {
            Assert::stringContainsString($id, $index);
        }
        foreach (['attach_declaration', 'preflight', 'activate'] as $action) {
            Assert::stringContainsString($action, $app);
            Assert::stringContainsString($action, $actions);
        }
        Assert::stringContainsString('X-CSRF-Token', $app);
        Assert::stringContainsString('assertCsrf', $actions);
        Assert::stringContainsString("Cache-Control: private, no-store", $actions);
        Assert::stringContainsString("header('Allow: POST')", $actions);
        Assert::stringContainsString('$exception instanceof SifException', $actions);
        Assert::stringContainsString('Internal SIF version governance error', $actions);
        Assert::stringContainsString('window.confirm', $app);
        Assert::stringContainsString('dataset.operationId', $app);
    }

    public function testEvidenceCliIsReadOnlyAndProductionGuarded(): void
    {
        $script = $this->read('scripts/verify-version-governance-evidence.php');
        $verifier = $this->read('src/Service/SifVersionEvidenceVerifier.php');

        Assert::stringContainsString('SIF_UC010_EVIDENCE_ALLOW_PRODUCTION', $script);
        Assert::stringContainsString("'production_authorized' => false", $script);
        Assert::stringContainsString("'production_authorized' => false", $verifier);
        Assert::stringContainsString("SELECT COUNT(*) FROM sif_version WHERE STATUS = 'ACTIVE'", $verifier);

        foreach (['INSERT INTO', 'UPDATE sif_version', 'DELETE FROM'] as $forbidden) {
            if (str_contains($verifier, $forbidden)) {
                Assert::fail('UC-010 evidence verifier must remain read-only: ' . $forbidden);
            }
        }
    }

    public function testIntranetLaunchIsAllowlistedForVersions(): void
    {
        $page = $this->read('../codi-drive/intranet-actual/sif-verifactu.php');
        $js = $this->read('../codi-drive/intranet-actual/js/sif-verifactu.js');
        $launcher = $this->read('../codi-drive/intranet-actual/ajax/sif/sifPanelLaunch.php');

        Assert::stringContainsString('sif-open-versions', $page);
        Assert::stringContainsString("launchPanel('versions')", $js);
        Assert::stringContainsString("['incidents', 'versions']", $launcher);
        Assert::stringContainsString("SIF_PANEL_VERSIONS_URL", $launcher);
        Assert::stringContainsString("SIF_PANEL_VERSIONS_PATH", $launcher);
    }

    private function read(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . '/' . $relativePath;
        $source = file_get_contents($path);
        if ($source === false) {
            Assert::fail('Could not read UC-010 source: ' . $relativePath);
        }
        return $source;
    }
}
