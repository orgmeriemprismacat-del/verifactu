<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class UsocIntranetUiContractTest
{
    public function testStandaloneUsocIntranetUiUsesServerSideSignedClientAndCsrf(): void
    {
        $root = dirname(__DIR__, 3);
        $page = file_get_contents($root . '/codi-drive/intranet-actual/alumnes-usoc-financament.php');
        $js = file_get_contents($root . '/codi-drive/intranet-actual/js/alumnes-usoc-financament.js');
        $controller = file_get_contents($root . '/codi-drive/intranet-actual/ajax/alumnes/usocFinancament.php');
        $client = file_get_contents($root . '/codi-drive/intranet-actual/SifInternalUsocClient.php');
        $modalPage = file_get_contents($root . '/codi-drive/intranet-actual/alumnes-mostrar-alumne.php');
        $modalJs = file_get_contents($root . '/codi-drive/intranet-actual/js/alumnes-mostrar-alumne-usoc.js');
        $modalBridge = file_get_contents($root . '/codi-drive/intranet-actual/ajax/alumnes/sifUsoc.php');
        $context = file_get_contents($root . '/codi-drive/intranet-actual/LegacyUsocContext.php');

        if ($page === false || $js === false || $controller === false || $client === false || $modalPage === false || $modalJs === false || $modalBridge === false || $context === false) {
            Assert::fail('Could not read USOC intranet UI contract files');
        }

        Assert::stringContainsString('csrf_usoc_financament', $page);
        Assert::stringContainsString('csrf-token-usoc-financament', $page);
        Assert::stringContainsString('random_bytes(32)', $page);
        Assert::stringContainsString('Finançament USOC', $page);
        Assert::stringContainsString('SIF_USOC_UI_ENABLED', $page);
        Assert::stringContainsString('usoc-emetre-entitat', $page);
        Assert::stringContainsString('usoc-registrar-cobrament', $page);

        Assert::stringContainsString("method: 'POST'", $js);
        Assert::stringContainsString('csrfToken: csrfToken()', $js);
        Assert::stringContainsString("post('view'", $js);
        Assert::stringContainsString("post('issue_entity_invoice'", $js);
        Assert::stringContainsString("post('register_entity_payment'", $js);
        Assert::stringContainsString("post('reconcile'", $js);

        Assert::stringContainsString("REQUEST_METHOD", $controller);
        Assert::stringContainsString("!== 'POST'", $controller);
        Assert::stringContainsString('hash_equals', $controller);
        Assert::stringContainsString('SifInternalUsocClient', $controller);
        Assert::stringContainsString('getRols()', $controller);
        Assert::stringContainsString('getUsuari()->get()', $controller);
        Assert::stringContainsString('issueEntityInvoice', $controller);
        Assert::stringContainsString('registerEntityPayment', $controller);
        Assert::stringContainsString('LegacyUsocContext::open()', $controller);
        Assert::stringContainsString('assertSameOrigin', $controller);
        Assert::stringContainsString('assertCanEdit', $controller);

        Assert::stringContainsString('SIF_USOC_UI_ENABLED', $modalPage);
        Assert::stringContainsString('alumnes-mostrar-alumne-usoc.js', $modalPage);
        Assert::stringContainsString('shown.bs.modal', $modalJs);
        Assert::stringContainsString('capabilities.manage === true', $modalJs);
        Assert::stringContainsString("cp: value('#uc013-billing-postal-code')", $modalJs);
        Assert::stringContainsString('register_entity_payment', $modalJs);
        Assert::stringContainsString('LegacyUsocContext::open()', $modalBridge);
        Assert::stringContainsString('assertSameOrigin', $modalBridge);
        Assert::stringContainsString("'/alumnes/mostrar-alumne/'", $context);

        if (str_contains($js, 'X-SIF-Signature') || str_contains($page, 'SIF_INTERNAL_API_SECRET')) {
            Assert::fail('HMAC signing material must remain server-side.');
        }

        Assert::stringContainsString('X-SIF-Signature', $client);
        Assert::stringContainsString("hash_hmac('sha256'", $client);
    }
}
