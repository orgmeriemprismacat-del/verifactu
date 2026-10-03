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
        Assert::same(false, str_contains($php, "\$request['pagFrac']"));
        Assert::stringContainsString("new Text('No')", $php);

        Assert::stringContainsString('inscripcioPackEnviant = false', $js);
        Assert::stringContainsString('if (inscripcioPackEnviant)', $js);
        Assert::stringContainsString('inscripcioPackEnviant = true', $js);
        Assert::stringContainsString('$("#form_enviar_dades").prop("disabled", true)', $js);
        Assert::stringContainsString('$("#form_enviar_dades").prop("disabled", false)', $js);
    }

    public function testPublicPackEnrollmentHasSameSiteRequestBoundaryBeforeInputProcessing(): void
    {
        $root = dirname(__DIR__, 3);
        $path = $root . '/codi-drive/web-actual/ajax/enviarInscripcioPack.php';
        $authPath = $root . '/codi-drive/web-actual/inc/PublicWebMutationAuthorization.php';

        $source = file_get_contents($path);
        $authorization = file_get_contents($authPath);
        if (!is_string($source) || !is_string($authorization)) {
            Assert::fail('Could not load PACK public enrollment authorization boundary');
        }

        Assert::stringContainsString('PublicWebMutationAuthorization.php', $source);
        Assert::stringContainsString('PublicWebMutationAuthorization::assertSameOriginAjax()', $source);
        Assert::stringContainsString('HTTP_SEC_FETCH_SITE', $source);
        Assert::stringContainsString("Cache-Control: no-store", $source);

        Assert::stringContainsString('WEB_ALLOWED_ORIGINS', $authorization);
        Assert::stringContainsString('HTTP_ORIGIN', $authorization);
        Assert::stringContainsString('HTTP_REFERER', $authorization);
        Assert::stringContainsString('https://www.prisma.cat;https://prisma.cat', $authorization);
        Assert::stringContainsString('HTTP_X_REQUESTED_WITH', $authorization);

        $methodGuard = strpos($source, 'REQUEST_METHOD');
        $authorizationCall = strpos($source, 'PublicWebMutationAuthorization::assertSameOriginAjax()');
        $siteGuard = strpos($source, 'HTTP_SEC_FETCH_SITE');
        $request = strpos($source, '$request = $_POST;');
        $firstInput = strpos($source, "new Text(\$request['nom'])");

        Assert::same(true, $methodGuard !== false);
        Assert::same(true, $authorizationCall !== false);
        Assert::same(true, $siteGuard !== false);
        Assert::same(true, $request !== false);
        Assert::same(true, $firstInput !== false);
        Assert::same(true, $methodGuard < $authorizationCall);
        Assert::same(true, $authorizationCall < $request);
        Assert::same(true, $siteGuard < $request);
        Assert::same(true, $request < $firstInput);
    }
}
