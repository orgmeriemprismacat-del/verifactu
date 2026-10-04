<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ClaimPaymentIntranetBoundaryTest
{
    public function testClaimPaymentBridgeIsPostCsrfSameOriginRoleCheckedAndServerSigned(): void
    {
        $root = dirname(__DIR__, 3);
        $bridge = file_get_contents(
            $root . '/codi-drive/intranet-actual/ajax/facturacio/registerClaimPaymentSif.php'
        );
        $client = file_get_contents(
            $root . '/codi-drive/intranet-actual/SifInternalClaimPaymentClient.php'
        );
        $ui = file_get_contents(
            $root . '/codi-drive/intranet-actual/js/claim-payment-sif.js'
        );
        $intranet = file_get_contents(
            $root . '/codi-drive/intranet-actual/Intranet.php'
        );

        if ($bridge === false || $client === false || $ui === false || $intranet === false) {
            Assert::fail('Could not read UC-024 intranet bridge files');
        }

        Assert::stringContainsString("REQUEST_METHOD", $bridge);
        Assert::stringContainsString("!== 'POST'", $bridge);
        Assert::stringContainsString('LegacyInvoiceMutationAuthorization::assertSameOrigin', $bridge);
        Assert::stringContainsString("csrf_claim_payment", $bridge);
        Assert::stringContainsString("hash_equals", $bridge);
        Assert::stringContainsString('LegacyInvoiceMutationAuthorization::assertCanEdit', $bridge);
        Assert::stringContainsString('SifAuthenticatedActor::fromUser', $bridge);
        Assert::stringContainsString('SifInternalClaimPaymentClient', $bridge);
        Assert::stringContainsString("'LEGACY-INSC:' . $idInsc . ':' . $claimPhase", $bridge);
        Assert::stringContainsString("'source_inscription_id' => $sourceInscriptionId", $client);

        foreach ([
            '/facturacio/primera-reclamacio/',
            '/facturacio/recordatori-pagament/',
            '/facturacio/reclamacio-final/',
            '/facturacio/morosos/',
            'FIRST',
            'COURSE_END',
            'FINAL',
            'DEFAULTER',
        ] as $requiredContext) {
            Assert::stringContainsString($requiredContext, $bridge);
        }

        if (str_contains($bridge, '$_GET[')) {
            Assert::fail('UC-024 payment mutation must not read GET parameters');
        }
        if (str_contains($bridge, 'SIF_INTERNAL_API_SECRET')) {
            Assert::fail('UC-024 browser bridge must not handle SIF signing secrets');
        }

        Assert::stringContainsString("registerByInscription", $bridge);
        Assert::stringContainsString("data-id-insc", $intranet);
        if (substr_count($intranet, "data-id-insc=") < 6) {
            Assert::fail('All audited UC-024 claim tables must expose their inscription id');
        }

        Assert::stringContainsString("registerClaimPaymentSif.php", $ui);
        Assert::stringContainsString("csrf-token-claim-payment", $ui);
        Assert::stringContainsString("externalReceiptId", $ui);
        Assert::stringContainsString("'X-Requested-With': 'XMLHttpRequest'", $ui);
        Assert::stringContainsString("credentials: 'same-origin'", $ui);

        foreach ([
            'uuidFactura',
            'numVisible',
            'claimCaseId',
            'actorId',
            'SIF_INTERNAL_API_SECRET',
            'SIF_INTERNAL_API_KEY_ID',
        ] as $forbiddenBrowserField) {
            if (str_contains($ui, $forbiddenBrowserField)) {
                Assert::fail('UC-024 browser UI must not control ' . $forbiddenBrowserField);
            }
        }
    }

    public function testAllClaimPagesExposeClaimPaymentCsrfToken(): void
    {
        $root = dirname(__DIR__, 3);
        foreach ([
            'facturacio-primera-reclamacio-pagament.php',
            'facturacio-recordatori-pagament-final.php',
            'facturacio-reclamacio-final.php',
            'facturacio-control-morosos.php',
        ] as $file) {
            $page = file_get_contents($root . '/codi-drive/intranet-actual/' . $file);
            if ($page === false) {
                Assert::fail('Could not read claim payment page ' . $file);
            }

            Assert::stringContainsString("csrf_claim_payment", $page);
            Assert::stringContainsString('csrf-token-claim-payment', $page);
            Assert::stringContainsString('random_bytes(32)', $page);
            Assert::stringContainsString('SIF_CLAIM_PAYMENT_UI_ENABLED', $page);
            Assert::stringContainsString('js/claim-payment-sif.js', $page);
        }
    }
}
