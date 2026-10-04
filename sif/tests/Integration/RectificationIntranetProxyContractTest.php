<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RectificationIntranetProxyContractTest
{
    public function testProxyRequiresSessionEditPermissionSameOriginCsrfAndSignedSifClient(): void
    {
        $root = dirname(__DIR__, 3);
        $proxy = (string) file_get_contents(
            $root . '/codi-drive/intranet-actual/ajax/alumnes/sifRectificarFactura.php'
        );

        Assert::stringContainsString("REQUEST_METHOD", $proxy);
        Assert::stringContainsString("!== 'POST'", $proxy);
        Assert::stringContainsString('LegacyInvoiceReadContext::open()', $proxy);
        Assert::stringContainsString('LegacyInvoiceMutationAuthorization::assertSameOrigin()', $proxy);
        Assert::stringContainsString('SifRectificationAccess::resolve', $proxy);
        Assert::stringContainsString('SifRectificationAccess::assertCsrf', $proxy);
        Assert::stringContainsString('SIF_UC005_RECTIFICATION_UI_ENABLED', $proxy);
        Assert::stringContainsString('classification_event_uuid', $proxy);
        Assert::stringContainsString('expected_fingerprint', $proxy);
        Assert::stringContainsString('previewRectification', $proxy);
        Assert::stringContainsString('confirmRectification', $proxy);

        foreach ([
            'UPDATE factura',
            'DELETE FROM factura',
            'anularFactura(',
            'guardarDadesFactura(',
        ] as $legacyMutation) {
            if (stripos($proxy, $legacyMutation) !== false) {
                Assert::fail('UC-005 intranet proxy must not mutate fiscal legacy tables directly');
            }
        }
    }

    public function testClientUsesDedicatedSignedRectificationEndpoint(): void
    {
        $root = dirname(__DIR__, 3);
        $client = (string) file_get_contents(
            $root . '/codi-drive/intranet-actual/SifInternalApiClient.php'
        );

        Assert::stringContainsString('SIF_RECTIFICATION_API_URL', $client);
        Assert::stringContainsString('SIF_INTERNAL_RECTIFICATION_SIGNED_PATH', $client);
        Assert::stringContainsString('/api/factures/rectify.php', $client);
        Assert::stringContainsString('previewRectification', $client);
        Assert::stringContainsString('confirmRectification', $client);
        Assert::stringContainsString('requestTo(', $client);
        Assert::stringContainsString("hash_hmac('sha256'", $client);
    }

    public function testPageAndJavascriptExposeOnlyCsrfAndPreviewConfirmContract(): void
    {
        $root = dirname(__DIR__, 3);
        $page = (string) file_get_contents(
            $root . '/codi-drive/intranet-actual/alumnes-factura.php'
        );
        $javascript = (string) file_get_contents(
            $root . '/codi-drive/intranet-actual/js/alumnes-factura-sif.js'
        );

        Assert::stringContainsString('SIF_UC005_RECTIFICATION_UI_ENABLED', $page);
        Assert::stringContainsString('SifRectificationAccess::csrfToken()', $page);
        Assert::stringContainsString('window.sifUc005RectificationConfig', $page);

        Assert::stringContainsString('uc005SifRectificationPreview', $javascript);
        Assert::stringContainsString('uc005SifRectificationConfirm', $javascript);
        Assert::stringContainsString('X-CSRF-Token', $javascript);
        Assert::stringContainsString('X-Requested-With', $javascript);
        Assert::stringContainsString('classification_event_uuid', $javascript);
        Assert::stringContainsString('fiscal_correction_decision', $javascript);
        Assert::stringContainsString('ready_for_uc005_ui', $javascript);
        Assert::stringContainsString('Previsualitzar rectificativa', $javascript);
        Assert::stringContainsString('Confirmar i emetre rectificativa', $javascript);
        Assert::stringContainsString('Pendent de classificació fiscal UC-74', $javascript);

        if (str_contains($javascript, 'classification:')) {
            Assert::fail('Intranet JS must not provide an inline UC-74 fiscal classification');
        }
        if (str_contains($javascript, '<select') || str_contains($javascript, "name=\"invoice_type\"")) {
            Assert::fail('Intranet UC-005 must not expose a manual R1-R5 selector');
        }
    }
}
