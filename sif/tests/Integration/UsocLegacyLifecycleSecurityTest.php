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
        $preview = file_get_contents($root . '/codi-drive/intranet-actual/ajax/alumnes/sifCanviCursPreview.php');
        $lifecyclePreview = file_get_contents($root . '/codi-drive/intranet-actual/ajax/alumnes/sifUsocLifecyclePreview.php');
        $lifecycleJs = file_get_contents($root . '/codi-drive/intranet-actual/js/alumnes-usoc-lifecycle-preview.js');

        if ($page === false || $js === false || $minJs === false || $change === false || $cancel === false || $guard === false || $preview === false || $lifecyclePreview === false || $lifecycleJs === false) {
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
            Assert::stringContainsString('if ($status >= 500)', $endpoint);
            Assert::stringContainsString('no s’ha pogut completar l’operació', $endpoint);

            if (str_contains($endpoint, '$_GET[')) {
                Assert::fail('Lifecycle mutation endpoints must not read mutation parameters from GET.');
            }
        }

        Assert::stringContainsString('method: "POST"', $js);
        Assert::stringContainsString('csrfToken : obtenirCsrfAlumnesLifecycle()', $js);
        Assert::stringContainsString('pendent : pendent', $js);
        Assert::stringContainsString('function mostrarErrorLifecycleAlumne', $js);
        Assert::stringContainsString('jqXHR.status === 409', $js);

        Assert::stringContainsString('realitzarCanviCurs_CanviCurs.php",method:"POST"', $minJs);
        Assert::stringContainsString('confirmacioBaixa_DonarBaixa.php",method:"POST"', $minJs);
        Assert::stringContainsString('csrfToken:obtenirCsrfAlumnesLifecycle()', $minJs);
        Assert::stringContainsString('function mostrarErrorLifecycleAlumne', $minJs);

        Assert::stringContainsString("['course_change', 'cancellation']", $guard);
        Assert::stringContainsString('TIPUS_DESC', $guard);
        Assert::stringContainsString('lifecycleGuard', $guard);
        Assert::stringContainsString('USOC', $guard);

        Assert::stringContainsString('LegacyUsocLifecycleGuard', $preview);
        Assert::stringContainsString("source_enrollment_id", $preview);
        Assert::stringContainsString("'course_change'", $preview);
        Assert::stringContainsString('http_response_code(', $preview);
        Assert::stringContainsString('$status >= 100 && $status <= 599 ? $status : 502', $preview);
        Assert::stringContainsString('$exception->getCode()', $preview);
        Assert::stringContainsString('$code >= 400 && $code <= 599', $preview);
        Assert::stringContainsString('$status >= 500', $preview);

        Assert::stringContainsString('alumnes-usoc-lifecycle-preview.js', $page);
        Assert::stringContainsString("REQUEST_METHOD", $lifecyclePreview);
        Assert::stringContainsString("!== 'POST'", $lifecyclePreview);
        Assert::stringContainsString('assertSameOrigin', $lifecyclePreview);
        Assert::stringContainsString('assertCanEdit', $lifecyclePreview);
        Assert::stringContainsString('csrf_alumnes_lifecycle', $lifecyclePreview);
        Assert::stringContainsString('LegacyUsocLifecycleGuard', $lifecyclePreview);
        Assert::stringContainsString('->inspect(', $lifecyclePreview);
        Assert::stringContainsString('->plan(', $lifecyclePreview);
        Assert::stringContainsString('USOC_CANCELLATION_EXECUTION_COMPLETED', $lifecyclePreview);
        Assert::stringContainsString('usoc_cancellation_execution', $lifecyclePreview);

        Assert::stringContainsString('#modalDonarBaixa .confirma-baixa', $lifecycleJs);
        Assert::stringContainsString("'cancellation'", $lifecycleJs);
        Assert::stringContainsString('sifUsocLifecyclePreview.php', $lifecycleJs);
        Assert::stringContainsString('X-CSRF-Token', $lifecycleJs);
        Assert::stringContainsString('payer_snapshot', $lifecycleJs);
        Assert::stringContainsString('execute_cancellation', $lifecycleJs);
        Assert::stringContainsString('DEFER_FISCAL', $lifecycleJs);
        Assert::stringContainsString('DEFER_REFUND', $lifecycleJs);
        Assert::stringContainsString('Registrar decisió i continuar baixa', $lifecycleJs);
        Assert::stringContainsString('allowLegacyCancellationClick = true', $lifecycleJs);

        Assert::stringContainsString('usoc_cancellation_execution', $cancel);
        Assert::stringContainsString('assertMayUseLegacyMutation(', $cancel);
        Assert::stringContainsString('$usocRequestId !== \'\' ? $usocRequestId : null', $cancel);
        Assert::stringContainsString("unset(\$_SESSION['usoc_cancellation_execution'][\$idInsc])", $cancel);
    }
}
