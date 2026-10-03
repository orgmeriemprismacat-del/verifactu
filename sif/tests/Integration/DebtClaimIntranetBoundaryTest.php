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
        Assert::stringContainsString("'/facturacio/morosos/'", $ajax);
        Assert::stringContainsString('SifInternalDebtClaimClient', $ajax);
        Assert::stringContainsString('operation_id', $ajax);
        Assert::stringContainsString('uuid_payment', $ajax);
        Assert::stringContainsString('DEBT_CLAIM|', $ajax);
        Assert::stringContainsString('uuid_factura', $ajax);
        Assert::stringContainsString('num_visible', $ajax);
        Assert::stringContainsString('id_insc', $ajax);

        Assert::stringContainsString('X-Requested-With', $js);
        Assert::stringContainsString('csrf-token-debt-claim', $js);
        Assert::stringContainsString('credentials: \'same-origin\'', $js);
        Assert::stringContainsString('record_notice', $js);
        Assert::stringContainsString('reconcile_after_payment', $js);
        Assert::stringContainsString('id_insc', $js);
    }

    public function testAllLegacyDebtPagesExposeCsrfWithoutChangingLegacyActions(): void
    {
        $root = dirname(__DIR__, 3);
        foreach ([
            'facturacio-recordatori-pagament-final.php',
            'facturacio-primera-reclamacio-pagament.php',
            'facturacio-reclamacio-final.php',
            'facturacio-control-morosos.php',
        ] as $file) {
            $page = file_get_contents($root . '/codi-drive/intranet-actual/' . $file);
            if ($page === false) {
                Assert::fail('Could not read UC-012 legacy page ' . $file);
            }

            Assert::stringContainsString('csrf_debt_claim', $page);
            Assert::stringContainsString('csrf-token-debt-claim', $page);
            Assert::stringContainsString('sif-debt-claim-bridge.js', $page);
        }
    }
}
