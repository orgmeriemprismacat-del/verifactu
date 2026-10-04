<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class Uc007IntranetBoundaryTest
{
    public function testInvoicePageLoadsSingleCanonicalUc007Implementation(): void
    {
        $page = $this->readIntranet('alumnes-factura.php');
        $js = $this->readIntranet('js/alumnes-factura.js');

        Assert::stringContainsString('js/alumnes-factura.js?ver=1.2', $page);
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

    public function testLegacyPdfReconstructionHasExplicitReadOnlyMode(): void
    {
        $source = $this->readIntranet('Intranet.php');
        $download = $this->readIntranet('ajax/alumnes/descarregaFactura.php');
        $start = strpos($source, 'public function generaFactura(');
        $end = strpos($source, '/* -------------------------- Consultar certificat', $start === false ? 0 : $start);

        if ($start === false || $end === false || $end <= $start) {
            Assert::fail('Could not isolate legacy generaFactura method.');
        }

        $fragment = substr($source, $start, $end - $start);
        Assert::stringContainsString(
            'public function generaFactura($factura, $descarrega, $marcaGenerada = true)',
            $fragment
        );
        Assert::stringContainsString('file_put_contents($filename, $pdf)', $fragment);
        Assert::stringContainsString(
            "if ( $marcaGenerada && ( $generada == null || $generada == '' ) )",
            $fragment
        );
        Assert::stringContainsString('generaFactura((int) $id, true, false)', $download);
    }

    public function testLegacyInvoiceDownloadDoesNotMarkInvoiceAsGenerated(): void
    {
        $download = $this->readIntranet('ajax/alumnes/descarregaFactura.php');
        $intranet = $this->readIntranet('Intranet.php');

        Assert::stringContainsString('generaFactura((int) $id, true, false)', $download);
        Assert::stringContainsString('public function generaFactura($factura, $descarrega, $marcaGenerada = true)', $intranet);
        Assert::stringContainsString('if ( $marcaGenerada && ( $generada == null || $generada == \'\' ) )', $intranet);
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

    public function testLegacyInvoiceDownloadUsesBinaryPdfContractOnBothPages(): void
    {
        $wrapper = $this->readIntranet('ajax/alumnes/descarregaFactura.php');

        Assert::stringContainsString("header('Content-Type: application/pdf')", $wrapper);
        Assert::stringContainsString("header('Content-Disposition: attachment; filename=\"' . $filename . '\"')", $wrapper);
        Assert::stringContainsString('echo $bytes;', $wrapper);

        foreach ([
            'js/alumnes-factura.js',
            'js/alumnes-mostrar-alumne.js',
        ] as $jsFile) {
            $js = $this->readIntranet($jsFile);
            $position = strpos($js, 'function uc007DescarregarFacturaLlegada(');
            if ($position === false) {
                Assert::fail('Missing binary legacy invoice download helper in ' . $jsFile);
            }

            $fragment = substr($js, $position, 4200);
            Assert::stringContainsString('alumnes/descarregaFactura.php', $fragment);
            Assert::stringContainsString('method: "POST"', $fragment);
            Assert::stringContainsString('"X-Requested-With": "XMLHttpRequest"', $fragment);
            Assert::stringContainsString('response.blob()', $fragment);
            Assert::stringContainsString('Content-Disposition', $fragment);
            Assert::stringContainsString('URL.createObjectURL(result.blob)', $fragment);

            if (str_contains($fragment, 'dataType: "html"')) {
                Assert::fail('Binary invoice response must not be parsed as HTML in ' . $jsFile);
            }
        }
    }

    public function testSifSearchSurfacesResultTruncationInsteadOfPresentingPartialListAsComplete(): void
    {
        $bridge = $this->readIntranet('ajax/alumnes/sifFactures.php');
        $js = $this->readIntranet('js/alumnes-factura.js');

        Assert::stringContainsString("'has_more' => \$hasMore", $bridge);
        Assert::stringContainsString("(\$matches['has_more'] ?? false) === true", $bridge);
        Assert::stringContainsString('res.has_more === true', $js);
        Assert::stringContainsString(
            'La cerca té més resultats dels que es poden mostrar. Afegeix algun filtre per veure un conjunt complet.',
            $js
        );
    }

    public function testParticipantIdentitySearchFailsInsteadOfSilentlyTruncatingAtTwoHundred(): void
    {
        $bridge = $this->readIntranet('ajax/alumnes/sifFactures.php');

        Assert::stringContainsString('ORDER BY ID DESC LIMIT 201', $bridge);
        Assert::stringContainsString('if (count($ids) > 200)', $bridge);
        Assert::stringContainsString(
            'Massa inscripcions coincideixen amb la identitat; afegeix un filtre més específic',
            $bridge
        );

        if (str_contains($bridge, 'ORDER BY ID DESC LIMIT 200')) {
            Assert::fail('UC-007 participant identity resolution must not silently truncate at 200 rows.');
        }
    }

    public function testLegacySifGuardIsActiveWheneverUc007BoundaryIsEnabled(): void
    {
        $guard = $this->readIntranet('SifLegacyInvoiceMutationGuard.php');

        Assert::stringContainsString("getenv('SIF_UC007_QUERY_ENABLED')", $guard);
        Assert::stringContainsString('return $mutationBlock || $uc007ReadBoundary;', $guard);
        Assert::stringContainsString(
            'No s’ha pogut verificar si la factura ja està governada pel SIF',
            $guard
        );
    }

    public function testLegacySearchKeepsPersonalDataOutOfUc007QueryStrings(): void
    {
        $js = $this->readIntranet('js/alumnes-factura.js');

        foreach ([
            'alumnes/consultaUsuarisFacturaRelacionada.php',
            'alumnes/mostrarTaulaUsuaris2.php',
            'alumnes/mostrarTotesFacturesUsuari_Factures.php',
        ] as $endpoint) {
            $position = strpos($js, $endpoint);
            if ($position === false) {
                Assert::fail('Could not locate UC-007 legacy endpoint: ' . $endpoint);
            }

            $fragment = substr($js, $position, 420);
            Assert::stringContainsString('method: "POST"', $fragment);
        }

        foreach ([
            'ajax/alumnes/consultaUsuarisFacturaRelacionada.php',
            'ajax/alumnes/mostrarTaulaUsuaris2.php',
            'ajax/alumnes/mostrarTotesFacturesUsuari_Factures.php',
        ] as $wrapper) {
            $source = $this->readIntranet($wrapper);
            Assert::stringContainsString("LegacyInvoiceMutationAuthorization::assertSameOrigin()", $source);
            Assert::stringContainsString("\$request = \$method === 'POST' ? \$_POST : \$_GET;", $source);
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

    public function testLegacyInvoicePreviewAndPdfEscapeDatabaseValues(): void
    {
        $intranet = $this->readIntranet('Intranet.php');
        $start = strpos($intranet, 'public function generaFactura(');
        $end = strpos($intranet, '/* -------------------------- Consultar certificat', $start === false ? 0 : $start);

        if ($start === false || $end === false || $end <= $start) {
            Assert::fail('Could not isolate legacy generaFactura implementation.');
        }

        $fragment = substr($intranet, $start, $end - $start);
        foreach ([
            '$this->__escapeHtmlValue($num)',
            '$this->__escapeHtmlValue($objRao->get())',
            '$this->__escapeHtmlValue($objCif->get())',
            '$this->__escapeHtmlValue($objAdreca->get())',
            '$this->__escapeHtmlValue($objPobl->get())',
            '$this->__escapeHtmlValue($concepte1)',
            '$this->__escapeHtmlValue($concepte2)',
            '$this->__escapeHtmlValue($import)',
        ] as $escapedValue) {
            Assert::stringContainsString($escapedValue, $fragment);
        }

        foreach ([
            '<p>".$concepte1."</p>',
            '$dadesFacturaPagador .= "<strong>".$objRao->get()."</strong><br />";',
        ] as $unsafePattern) {
            if (str_contains($fragment, $unsafePattern)) {
                Assert::fail('Legacy invoice HTML contains unescaped database value: ' . $unsafePattern);
            }
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
            "if ( \$marcaGenerada && ( \$generada == null || \$generada == '' ) )",
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
