<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class DebtClaimIntranetBoundaryTest
{
    public function testBridgeRequiresSessionCsrfOriginPermissionAndSignedSifClient(): void
    {
        $root = dirname(__DIR__, 3);
        $ajax = file_get_contents($root . '/codi-drive/intranet-actual/ajax/facturacio/sifDebtClaim.php');
        $context = file_get_contents($root . '/codi-drive/intranet-actual/LegacyDebtClaimContext.php');
        $js = file_get_contents($root . '/codi-drive/intranet-actual/js/sif-debt-claim-bridge.js');

        if ($ajax === false || $context === false || $js === false) {
            Assert::fail('Could not read UC-012 intranet bridge files');
        }

        Assert::stringContainsString('SIF_DEBT_CLAIM_UI_ENABLED', $ajax);
        Assert::stringContainsString('REQUEST_METHOD', $ajax);
        Assert::stringContainsString('assertSameOrigin', $ajax);
        Assert::stringContainsString('HTTP_X_REQUESTED_WITH', $ajax);
        Assert::stringContainsString('csrf_debt_claim', $context);
        Assert::stringContainsString('hash_equals', $ajax);
        Assert::stringContainsString('SifAuthenticatedActor::fromUser', $ajax);
        Assert::stringContainsString('assertCanEdit', $ajax);
        Assert::stringContainsString('$permissionPage', $ajax);
        Assert::same(false, str_contains(
            $ajax,
            "assertCanEdit(\n        $user,\n        $intranet,\n        '/facturacio/morosos/'"
        ));
        Assert::stringContainsString('routeForSurface', $context);
        Assert::stringContainsString("'/facturacio/recordatori-pagament/'", $context);
        Assert::stringContainsString("'/facturacio/primera-reclamacio/'", $context);
        Assert::stringContainsString("'/facturacio/reclamacio-final/'", $context);
        Assert::stringContainsString("'/facturacio/morosos/'", $context);
        Assert::stringContainsString('SifInternalDebtClaimClient', $ajax);
        Assert::stringContainsString('operation_id', $ajax);
        Assert::stringContainsString('uuid_payment', $ajax);
        Assert::stringContainsString('DEBT_CLAIM|', $ajax);
        Assert::stringContainsString('uuid_factura', $ajax);
        Assert::stringContainsString('num_visible', $ajax);
        Assert::stringContainsString('id_insc', $ajax);

        Assert::stringContainsString('X-Requested-With', $js);
        Assert::stringContainsString('csrf-token-debt-claim', $js);
        Assert::stringContainsString('debt-claim-surface', $js);
        Assert::stringContainsString("params.set('surface', surface())", $js);
        Assert::stringContainsString('credentials: \'same-origin\'', $js);
        Assert::stringContainsString('record_notice', $js);
        Assert::stringContainsString('reconcile_after_payment', $js);
        Assert::stringContainsString('id_insc', $js);
    }

    public function testAllLegacyDebtPagesExposeCsrfWithoutChangingLegacyActions(): void
    {
        $root = dirname(__DIR__, 3);
        foreach ([
            'facturacio-recordatori-pagament-final.php' => 'RECORDATORI',
            'facturacio-primera-reclamacio-pagament.php' => 'PRIMERA_RECLAMACIO',
            'facturacio-reclamacio-final.php' => 'RECLAMACIO_FINAL',
            'facturacio-control-morosos.php' => 'MOROSOS',
        ] as $file => $surface) {
            $page = file_get_contents($root . '/codi-drive/intranet-actual/' . $file);
            if ($page === false) {
                Assert::fail('Could not read UC-012 legacy page ' . $file);
            }

            Assert::stringContainsString('csrf_debt_claim', $page);
            Assert::stringContainsString('csrf-token-debt-claim', $page);
            Assert::stringContainsString(
                'name="debt-claim-surface" content="' . $surface . '"',
                $page
            );
            Assert::stringContainsString('sif-debt-claim-bridge.js', $page);
        }
    }

    public function testLegacyReminderInitializesClaimMarkerBeforeUpdatingProjection(): void
    {
        $root = dirname(__DIR__, 3);
        $intranet = file_get_contents($root . '/codi-drive/intranet-actual/Intranet.php');
        if ($intranet === false) {
            Assert::fail('Could not read legacy Intranet.php');
        }

        $methodStart = strpos($intranet, 'public function updateSendMsg_Facturacio_Recordatori_Pagament');
        $methodEnd = strpos($intranet, '/* ############################## RECLAMACIÓ FINAL', $methodStart);
        if ($methodStart === false || $methodEnd === false) {
            Assert::fail('Could not isolate UC-012 legacy reminder method');
        }

        $method = substr($intranet, $methodStart, $methodEnd - $methodStart);
        Assert::stringContainsString('$reclamatM = $reclamat;', $method);
        Assert::stringContainsString('$reclamatM .= "Reclamat fi de curs";', $method);
        Assert::stringContainsString('bind_param("ssd", $pagObsM, $reclamatM, $id)', $method);
    }

    public function testFinalClaimLegacyStillContainsAcademicOffboardingAndMustNotBeBlindlyCutOver(): void
    {
        $root = dirname(__DIR__, 3);
        $intranet = file_get_contents($root . '/codi-drive/intranet-actual/Intranet.php');
        if ($intranet === false) {
            Assert::fail('Could not read legacy Intranet.php');
        }

        $start = strpos($intranet, 'public function updateSendMsg_LastClaimPay');
        if ($start === false) {
            Assert::fail('Could not locate legacy final-claim flow');
        }
        $section = substr($intranet, $start, 45000);

        Assert::stringContainsString('updateSendMsg_LastClaimPay_noApprove', $section);
        Assert::stringContainsString('__donarBaixaMoodleNou', $section);
        Assert::stringContainsString('updCampInscripcioBaixaMorosBD', $section);
    }


    public function testUnknownDebtSurfaceCannotSelectArbitraryPermissionRoute(): void
    {
        $root = dirname(__DIR__, 3);
        $context = file_get_contents(
            $root . '/codi-drive/intranet-actual/LegacyDebtClaimContext.php'
        );
        if ($context === false) {
            Assert::fail('Could not read LegacyDebtClaimContext.php');
        }

        Assert::stringContainsString('SURFACE_ROUTES', $context);
        Assert::stringContainsString('!isset(self::SURFACE_ROUTES[$surface])', $context);
        Assert::stringContainsString('Superfície de morositat no vàlida', $context);
        Assert::same(false, str_contains($context, '$_POST'));
    }

}
