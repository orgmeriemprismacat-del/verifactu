<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class PackComponentAvailabilityBoundaryTest
{
    public function testEditionAvailabilityUsesSignedCutoffDate(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents($root . '/codi-drive/web-actual/EdicioPack.php');

        if (!is_string($source)) {
            Assert::fail('Could not load EdicioPack availability policy');
        }

        Assert::stringContainsString("\$dataLimit->modify((\$diesOberts >= 0 ? '+' : '').\$diesOberts.' days');", $source);
        Assert::stringContainsString("\$avui = new DateTime('today');", $source);
        Assert::stringContainsString('return $dataLimit > $avui ? 1 : 0;', $source);
        Assert::same(false, str_contains($source, '$diff->days > 0'));
        Assert::same(false, str_contains($source, "new DateInterval('P'.\$diesOberts.'D')"));
    }

    public function testPackPageRequiresEveryComponentToRemainOpen(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents($root . '/codi-drive/web-actual/Pack.php');

        if (!is_string($source)) {
            Assert::fail('Could not load Pack availability policy');
        }

        Assert::stringContainsString('foreach ($this->edicions as $edicioPack)', $source);
        Assert::stringContainsString('$edicioPack->inscripcioOberta($diesOberts) > 0', $source);
        Assert::stringContainsString('if (!$edicioOberta)', $source);
        Assert::same(false, str_contains($source, '$this->edicions[0]->inscripcioOberta($diesOberts) < 0'));
    }

    public function testEnrollmentEndpointRevalidatesEveryComponentBeforePricesAndIdpag(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents($root . '/codi-drive/web-actual/ajax/enviarInscripcioPack.php');

        if (!is_string($source)) {
            Assert::fail('Could not load PACK enrollment endpoint');
        }

        Assert::stringContainsString('foreach ($edicions as $edicioDisponibilitat)', $source);
        Assert::stringContainsString('$edicioDisponibilitat->inscripcioOberta($diesOberts) > 0', $source);
        Assert::stringContainsString('$diesInscripcioPerHores[$horesEdicio]', $source);

        $availability = strpos($source, 'foreach ($edicions as $edicioDisponibilitat)');
        $serverPrice = strpos($source, '/* Preus autoritatius del pack');
        $idpag = strpos($source, '$connexio->reserveIdPag()');

        Assert::same(true, $availability !== false);
        Assert::same(true, $serverPrice !== false);
        Assert::same(true, $idpag !== false);
        Assert::same(true, $availability < $serverPrice);
        Assert::same(true, $availability < $idpag);
    }

    public function testIdempotentReplayDoesNotDependOnCurrentAvailability(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents($root . '/codi-drive/web-actual/ajax/enviarInscripcioPack.php');

        if (!is_string($source)) {
            Assert::fail('Could not load PACK enrollment endpoint');
        }

        $replay = strpos($source, 'uc015PackConfirmationHash($existingId, $keyEncr)');
        $availability = strpos($source, 'foreach ($edicions as $edicioDisponibilitat)');

        Assert::same(true, $replay !== false);
        Assert::same(true, $availability !== false);
        Assert::same(true, $replay < $availability);
    }

    public function testPackListingSeparatesEditionFilterFromGlobalAvailability(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents(
            $root . '/codi-drive/web-actual/inc/buscantPacksDisponibles.php'
        );

        if (!is_string($source)) {
            Assert::fail('Could not load PACK listing availability policy');
        }

        Assert::stringContainsString('$packsAmbEdicioSeleccionadaOberta = [];', $source);
        Assert::stringContainsString('$packsAmbTotsComponentsOberts = [];', $source);
        Assert::stringContainsString('$componentsPerPack[$idPackComponent]++', $source);
        Assert::stringContainsString('$componentsObertsPerPack[$idPackComponent]++', $source);
        Assert::stringContainsString('$compleixDisponibilitat =', $source);
        Assert::stringContainsString('isset($packsAmbTotsComponentsOberts[$idPack])', $source);
        Assert::stringContainsString('$compleixEdicio &&', $source);
        Assert::stringContainsString('$compleixDisponibilitat &&', $source);

        $globalCheck = strpos($source, '12B. DISPONIBILITAT GLOBAL DEL PACK');
        $finalFilters = strpos($source, '13. APLICACIÓ DELS FILTRES');

        Assert::same(true, $globalCheck !== false);
        Assert::same(true, $finalFilters !== false);
        Assert::same(true, $globalCheck < $finalFilters);
    }

}
