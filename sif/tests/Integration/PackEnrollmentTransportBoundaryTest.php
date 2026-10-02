<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class PackEnrollmentTransportBoundaryTest
{
    public function testPackEnrollmentMutationUsesPostAndDoesNotReadGetParameters(): void
    {
        $root = dirname(__DIR__, 3);
        $js = file_get_contents(
            $root . '/codi-drive/web-actual/js1619773569/mostrarInscripcioPack.min.js'
        );
        $endpoint = file_get_contents(
            $root . '/codi-drive/web-actual/ajax/enviarInscripcioPack.php'
        );

        if (!is_string($js) || !is_string($endpoint)) {
            Assert::fail('Could not load PACK enrollment transport files');
        }

        $packAjax = strpos($js, 'url: path + "ajax/enviarInscripcioPack.php"');
        Assert::same(true, $packAjax !== false);
        $packAjaxSlice = substr($js, (int) $packAjax, 1000);
        Assert::stringContainsString('method: "POST"', $packAjaxSlice);
        Assert::stringContainsString(
            'url: "https://www.prisma.cat/ajax/mostrar_header_2.php"',
            $js
        );

        Assert::stringContainsString("REQUEST_METHOD", $endpoint);
        Assert::stringContainsString("PublicWebMutationAuthorization.php", $endpoint);
        Assert::stringContainsString("PublicWebMutationAuthorization::assertSameOriginAjax()", $endpoint);
        Assert::stringContainsString("!== 'POST'", $endpoint);
        Assert::stringContainsString("header('Allow: POST')", $endpoint);
        Assert::stringContainsString('http_response_code(405)', $endpoint);
        Assert::stringContainsString("\$_POST['dni']", $endpoint);
        Assert::stringContainsString("\$_POST['email']", $endpoint);
        Assert::stringContainsString("\$_POST['idPack']", $endpoint);
        Assert::stringContainsString("\$pagFrac = 'No'", $endpoint);

        if (str_contains($endpoint, "\$_POST['pagFrac']")) {
            Assert::fail('PACK ecommerce must not accept client-controlled fractional-payment mode.');
        }

        if (str_contains($endpoint, '$_GET[')) {
            Assert::fail('PACK enrollment mutation must not read personal or mutation data from GET.');
        }
    }
}
