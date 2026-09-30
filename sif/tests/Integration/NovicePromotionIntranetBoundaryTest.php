<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class NovicePromotionIntranetBoundaryTest
{
    public function testStudentPromotionBridgeUsesAuthenticatedReadBoundary(): void
    {
        $root = dirname(__DIR__, 3);
        $page = file_get_contents($root . '/codi-drive/intranet-actual/alumnes-mostrar-alumne.php');
        $js = file_get_contents($root . '/codi-drive/intranet-actual/js/alumnes-mostrar-alumne-uc111.js');
        $endpoint = file_get_contents($root . '/codi-drive/intranet-actual/ajax/alumnes/mostrarPromocioDocentNovell.php');
        $context = file_get_contents($root . '/codi-drive/intranet-actual/LegacyNovicePromotionContext.php');
        $validationJs = file_get_contents($root . '/codi-drive/intranet-actual/js/alumnes-validar-descomptes.js');
        $decisionEndpoint = file_get_contents($root . '/codi-drive/intranet-actual/ajax/alumnes/sendMsgValidatProfessorNovell.php');

        if ($page === false || $js === false || $endpoint === false || $context === false || $validationJs === false || $decisionEndpoint === false) {
            Assert::fail('Could not read UC-111 intranet boundary files');
        }

        Assert::stringContainsString('SIF_NOVICE_PROMOTION_UI_ENABLED', $page);
        Assert::stringContainsString('alumnes-mostrar-alumne-uc111.js', $page);
        Assert::stringContainsString('csrf-token-alumnes-lifecycle', $page);

        Assert::stringContainsString("method: 'POST'", $js);
        Assert::stringContainsString('csrfToken: csrfToken()', $js);
        Assert::stringContainsString('mostrarPromocioDocentNovell.php', $js);

        Assert::stringContainsString('SIF_NOVICE_PROMOTION_UI_ENABLED', $endpoint);
        Assert::stringContainsString("!== 'POST'", $endpoint);
        Assert::stringContainsString('LegacyNovicePromotionContext::open()', $endpoint);
        Assert::stringContainsString('LegacyInvoiceMutationAuthorization::assertSameOrigin()', $endpoint);
        Assert::stringContainsString('SifAuthenticatedActor::fromUser', $endpoint);
        Assert::stringContainsString('hash_equals', $endpoint);
        Assert::stringContainsString('Cache-Control: private, no-store', $endpoint);
        Assert::stringContainsString('NovicePromotionStudentSummaryService', $endpoint);

        Assert::stringContainsString("'/alumnes/mostrar-alumne/'", $context);
        Assert::stringContainsString('LegacyInvoiceReadAuthorization::assertCanView', $context);

        Assert::stringContainsString('sendMsgValidatProfessorNovell.php', $validationJs);
        Assert::stringContainsString('csrfToken: obtenirCsrfValidarDescomptes()', $validationJs);
        Assert::stringContainsString('requestId: nouRequestIdValidarDescompte()', $validationJs);
        Assert::stringContainsString('method: "POST"', $validationJs);

        Assert::stringContainsString("!== 'POST'", $decisionEndpoint);
        Assert::stringContainsString('hash_equals', $decisionEndpoint);
        Assert::stringContainsString('assertSameOrigin', $decisionEndpoint);
        Assert::stringContainsString("consultaRolsEdiicio('/alumnes/validar-descomptes/')", $decisionEndpoint);
        Assert::stringContainsString('validar_docent_novell_requests', $decisionEndpoint);

        foreach (['NOV-', 'TOKEN_CIPHERTEXT', 'wrapping_key_hex', 'SIF_NOVICE_PROMO_WRAP_KEY_HEX'] as $secret) {
            if (str_contains($js, $secret)) {
                Assert::fail('UC-111 browser module must not expose promotional secret material: ' . $secret);
            }
        }
    }
}
