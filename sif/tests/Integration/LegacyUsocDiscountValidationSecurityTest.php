<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class LegacyUsocDiscountValidationSecurityTest
{
    public function testLegacyDiscountValidationUsesPostCsrfAndEditPermission(): void
    {
        $root = dirname(__DIR__, 3);
        $endpoint = file_get_contents(
            $root . '/codi-drive/intranet-actual/ajax/alumnes/sendMsgValidatCurosDescomptes.php'
        );
        $js = file_get_contents(
            $root . '/codi-drive/intranet-actual/js/alumnes-validar-descomptes.js'
        );
        $page = file_get_contents(
            $root . '/codi-drive/intranet-actual/alumnes-validar-descomptes.php'
        );

        if ($endpoint === false || $js === false || $page === false) {
            Assert::fail('Could not read legacy USOC discount validation files');
        }

        Assert::stringContainsString("REQUEST_METHOD", $endpoint);
        Assert::stringContainsString("!== 'POST'", $endpoint);
        Assert::stringContainsString("hash_equals", $endpoint);
        Assert::stringContainsString("csrf_validar_descomptes", $endpoint);
        Assert::stringContainsString("consultaRolsUsuari", $endpoint);
        Assert::stringContainsString("replaceRols", $endpoint);
        Assert::stringContainsString("consultaRolsEdiicio('/alumnes/validar-descomptes/')", $endpoint);
        Assert::stringContainsString("tePermisVisualitzacio", $endpoint);

        $refreshPos = strpos($endpoint, 'consultaRolsUsuari');
        $permissionPos = strpos($endpoint, "consultaRolsEdiicio('/alumnes/validar-descomptes/')");
        if ($refreshPos === false || $permissionPos === false || $refreshPos >= $permissionPos) {
            Assert::fail('Current user roles must be refreshed before checking edit permission.');
        }
        Assert::stringContainsString("FILTER_VALIDATE_INT", $endpoint);
        Assert::stringContainsString("validar_descomptes_requests", $endpoint);
        Assert::stringContainsString("validar_descomptes_usoc_requests", $endpoint);
        Assert::stringContainsString("'id_insc' => \$idInsc", $endpoint);
        Assert::stringContainsString("'desired_valid_desc' => \$desiredValidDesc", $endpoint);
        Assert::stringContainsString("requestId reutilitzat amb una operació diferent", $endpoint);
        Assert::stringContainsString("LegacyDiscountValidationLookup", $endpoint);
        Assert::stringContainsString('->isUsoc($idInsc)', $endpoint);
        Assert::stringContainsString("beginValidationDecision", $endpoint);
        Assert::stringContainsString("(\$beginDecision['tracked'] ?? false) !== true", $endpoint);
        Assert::stringContainsString("classificació USOC ha canviat abans de registrar la decisió al SIF", $endpoint);
        Assert::stringContainsString("catch (Throwable", $endpoint);
        Assert::stringContainsString("completeValidationDecision", $endpoint);
        Assert::stringContainsString("should_apply_legacy", $endpoint);

        if (str_contains($endpoint, '$_GET[')) {
            Assert::fail('USOC discount validation endpoint must not mutate from GET parameters.');
        }

        Assert::stringContainsString('method: "POST"', $js);
        Assert::stringContainsString('csrfToken: obtenirCsrfValidarDescomptes()', $js);
        Assert::stringContainsString('requestId: obtenirRequestIdValidarDescompte(idInsc, valid)', $js);
        Assert::stringContainsString('window.sessionStorage.getItem', $js);
        Assert::stringContainsString('window.sessionStorage.setItem', $js);
        Assert::stringContainsString('completarRequestValidarDescompte(idInsc, valid)', $js);

        Assert::stringContainsString('csrf_validar_descomptes', $page);
        Assert::stringContainsString('csrf-token-validar-descomptes', $page);
        Assert::stringContainsString('random_bytes(32)', $page);
    }
}
