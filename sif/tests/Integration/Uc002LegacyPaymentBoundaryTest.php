<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class Uc002LegacyPaymentBoundaryTest
{
    public function testLegacyPaymentMutationUsesPostInsteadOfGet(): void
    {
        $root = dirname(__DIR__, 3);
        $js = file_get_contents($root . '/codi-drive/intranet-actual/js/alumnes-pagaments.js');
        $endpoint = file_get_contents($root . '/codi-drive/intranet-actual/ajax/alumnes/efectuarPagament.php');

        if (!is_string($js) || !is_string($endpoint)) {
            Assert::fail('Could not load UC-002 legacy payment boundary');
        }

        $call = strpos($js, 'url: path + "alumnes/efectuarPagament.php"');
        $post = strpos($js, 'method: "POST"', $call === false ? 0 : $call);

        Assert::same(true, $call !== false);
        Assert::same(true, $post !== false && $post - $call < 200);
        Assert::stringContainsString("REQUEST_METHOD", $endpoint);
        Assert::stringContainsString("!== 'POST'", $endpoint);
        Assert::stringContainsString("header('Allow: POST')", $endpoint);
        Assert::stringContainsString("$_POST['pagament']", $endpoint);
        Assert::same(false, str_contains($endpoint, '$_GET'));
    }

    public function testLegacyPaymentMutationChecksSessionOriginRoleAndServerAmount(): void
    {
        $root = dirname(__DIR__, 3);
        $endpoint = file_get_contents($root . '/codi-drive/intranet-actual/ajax/alumnes/efectuarPagament.php');

        if (!is_string($endpoint)) {
            Assert::fail('Could not load UC-002 payment endpoint');
        }

        Assert::stringContainsString("isset($_SESSION['usuari'], $_SESSION['intranet'])", $endpoint);
        Assert::stringContainsString('HTTP_SEC_FETCH_SITE', $endpoint);
        Assert::stringContainsString('HTTP_ORIGIN', $endpoint);
        Assert::stringContainsString('HTTP_REFERER', $endpoint);
        Assert::stringContainsString('HTTP_X_REQUESTED_WITH', $endpoint);
        Assert::stringContainsString("consultaRolsEdiicio('/alumnes/pagaments/')", $endpoint);
        Assert::stringContainsString('tePermisVisualitzacio', $endpoint);
        Assert::stringContainsString("(float) $pagament <= 0.0", $endpoint);
        Assert::stringContainsString("Cache-Control: no-store", $endpoint);
    }

    public function testLegacyAndVerifactuCopiesKeepTheSamePaymentMutationBoundary(): void
    {
        $root = dirname(__DIR__, 3);
        $current = file_get_contents($root . '/codi-drive/intranet-actual/ajax/alumnes/efectuarPagament.php');
        $verifactu = file_get_contents($root . '/codi-drive/intranet-nova-canvis-verifactu/ajax/alumnes/efectuarPagament.php');

        if (!is_string($current) || !is_string($verifactu)) {
            Assert::fail('Could not load both UC-002 legacy endpoint copies');
        }

        Assert::same($current, $verifactu);
    }
}
