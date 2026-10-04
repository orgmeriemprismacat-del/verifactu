<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class Uc007IntranetBoundaryTest
{
    public function testInvoicePageLoadsSingleCanonicalUc007Implementation(): void
    {
        $page = $this->readIntranet('alumnes-factura.php');
        $js = $this->readIntranet('js/alumnes-factura.js');

        Assert::stringContainsString('js/alumnes-factura.js?ver=1.1', $page);
        if (str_contains($page, 'SIF_INVOICE_QUERY_UI_ENABLED')) {
            Assert::fail('Obsolete no-op UC-007 UI flag must not remain in the page template.');
        }
        if (str_contains($page, 'alumnes-factura-sif.js')) {
            Assert::fail('UC-007 invoice page must not load the obsolete duplicate SIF module.');
        }

        Assert::stringContainsString("tipusCerca == 'uuid'", $js);
        Assert::stringContainsString('criteria.uuid_factura = params.uuid', $js);
        Assert::stringContainsString("new URLSearchParams(window.location.search).get('uuid_factura')", $js);
        Assert::stringContainsString('action: "search"', $js);
        Assert::stringContainsString('action: "view"', $js);
        Assert::stringContainsString('sifDocument.php', $js);
    }

    public function testLegacyPdfReconstructionDoesNotMutateGeneratedBusinessState(): void
    {
        $source = $this->readIntranet('Intranet.php');
        $start = strpos($source, 'public function generaFactura($factura, $descarrega)');
        $end = strpos($source, '/* -------------------------- Consultar certificat', $start === false ? 0 : $start);

        if ($start === false || $end === false || $end <= $start) {
            Assert::fail('Could not isolate legacy generaFactura method.');
        }

        $fragment = substr($source, $start, $end - $start);
        Assert::stringContainsString('file_put_contents($filename, $pdf)', $fragment);

        if (str_contains($fragment, 'updGeneratFactura')) {
            Assert::fail('UC-007 download must not mutate GENERAT while reconstructing a temporary PDF.');
        }
    }

    public function testLegacyInvoiceDownloadUsesReadBoundaryNotClientSideEditPermission(): void
    {
        $js = $this->readIntranet('js/alumnes-factura.js');
        $start = strpos($js, "$('.download-factura').on('click'");
        $end = strpos($js, "if ($('#factura-num-pagines'))", $start === false ? 0 : $start);

        if ($start === false || $end === false || $end <= $start) {
            Assert::fail('Could not locate legacy invoice download fragment.');
        }

        $fragment = substr($js, $start, $end - $start);
        Assert::stringContainsString('alumnes/descarregaFactura.php', $fragment);
        Assert::stringContainsString('method: "POST"', $fragment);

        if (str_contains($fragment, 'tePermisEdicio')) {
            Assert::fail('UC-007 legacy document download is a read action and must not depend on client-side edit permission.');
        }
    }

    public function testLegacySearchInitializesStateUsesStableDelimiterAndEscapesTitle(): void
    {
        $intranet = $this->readIntranet('Intranet.php');

        Assert::stringContainsString('$existeixCerca = false;', $intranet);
        Assert::stringContainsString(
            'if ( count($dniDefinitius) > 0 ) $dniUsuaris .= "|";',
            $intranet
        );
        Assert::stringContainsString(
            'justify-content-center d-flex\'>".$this->__escapeHtmlValue($cercaPer)."</p>',
            $intranet
        );

        if (str_contains($intranet, 'if ( $i > 0 ) $dniUsuaris .= "|";')) {
            Assert::fail('Legacy UC-007 search must not derive separators from the loop index.');
        }
    }

    public function testLegacyDownloadDoesNotMutateGeneratedMarker(): void
    {
        $wrapper = $this->readIntranet('ajax/alumnes/descarregaFactura.php');
        $intranet = $this->readIntranet('Intranet.php');

        Assert::stringContainsString('generaFactura((int) $id, true, false)', $wrapper);
        Assert::stringContainsString(
            'public function generaFactura($factura, $descarrega, $marcaGenerada = true)',
            $intranet
        );
        Assert::stringContainsString(
            "if ( $marcaGenerada && ( $generada == null || $generada == '' ) )",
            $intranet
        );
    }

    public function testStudentPageExecutesUpdatedSourceInsteadOfStaleMinifiedAsset(): void
    {
        $page = $this->readIntranet('alumnes-mostrar-alumne.php');
        $js = $this->readIntranet('js/alumnes-mostrar-alumne.js');

        Assert::stringContainsString('js/alumnes-mostrar-alumne.js?ver=1.7', $page);
        if (str_contains($page, 'SIF_INVOICE_QUERY_UI_ENABLED')) {
            Assert::fail('Obsolete no-op UC-007 UI flag must not remain in the student page template.');
        }
        if (str_contains($page, 'alumnes-mostrar-alumne.min.js')) {
            Assert::fail('UC-007 student page must not execute the stale minified invoice flow.');
        }
        if (str_contains($page, 'alumnes-mostrar-alumne-sif.js')) {
            Assert::fail('UC-007 student page must not execute a second SIF implementation.');
        }

        Assert::stringContainsString('alumnes/factura/#/uuid/', $js);
        Assert::stringContainsString('action: "view_by_enrollment"', $js);
        Assert::stringContainsString('mostrarModalConsultaFacturaLlegat(id)', $js);
        Assert::stringContainsString('url: path + "alumnes/descarregaFactura.php"', $js);
        Assert::stringContainsString('method: "POST"', $js);

        if (str_contains($js, 'resD.toLowerCase()')) {
            Assert::fail('Legacy invoice fallback must not reference the obsolete undefined resD variable.');
        }
    }

    public function testLegacyFallbackDoesNotReplaceModalAfterBindingPaginationHandlers(): void
    {
        $js = $this->readIntranet('js/alumnes-mostrar-alumne.js');
        $start = strpos($js, 'function mostrarModalConsultaFacturaLlegat');
        if ($start === false) {
            Assert::fail('Could not locate UC-007 legacy fallback function.');
        }

        $fragment = substr($js, $start, 9000);
        $leftHandler = strpos($fragment, "$('.fletxa-left').on('click'");
        $rightHandler = strpos($fragment, "$('.fletxa-right').on('click'");

        if ($leftHandler === false || $rightHandler === false) {
            Assert::fail('Could not locate legacy invoice pagination handlers.');
        }

        $afterHandlers = substr($fragment, max($leftHandler, $rightHandler));
        if (str_contains($afterHandlers, '$("#modalConsultaFactura .modal-body").html(res);')) {
            Assert::fail('Replacing modal HTML after binding arrows would destroy the pagination handlers.');
        }
    }

    public function testUc007BrowserBoundaryUsesAuthenticatedServerBridge(): void
    {
        $bridge = $this->readIntranet('ajax/alumnes/sifFactures.php');

        Assert::stringContainsString('LegacyInvoiceReadContext::open()', $bridge);
        Assert::stringContainsString('LegacyInvoiceMutationAuthorization::assertSameOrigin()', $bridge);
        Assert::stringContainsString('SifAuthenticatedActor::fromUser', $bridge);
        Assert::stringContainsString('new SifInternalApiClient()', $bridge);
        Assert::stringContainsString("'FEATURE_DISABLED'", $bridge);

        foreach (['SIF_INTERNAL_API_SECRET', 'X-SIF-Signature', 'invoice_scope'] as $browserControlled) {
            if (str_contains($bridge, '$payload[' . var_export($browserControlled, true) . ']')) {
                Assert::fail('UC-007 bridge must not accept trusted identity/security field from browser: ' . $browserControlled);
            }
        }
    }

    private function readIntranet(string $relativePath): string
    {
        $path = dirname(__DIR__, 3) . '/codi-drive/intranet-actual/' . $relativePath;
        $source = file_get_contents($path);
        if ($source === false) {
            Assert::fail('Could not read UC-007 intranet source: ' . $relativePath);
        }

        return $source;
    }
}
