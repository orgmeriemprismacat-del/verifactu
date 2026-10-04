<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class Uc002AuthoritativeBridgeBoundaryTest
{
    public function testIntranetBridgeUsesCsrfSignedSifAndStableRequestId(): void
    {
        $root = dirname(__DIR__, 3);
        $js = file_get_contents($root . '/codi-drive/intranet-actual/js/alumnes-pagaments.js');
        $proxy = file_get_contents($root . '/codi-drive/intranet-actual/ajax/alumnes/sifPagamentFactura.php');
        $access = file_get_contents($root . '/codi-drive/intranet-actual/SifExistingInvoicePaymentAccess.php');
        $client = file_get_contents($root . '/codi-drive/intranet-actual/SifInternalApiClient.php');

        if (!is_string($js) || !is_string($proxy) || !is_string($access) || !is_string($client)) {
            Assert::fail('Could not load UC-002 authoritative intranet bridge');
        }

        Assert::stringContainsString('sifPagamentFacturaToken.php', $js);
        Assert::stringContainsString('sifPagamentFactura.php', $js);
        Assert::stringContainsString('sessionStorage.getItem(storageKey)', $js);
        Assert::stringContainsString('window.crypto.randomUUID', $js);
        Assert::stringContainsString("'X-CSRF-Token': uc002CsrfToken", $js);

        Assert::stringContainsString('SifExistingInvoicePaymentAccess::assertCsrf', $proxy);
        Assert::stringContainsString("'INTRANET|UC002|REQ:' . $requestId", $proxy);
        Assert::stringContainsString('registerExistingInvoicePayment', $proxy);
        Assert::stringContainsString("'PENDING_RETRY'", $proxy);
        Assert::stringContainsString("'SYNCED'", $proxy);
        Assert::stringContainsString('flux Redsys autoritatiu', $proxy);
        Assert::stringContainsString("['caixa', 'bbva']", $proxy);

        Assert::stringContainsString("getenv('SIF_UC002_AUTHORITATIVE')", $access);
        Assert::stringContainsString('SIF_INTERNAL_PAYMENT_URL', $client);
        Assert::stringContainsString('SIF_INTERNAL_PAYMENT_SIGNED_PATH', $client);
    }

    public function testLegacyMutationFailsClosedWhenAuthoritativeModeIsEnabled(): void
    {
        $root = dirname(__DIR__, 3);
        $current = file_get_contents($root . '/codi-drive/intranet-actual/ajax/alumnes/efectuarPagament.php');
        $verifactu = file_get_contents(
            $root . '/codi-drive/intranet-nova-canvis-verifactu/ajax/alumnes/efectuarPagament.php'
        );

        if (!is_string($current) || !is_string($verifactu)) {
            Assert::fail('Could not load UC-002 legacy payment endpoints');
        }

        Assert::same($current, $verifactu);
        Assert::stringContainsString("getenv('SIF_UC002_AUTHORITATIVE')", $current);
        Assert::stringContainsString("$efact === '1'", $current);
        Assert::stringContainsString('circuit SIF autoritatiu', $current);
    }

    public function testLegacyProjectionIsAbsoluteAndTransactional(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents(
            $root . '/codi-drive/intranet-actual/Uc002LegacyPaymentProjectionApplier.php'
        );

        if (!is_string($source)) {
            Assert::fail('Could not load UC-002 legacy projection applier');
        }

        Assert::stringContainsString('begin_transaction()', $source);
        Assert::stringContainsString('FOR UPDATE', $source);
        Assert::stringContainsString('SET PAGAMENT = ?', $source);
        Assert::stringContainsString('A_PAGAR no coincideix', $source);
        Assert::stringContainsString('SIF_PAYMENT ', $source);
        Assert::same(false, str_contains($source, 'PAGAMENT = PAGAMENT +'));
    }

    public function testSifEndpointExposesExistingInvoiceCommandAndProjection(): void
    {
        $root = dirname(__DIR__, 2);
        $endpoint = file_get_contents($root . '/public/api/payments/register.php');

        if (!is_string($endpoint)) {
            Assert::fail('Could not load SIF payment endpoint');
        }

        Assert::stringContainsString("'register_existing_invoice'", $endpoint);
        Assert::stringContainsString('ExistingInvoicePaymentCommandService', $endpoint);
        Assert::stringContainsString('ExistingInvoiceLegacyProjectionService', $endpoint);
        Assert::stringContainsString('ManualPaymentService', $endpoint);
    }
}
