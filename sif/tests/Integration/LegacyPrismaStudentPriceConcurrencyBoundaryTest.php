<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class LegacyPrismaStudentPriceConcurrencyBoundaryTest
{
    public function testPriceCalculationUsesMonotonicGenerationAndDiscardsStaleAjaxResponses(): void
    {
        $source = $this->readJs();

        Assert::stringContainsString('var uc020PriceRequestVersion = 0', $source);
        Assert::stringContainsString('function iniciarCalculPreuUC020()', $source);
        Assert::stringContainsString('function esCalculPreuVigentUC020(requestVersion)', $source);
        Assert::stringContainsString('var uc020RequestVersion = iniciarCalculPreuUC020();', $source);
        Assert::stringContainsString(
            'if (!esCalculPreuVigentUC020(uc020RequestVersion)) return;',
            $source
        );

        $done = strpos($source, 'searchPrice.done(function( msg ) {');
        $staleGuard = strpos(
            $source,
            'if (!esCalculPreuVigentUC020(uc020RequestVersion)) return;',
            $done === false ? 0 : $done
        );
        $discountAssignment = strpos(
            $source,
            'tipusPreuAplicat = parseInt(vectorTipusMsgPreu[0]);',
            $done === false ? 0 : $done
        );

        Assert::same(true, $done !== false);
        Assert::same(true, $staleGuard !== false);
        Assert::same(true, $discountAssignment !== false);
        Assert::same(true, $done < $staleGuard && $staleGuard < $discountAssignment);
    }

    public function testSubmitIsBlockedWhileLatestPriceCalculationIsPending(): void
    {
        $source = $this->readJs();

        $pendingGuard = strpos($source, 'if (uc020PriceCalculationPending) {');
        $submitFlow = strpos($source, 'comprovaSiHaRealitzatElCurs();');

        Assert::same(true, $pendingGuard !== false);
        Assert::same(true, $submitFlow !== false);
        Assert::same(true, $pendingGuard < $submitFlow);
        Assert::stringContainsString(
            "El preu encara s'està recalculant",
            $source
        );
    }

    public function testFailedLatestPriceCalculationKeepsSubmitBlocked(): void
    {
        $source = $this->readJs();

        Assert::stringContainsString('uc020PriceCalculationValid = false', $source);
        Assert::stringContainsString('if (!uc020PriceCalculationValid) {', $source);
        Assert::stringContainsString(
            'No hi ha cap càlcul de preu vigent i vàlid',
            $source
        );
        Assert::stringContainsString(
            'finalitzarCalculPreuUC020(uc020RequestVersion, false);',
            $source
        );

        $start = strpos($source, 'function iniciarCalculPreuUC020()');
        $invalidate = strpos(
            $source,
            'uc020PriceCalculationValid = false;',
            $start === false ? 0 : $start
        );
        Assert::same(true, $start !== false && $invalidate !== false && $start < $invalidate);
    }

    public function testValidPromotionSupersedesPrismaStudentOriginInsteadOfCombiningBoth(): void
    {
        $source = $this->readJs();

        $promoFunction = strpos($source, 'function aplicarPreuCodiPromocions(dadesPromo) {');
        $reset = strpos(
            $source,
            "if (tipusPreuAplicat == 1)\n\t\ttipusPreuAplicat = 0;",
            $promoFunction === false ? 0 : $promoFunction
        );
        $render = strpos(
            $source,
            'mostraPreu(missPreu, msgModal, true);',
            $promoFunction === false ? 0 : $promoFunction
        );

        Assert::same(true, $promoFunction !== false);
        Assert::same(true, $reset !== false);
        Assert::same(true, $render !== false);
        Assert::same(true, $promoFunction < $reset && $reset < $render);
    }

    public function testInvalidPromotionFallbackClearsPromotionMarkerBeforeReapplyingPrismaStudent(): void
    {
        $source = $this->readJs();

        Assert::stringContainsString(
            "if ( !promoValida ) {\n\t\tpromocioAplicada = '';\n\t\tcalcularPreuSenseCodiPromo( 2, uc020RequestVersion );",
            $source
        );
        Assert::stringContainsString(
            "else if ( trobat && (edicio == '' || edicio != promo[2]) ) {\n\t\t\t\tpromocioATrobadaplicada = '';",
            $source
        );
    }

    public function testDelayedUiUpdateAlsoChecksGeneration(): void
    {
        $source = $this->readJs();

        $timeout = strpos($source, 'setTimeout(function() {');
        $guard = strpos(
            $source,
            'if (!esCalculPreuVigentUC020(uc020RequestVersion)) return;',
            $timeout === false ? 0 : $timeout
        );

        Assert::same(true, $timeout !== false);
        Assert::same(true, $guard !== false);
        Assert::same(true, $timeout < $guard);
    }

    private function readJs(): string
    {
        $root = dirname(__DIR__, 3);
        $path = $root . '/codi-drive/web-actual/js1619773569/mostrarInscripcions.min.js';
        $content = file_get_contents($path);
        if ($content === false) {
            Assert::fail('Could not read legacy enrollment JS.');
        }

        return $content;
    }
}
