<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class IncidentPanelIntranetBoundaryTest
{
    public function testIntranetIncidentBridgeRemainsReadOnly(): void
    {
        $source = $this->readIntranet('ajax/sif/sifIncidents.php');

        Assert::stringContainsString("['summary', 'list', 'view']", $source);
        Assert::stringContainsString('Incident mutation is only allowed in the official SIF panel', $source);
        Assert::stringContainsString("csrf_token", $source);
        Assert::stringContainsString('SifInternalIncidentClient', $source);

        foreach (["'assign'", "'evidence'", "'resolve'", "'dismiss'", "'reopen'"] as $mutation) {
            if (str_contains($source, '$action === ' . $mutation)) {
                Assert::fail('Intranet incident bridge must not implement mutation action ' . $mutation);
            }
        }
    }

    public function testPanelLaunchIdentityComesFromAuthenticatedSession(): void
    {
        $source = $this->readIntranet('ajax/sif/sifPanelLaunch.php');

        Assert::stringContainsString('comprovarSessio.php', $source);
        Assert::stringContainsString("csrf_token", $source);
        Assert::stringContainsString('SifPanelLaunchToken', $source);
        Assert::stringContainsString('$usuariObject->getUsuari()', $source);
        Assert::stringContainsString('$usuariObject->getRols()', $source);

        foreach (['$payload[\'actor_id\']', '$payload[\'roles\']', '$payload[\'key_id\']', '$payload[\'signature\']'] as $untrusted) {
            if (str_contains($source, $untrusted)) {
                Assert::fail('Panel launch must not trust browser supplied identity/signature field: ' . $untrusted);
            }
        }
    }

    public function testIntranetSummaryJavascriptDoesNotExposeLifecycleMutations(): void
    {
        $source = $this->readIntranet('js/sif-verifactu.js');

        Assert::stringContainsString("{action:'summary'}", $source);
        Assert::stringContainsString("{action:'list'", $source);
        Assert::stringContainsString('sifPanelLaunch.php', $source);

        foreach (['assign', 'evidence', 'resolve', 'dismiss', 'reopen'] as $action) {
            if (preg_match('/action\s*:\s*[\'\"]' . preg_quote($action, '/') . '[\'\"]/', $source) === 1) {
                Assert::fail('Intranet JS must not send lifecycle mutation action: ' . $action);
            }
        }
    }

    public function testIntranetFallbackNeverTurnsSifOutageIntoZeroIncidents(): void
    {
        $source = $this->readIntranet('js/sif-verifactu.js');

        Assert::stringContainsString('sessionStorage.setItem', $source);
        Assert::stringContainsString('sessionStorage.getItem', $source);
        Assert::stringContainsString('darrera dada validada', $source);
        Assert::stringContainsString('No es pot afirmar que hi hagi 0 incidències', $source);
        Assert::stringContainsString('Llistat no disponible mentre el SIF és inaccessible', $source);

        if (str_contains($source, 'localStorage.')) {
            Assert::fail('Incident summary fallback must stay scoped to the browser session.');
        }

        if (str_contains($source, 'sessionStorage.setItem(cacheKey, JSON.stringify(incidents')) {
            Assert::fail('Incident details must not be cached by the intranet fallback.');
        }
    }

    private function readIntranet(string $relativePath): string
    {
        $path = dirname(__DIR__, 3) . '/codi-drive/intranet-actual/' . $relativePath;
        $source = file_get_contents($path);
        if ($source === false) {
            Assert::fail('Could not read intranet source: ' . $relativePath);
        }

        return $source;
    }
}
