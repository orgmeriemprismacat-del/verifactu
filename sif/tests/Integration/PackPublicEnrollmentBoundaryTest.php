<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class PackPublicEnrollmentBoundaryTest
{
    public function testPublicPackEnrollmentUsesPostAndDoesNotPutPersonalDataInQueryString(): void
    {
        $root = dirname(__DIR__, 3);
        $jsPath = $root . '/codi-drive/web-actual/js1619773569/mostrarInscripcioPack.min.js';
        $phpPath = $root . '/codi-drive/web-actual/ajax/enviarInscripcioPack.php';

        $js = file_get_contents($jsPath);
        $php = file_get_contents($phpPath);
        if (!is_string($js) || !is_string($php)) {
            Assert::fail('Could not load PACK public enrollment boundary');
        }

        $endpoint = strpos($js, 'ajax/enviarInscripcioPack.php');
        $post = strpos($js, 'method: "POST"', $endpoint === false ? 0 : $endpoint);
        $nextGet = strpos($js, 'method: "GET"', $endpoint === false ? 0 : $endpoint);

        Assert::same(true, $endpoint !== false);
        Assert::same(true, $post !== false);
        Assert::same(true, $nextGet === false || $post < $nextGet);

        Assert::stringContainsString("REQUEST_METHOD", $php);
        Assert::stringContainsString("!== 'POST'", $php);
        Assert::stringContainsString("header('Allow: POST')", $php);
        Assert::stringContainsString('$request = $_POST;', $php);
        Assert::same(false, str_contains($php, '$_GET'));
    }

    public function testPublicPackEnrollmentHasSameSiteRequestBoundaryBeforeInputProcessing(): void
    {
        $path = dirname(__DIR__, 3)
            . '/codi-drive/web-actual/ajax/enviarInscripcioPack.php';

        $source = file_get_contents($path);
        if (!is_string($source)) {
            Assert::fail('Could not load PACK public enrollment endpoint');
        }

        Assert::stringContainsString('HTTP_SEC_FETCH_SITE', $source);
        Assert::stringContainsString('HTTP_ORIGIN', $source);
        Assert::stringContainsString('HTTP_REFERER', $source);
        Assert::stringContainsString("'www.prisma.cat', 'prisma.cat'", $source);
        Assert::stringContainsString("Cache-Control: no-store", $source);

        $methodGuard = strpos($source, 'REQUEST_METHOD');
        $siteGuard = strpos($source, 'HTTP_SEC_FETCH_SITE');
        $request = strpos($source, '$request = $_POST;');
        $firstInput = strpos($source, "new Text($request['nom'])");

        Assert::same(true, $methodGuard !== false);
        Assert::same(true, $siteGuard !== false);
        Assert::same(true, $request !== false);
        Assert::same(true, $firstInput !== false);
        Assert::same(true, $methodGuard < $request);
        Assert::same(true, $siteGuard < $request);
        Assert::same(true, $request < $firstInput);
    }
}
