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

    public function testStudentPageExecutesUpdatedSourceInsteadOfStaleMinifiedAsset(): void
    {
        $page = $this->readIntranet('alumnes-mostrar-alumne.php');
        $js = $this->readIntranet('js/alumnes-mostrar-alumne.js');

        Assert::stringContainsString('js/alumnes-mostrar-alumne.js?ver=1.7', $page);
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
