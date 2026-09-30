<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class UsocLegacyLifecycleSecurityTest
{
    public function testCourseChangeAndCancellationArePostCsrfAndFailClosedForUsoc(): void
    {
        $root = dirname(__DIR__, 3);
        $page = file_get_contents($root . '/codi-drive/intranet-actual/alumnes-mostrar-alumne.php');
        $js = file_get_contents($root . '/codi-drive/intranet-actual/js/alumnes-mostrar-alumne.js');
        $minJs = file_get_contents($root . '/codi-drive/intranet-actual/js/alumnes-mostrar-alumne.min.js');
        $change = file_get_contents(
            $root . '/codi-drive/intranet-actual/ajax/alumnes/realitzarCanviCurs_CanviCurs.php'
        );
        $cancel = file_get_contents(
            $root . '/codi-drive/intranet-actual/ajax/alumnes/confirmacioBaixa_DonarBaixa.php'
        );
        $guard = file_get_contents($root . '/codi-drive/intranet-actual/LegacyUsocLifecycleGuard.php');

        if ($page === false || $js === false || $minJs === false || $change === false || $cancel === false || $guard === false) {
            Assert::fail('Could not read USOC lifecycle security files');
        }

        Assert::stringContainsString('csrf_alumnes_lifecycle', $page);
        Assert::stringContainsString('csrf-token-alumnes-lifecycle', $page);
        Assert::stringContainsString('random_bytes(32)', $page);

        foreach ([$change, $cancel] as $endpoint) {
            Assert::stringContainsString("REQUEST_METHOD", $endpoint);
            Assert::stringContainsString("!== 'POST'", $endpoint);
            Assert::stringContainsString('hash_equals', $endpoint);
            Assert::stringContainsString('csrf_alumnes_lifecycle', $endpoint);
            Assert::stringContainsString('LegacyInvoiceMutationAuthorization::assertSameOrigin', $endpoint);
            Assert::stringContainsString('LegacyInvoiceMutationAuthorization::assertCanEdit', $endpoint);
            Assert::stringContainsString('LegacyUsocLifecycleGuard', $endpoint);

            if (str_contains($endpoint, '$_GET[')) {
                Assert::fail('Lifecycle mutation endpoints must not read mutation parameters from GET.');
            }
        }

        Assert::stringContainsString('method: "POST"', $js);
        Assert::stringContainsString('csrfToken : obtenirCsrfAlumnesLifecycle()', $js);
        Assert::stringContainsString('pendent : pendent', $js);

        Assert::stringContainsString('realitzarCanviCurs_CanviCurs.php",method:"POST"', $minJs);
        Assert::stringContainsString('confirmacioBaixa_DonarBaixa.php",method:"POST"', $minJs);
        Assert::stringContainsString('csrfToken:obtenirCsrfAlumnesLifecycle()', $minJs);

        Assert::stringContainsString("['course_change', 'cancellation']", $guard);
        Assert::stringContainsString('TIPUS_DESC', $guard);
        Assert::stringContainsString('lifecycleGuard', $guard);
        Assert::stringContainsString('USOC', $guard);
    }
}
