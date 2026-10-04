<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class LegacyEnrollmentCompletionPrecheckBoundaryTest
{
    public function testCompletionPrecheckHasSingleDeterministicAjaxFlow(): void
    {
        $body = $this->functionBody();

        Assert::same(
            2,
            substr_count($body, 'ajax/buscarSiHaRealitzatElCurs.php')
        );
        Assert::same(
            1,
            substr_count($body, 'ajax/buscarCodiCursDeriva.php')
        );
        Assert::same(false, str_contains($body, "?doc="));
        Assert::same(false, str_contains($body, "&& msg!=''"));
    }

    public function testCurrentCourseCompletionStopsBeforeDerivedCourseLookup(): void
    {
        $body = $this->functionBody();

        $currentCompleted = strpos($body, "if (haRealitzatElCurs != '') {");
        $showCurrent = strpos($body, 'mostrarAvisCursRealitzat(haRealitzatElCurs, false);');
        $returnCurrent = strpos($body, 'return;', $showCurrent === false ? 0 : $showCurrent);
        $derivedLookup = strpos($body, 'ajax/buscarCodiCursDeriva.php');

        Assert::same(true, $currentCompleted !== false);
        Assert::same(true, $showCurrent !== false);
        Assert::same(true, $returnCurrent !== false);
        Assert::same(true, $derivedLookup !== false);
        Assert::same(
            true,
            $currentCompleted < $showCurrent
            && $showCurrent < $returnCurrent
            && $returnCurrent < $derivedLookup
        );
    }

    public function testConfirmationHandlerIsNamespacedAndReplacedBeforeBinding(): void
    {
        $body = $this->functionBody();

        $off = strpos($body, '.off("click.uc020")');
        $on = strpos($body, '.on("click.uc020"');
        $send = strpos($body, 'enviarInscripcio();', $on === false ? 0 : $on);

        Assert::same(true, $off !== false);
        Assert::same(true, $on !== false);
        Assert::same(true, $send !== false);
        Assert::same(true, $off < $on && $on < $send);
    }

    public function testPrecheckErrorsDoNotFallThroughToEnrollment(): void
    {
        $body = $this->functionBody();

        Assert::stringContainsString(
            'if (haRealitzatElCurs.toLowerCase().includes("error")) {',
            $body
        );
        Assert::stringContainsString(
            'if (cursDerivat.toLowerCase().includes("error")) {',
            $body
        );
        Assert::stringContainsString(
            'if (haRealitzatElCursDeriva.toLowerCase().includes("error")) {',
            $body
        );
    }

    private function functionBody(): string
    {
        $root = dirname(__DIR__, 3);
        $path = $root . '/codi-drive/web-actual/js1619773569/mostrarInscripcions.min.js';
        $source = file_get_contents($path);
        if ($source === false) {
            Assert::fail('Could not read legacy enrollment JS.');
        }

        $start = strpos($source, 'function comprovaSiHaRealitzatElCurs() {');
        $end = strpos($source, 'function validarFileCarnet()', $start === false ? 0 : $start);
        if ($start === false || $end === false || $end <= $start) {
            Assert::fail('Could not isolate completion precheck function.');
        }

        return substr($source, $start, $end - $start);
    }
}
