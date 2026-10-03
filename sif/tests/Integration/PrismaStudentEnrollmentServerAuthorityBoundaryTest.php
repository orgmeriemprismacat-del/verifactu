<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class PrismaStudentEnrollmentServerAuthorityBoundaryTest
{
    public function testCourseEnrollmentMutationUsesPostSameOriginAndServerSideApGuard(): void
    {
        $root = dirname(__DIR__, 3);
        $js = file_get_contents(
            $root . '/codi-drive/web-actual/js1619773569/mostrarInscripcions.min.js'
        );
        $endpoint = file_get_contents(
            $root . '/codi-drive/web-actual/ajax/enviarInscripcio.php'
        );
        $resolver = file_get_contents(
            $root . '/codi-drive/web-actual/inc/resoldrePreuAlumnePrisMaServidor.php'
        );

        if (!is_string($js) || !is_string($endpoint) || !is_string($resolver)) {
            Assert::fail('Could not load UC-020 public enrollment boundary files');
        }

        $ajax = strpos($js, 'url: path + "ajax/enviarInscripcio.php"');
        Assert::same(true, $ajax !== false);
        $ajaxSlice = substr($js, (int) $ajax, 1800);
        Assert::stringContainsString('method: "POST"', $ajaxSlice);

        Assert::stringContainsString("REQUEST_METHOD", $endpoint);
        Assert::stringContainsString("!== 'POST'", $endpoint);
        Assert::stringContainsString("header('Allow: POST')", $endpoint);
        Assert::stringContainsString("PublicWebMutationAuthorization.php", $endpoint);
        Assert::stringContainsString(
            "PublicWebMutationAuthorization::assertSameOriginAjax()",
            $endpoint
        );
        Assert::stringContainsString('$request = $_POST;', $endpoint);

        if (str_contains($endpoint, '$_GET[')) {
            Assert::fail('Course enrollment mutation must not read mutation data from GET.');
        }

        Assert::stringContainsString(
            'resoldrePreuAlumnePrisMaServidor(',
            $endpoint
        );
        Assert::stringContainsString('PRICE_CHANGED_ALUMNE_PRISMA', $endpoint);
        Assert::stringContainsString('DATA_RESOL, ID_PREU FROM curs', $endpoint);

        $guardPosition = strpos($endpoint, 'resoldrePreuAlumnePrisMaServidor(');
        $idPagPosition = strpos($endpoint, '$connexio->reserveIdPag()');
        Assert::same(true, $guardPosition !== false && $idPagPosition !== false);
        Assert::same(true, $guardPosition < $idPagPosition);

        Assert::stringContainsString('SELECT IMPORT FROM preu', $resolver);
        Assert::stringContainsString('SELECT PREU FROM descomptes', $resolver);
        Assert::stringContainsString('AND ID_PREU=? AND TIPUS=1', $resolver);
        Assert::stringContainsString("count(\$preus) !== 1", $resolver);

        // La regla d'elegibilitat legacy no es redefineix silenciosament en aquest tall.
        Assert::stringContainsString('FACTURA_RELACIONADA != NULL', $resolver);
    }
}
