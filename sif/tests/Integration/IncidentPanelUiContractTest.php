<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class IncidentPanelUiContractTest
{
    public function testListAndDetailExposeRequiredReadSurface(): void
    {
        $index = $this->read('public/sif/incidencies/index.php');
        $app = $this->read('public/sif/incidencies/app.js');

        foreach (['filter-status', 'filter-severity', 'filter-type', 'filter-assignee'] as $filter) {
            Assert::stringContainsString($filter, $index);
        }

        Assert::stringContainsString("action: 'list'", $app);
        Assert::stringContainsString("assignee_id: document.getElementById('filter-assignee')", $app);
        Assert::stringContainsString("action: 'view'", $app);
        Assert::stringContainsString('RESOURCE_TYPE', $app);
        Assert::stringContainsString('RESOURCE_ID', $app);
        Assert::stringContainsString('CORRELATION_ID', $app);
        Assert::stringContainsString('CLOSURE_CRITERIA', $app);
        Assert::stringContainsString('RESOLUTION_NOTES', $app);
    }

    public function testTimelineIsRenderedFromAppendOnlyActionHistory(): void
    {
        $app = $this->read('public/sif/incidencies/app.js');
        $repository = $this->read('src/Repository/IncidentActionRepository.php');

        Assert::stringContainsString('EVIDENCE_JSON', $app);
        Assert::stringContainsString('ACTION_TYPE', $app);
        Assert::stringContainsString('CREATED_AT', $app);
        Assert::stringContainsString('ORDER BY CREATED_AT ASC, ID ASC', $repository);
        Assert::stringContainsString('INSERT INTO sif_incident_action', $repository);

        foreach (['UPDATE sif_incident_action', 'DELETE FROM sif_incident_action'] as $mutation) {
            if (str_contains($repository, $mutation)) {
                Assert::fail('Incident action history must remain append-only: ' . $mutation);
            }
        }
    }

    public function testResolvedRequiresEvidenceInUiAndBackend(): void
    {
        $app = $this->read('public/sif/incidencies/app.js');
        $service = $this->read('src/Service/IncidentLifecycleService.php');

        Assert::stringContainsString("action === 'resolve' && !evidenceRef", $app);
        Assert::stringContainsString('RESOLVED exigeix evidència de verificació', $app);
        Assert::stringContainsString('$targetStatus === \'RESOLVED\'', $service);
        Assert::stringContainsString('Resolution evidence is required', $service);
    }

    public function testDismissRequiresClosureJustificationAndUiShowsResolution(): void
    {
        $index = $this->read('public/sif/incidencies/index.php');
        $app = $this->read('public/sif/incidencies/app.js');
        $service = $this->read('src/Service/IncidentLifecycleService.php');

        Assert::stringContainsString('value="dismiss">DISMISSED', $index);
        Assert::stringContainsString('name="closure_criteria" required', $index);
        Assert::stringContainsString('name="resolution_notes" required', $index);
        Assert::stringContainsString('Criteri tancament', $app);
        Assert::stringContainsString('Resolució', $app);
        Assert::stringContainsString("return \$this->close(\$actor, \$incidentId, \$payload, 'DISMISSED', 'DISMISS');", $service);
    }

    public function testRepairLinksTargetExistingIntranetSurfaces(): void
    {
        $index = $this->read('public/sif/incidencies/index.php');
        $app = $this->read('public/sif/incidencies/app.js');
        $invoiceJs = $this->read('../codi-drive/intranet-actual/js/alumnes-factura-sif.js');
        $aeatJs = $this->read('../codi-drive/intranet-actual/js/sif-registres-aeat.js');

        Assert::stringContainsString('repair-links', $index);
        Assert::stringContainsString('alumnes-factura.php?uuid_factura=', $app);
        Assert::stringContainsString('sif-registres-aeat.php?queue_id=', $app);
        Assert::stringContainsString("RESOURCE_TYPE || '').toUpperCase()", $app);
        Assert::stringContainsString("resourceType === 'FISCAL_QUEUE'", $app);
        Assert::stringContainsString('noopener noreferrer', $app);

        Assert::stringContainsString("get('uuid_factura')", $invoiceJs);
        Assert::stringContainsString('viewSifInvoice(deepLinkUuid)', $invoiceJs);
        Assert::stringContainsString("get('queue_id')", $aeatJs);
        Assert::stringContainsString('loadDetail(Number(deepQueueId))', $aeatJs);

        foreach ([$invoiceJs, $aeatJs] as $target) {
            foreach (["action: 'resolve'", "action: 'dismiss'", "action: 'assign'"] as $forbidden) {
                if (str_contains($target, $forbidden)) {
                    Assert::fail('Repair deep-link target must not auto-run incident mutation: ' . $forbidden);
                }
            }
        }
    }

    public function testPanelHasNoBulkRetryAction(): void
    {
        $index = strtolower($this->read('public/sif/incidencies/index.php'));
        $app = strtolower($this->read('public/sif/incidencies/app.js'));

        foreach (['retry all', 'reintentar tot', 'reintenta-ho tot'] as $forbidden) {
            if (str_contains($index, $forbidden) || str_contains($app, $forbidden)) {
                Assert::fail('Incident panel must not expose bulk retry: ' . $forbidden);
            }
        }
    }

    private function read(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . '/' . $relativePath;
        $source = file_get_contents($path);
        if ($source === false) {
            Assert::fail('Could not read SIF source: ' . $relativePath);
        }

        return $source;
    }
}
