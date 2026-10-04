<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualTransferIntranetAdapterTest
{
    public function testIntranetAdapterUsesLiveRolesCsrfAndServerToServerSignature(): void
    {
        $root = dirname(__DIR__, 3);

        $guard = file_get_contents(
            $root . '/codi-drive/intranet-nova-canvis-verifactu/SifPaymentSessionGuard.php'
        );
        $endpoint = file_get_contents(
            $root . '/codi-drive/intranet-nova-canvis-verifactu/ajax/alumnes/registrarTransferenciaSif.php'
        );
        $token = file_get_contents(
            $root . '/codi-drive/intranet-nova-canvis-verifactu/ajax/alumnes/obtenirTokenPagamentSif.php'
        );
        $js = file_get_contents(
            $root . '/codi-drive/intranet-nova-canvis-verifactu/js/alumnes-pagaments.js'
        );

        foreach ([$guard, $endpoint, $token, $js] as $source) {
            if ($source === false) {
                Assert::fail('Could not read UC-022 intranet adapter source');
            }
        }

        Assert::stringContainsString('SELECT ROLS FROM usuaris', $guard);
        Assert::stringContainsString('replaceRols', $guard);
        Assert::stringContainsString('hash_equals', $guard);
        Assert::stringContainsString('sif_uc022_payment_csrf', $guard);

        Assert::stringContainsString("REQUEST_METHOD", $endpoint);
        Assert::stringContainsString("'POST'", $endpoint);
        Assert::stringContainsString('HTTP_X_CSRF_TOKEN', $endpoint);
        Assert::stringContainsString('SifManualTransferGateway::fromEnvironment()', $endpoint);
        Assert::stringContainsString('external_bank_event_id', $endpoint);
        Assert::stringContainsString('Card payments must use the Redsys flow', $endpoint);

        Assert::stringContainsString('csrf_token', $token);
        Assert::stringContainsString('Cache-Control: no-store', $token);

        Assert::stringContainsString('ID MOVIMENT BANCARI', $js);
        Assert::stringContainsString('registrarTransferenciaSif.php', $js);
        Assert::stringContainsString('"X-CSRF-Token": csrfToken', $js);
        Assert::stringContainsString('external_bank_event_id: externalBankEventId', $js);
        Assert::stringContainsString("res.status == 'PENDING_RETRY'", $js);
        Assert::stringContainsString("res.status == 'CONFLICT'", $js);

        if (str_contains($endpoint, 'SifLegacyPaymentProjection')
            || str_contains($endpoint, 'ConnexioWeb')
        ) {
            Assert::fail('UC-022 intranet adapter must not project legacy state locally; SIF owns projection.');
        }

        if (str_contains($endpoint, '$_GET')) {
            Assert::fail('UC-022 secure transfer adapter must not mutate through GET.');
        }
    }
}
