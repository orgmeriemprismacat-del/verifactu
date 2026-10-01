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

        Assert::stringContainsString(
            'url: path + "ajax/enviarInscripcioPack.php"',
            $js
        );
        Assert::stringContainsString('method: "POST"', $js);

        Assert::stringContainsString("REQUEST_METHOD", $endpoint);
        Assert::stringContainsString("!== 'POST'", $endpoint);
        Assert::stringContainsString("header('Allow: POST')", $endpoint);
        Assert::stringContainsString('http_response_code(405)', $endpoint);
        Assert::stringContainsString("\$_POST['dni']", $endpoint);
        Assert::stringContainsString("\$_POST['email']", $endpoint);
        Assert::stringContainsString("\$_POST['idPack']", $endpoint);

        if (str_contains($endpoint, '$_GET[')) {
            Assert::fail('PACK enrollment mutation must not read personal or mutation data from GET.');
        }
    }
}
