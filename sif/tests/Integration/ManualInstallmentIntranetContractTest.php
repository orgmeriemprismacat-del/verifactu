<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualInstallmentIntranetContractTest
{
    public function testLegacyPaymentsUiCarriesRealInscriptionAndExternalReceiptToSif(): void
    {
        $root = dirname(__DIR__, 3);

        $page = $this->read($root . '/codi-drive/intranet-actual/alumnes-pagaments.php');
        $render = $this->read($root . '/codi-drive/intranet-actual/Intranet.php');
        $js = $this->read($root . '/codi-drive/intranet-actual/js/alumnes-pagaments.js');
        $endpoint = $this->read($root . '/codi-drive/intranet-actual/ajax/alumnes/efectuarPagament.php');

        Assert::stringContainsString('sif-installment-enforced', $page);

        Assert::stringContainsString("REFERÈNCIA", $render);
        Assert::stringContainsString("reference-".$idCercat", $render);
        Assert::stringContainsString("idInsc-".$idCercat", $render);

        Assert::stringContainsString("sifInstallmentEnforced", $js);
        Assert::stringContainsString("$('#idInsc-' + idTipus)", $js);
        Assert::stringContainsString("$('#reference-' + idTipus)", $js);
        Assert::stringContainsString("var button = $('#upd-insc-' + idTipus)", $js);
        Assert::stringContainsString("idInsc: idInscSif", $js);
        Assert::stringContainsString("externalReference: externalReference", $js);

        if (str_contains($js, "var button = $('#upd-inscripcio-' + idTipus)")) {
            Assert::fail('UC-023 retry identity must attach to the actual upd-insc-* element');
        }

        Assert::stringContainsString("$idTipusRaw = $_POST['id']", $endpoint);
        Assert::stringContainsString("$idInscSifRaw = $_POST['idInsc']", $endpoint);
        Assert::stringContainsString("'id_insc' => $idInscSif", $endpoint);
        Assert::stringContainsString("strtoupper($banc) === 'TPV'", $endpoint);
        Assert::stringContainsString("$sifInput['ds_order'] = $externalReference", $endpoint);
        Assert::stringContainsString("$sifInput['reference'] = $externalReference", $endpoint);
        Assert::stringContainsString("SIF_INSTALLMENT_PAYMENT_ENFORCED", $endpoint);
        Assert::stringContainsString(
            "cal emetre la factura abans de registrar el cobrament al SIF",
            $endpoint
        );
        Assert::stringContainsString("if ($tipus === '')", $endpoint);

        if (str_contains($endpoint, "if ($tipus === '' || $numFact === '')")) {
            Assert::fail('Legacy fallback must not require an existing invoice before cutover');
        }

        // Compatibility boundary: the legacy implementation still receives the
        // historical row/payment identifier only when the SIF cutover is disabled.
        Assert::stringContainsString(
            "$_SESSION['intranet']->efectuarPagament(\n\t\t\t$idTipus",
            $endpoint
        );
    }

    private function read(string $path): string
    {
        $source = file_get_contents($path);
        if ($source === false) {
            Assert::fail('Could not read UC-023 intranet contract file: ' . $path);
        }

        return $source;
    }
}
