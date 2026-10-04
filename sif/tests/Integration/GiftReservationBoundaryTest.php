<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class GiftReservationBoundaryTest
{
    public function testPreviewAndSubmitAreServerBound(): void
    {
        $preview = $this->read('codi-drive/web-actual/ajax/previsualitza_regal.php');
        $submit = $this->read('codi-drive/web-actual/ajax/enviarInscripcioRegal.php');

        Assert::stringContainsString('uc017_gift_preview', $preview);
        Assert::stringContainsString('random_int(', $preview);
        Assert::stringContainsString('random_bytes(32)', $preview);
        Assert::stringContainsString('SELECT ID FROM regal WHERE CODI=? LIMIT 1', $preview);
        Assert::stringContainsString("!== 'POST'", $submit);
        Assert::stringContainsString("hash_equals((string) \$preview['csrf'], \$previewToken)", $submit);
        Assert::stringContainsString("\$codiRegal = (string) \$preview['code'];", $submit);

        if (str_contains($submit, '$_GET[')) {
            Assert::fail('Gift reservation endpoint must not use GET.');
        }
        foreach (["\$_POST['preu']", "\$_POST['percentatge']", "\$_POST['hores']", "\$_POST['codiRegal']"] as $bad) {
            if (str_contains($submit, $bad)) {
                Assert::fail('Gift reservation trusts client authority: ' . $bad);
            }
        }
    }

    public function testReservationRepricesSerializesAndPersistsBeforeMail(): void
    {
        $source = $this->read('codi-drive/web-actual/RegalCurs.php');
        $start = strpos($source, 'public function enviarInscripcioRegal(');
        $end = strpos($source, 'private function __mostrarClaseTamanyNomCurs', $start ?: 0);
        $method = ($start !== false && $end !== false) ? substr($source, $start, $end - $start) : '';

        Assert::stringContainsString('obtenirPreuHoresNomCursRegal($codiCurs)', $method);
        Assert::stringContainsString('INVALID_AUTHORITATIVE_GIFT_PRICING', $method);
        Assert::stringContainsString('SELECT GET_LOCK(?, 5)', $method);
        Assert::stringContainsString('GIFT_CODE_ALREADY_BOUND_TO_DIFFERENT_RESERVATION', $method);

        $insert = strpos($method, 'INSERT INTO regal');
        $mail = strpos($method, 'sendMessage()');
        Assert::same(true, $insert !== false && $mail !== false && $insert < $mail);
    }

    public function testCutoverStopsPrepaymentPublicGiftPdf(): void
    {
        $source = $this->read('codi-drive/web-actual/RegalCurs.php');
        $start = strpos($source, 'public function enviarInscripcioRegal(');
        $end = strpos($source, 'private function __mostrarClaseTamanyNomCurs', $start ?: 0);
        $method = ($start !== false && $end !== false) ? substr($source, $start, $end - $start) : '';

        Assert::stringContainsString('SIF_REDSYS_GIFT_CUTOVER_ENABLED', $method);
        Assert::stringContainsString('if (!$giftCutoverEnabled)', $method);
        Assert::stringContainsString('targetes-regal/', $method);

        $guard = strpos($method, 'if (!$giftCutoverEnabled)');
        $write = strpos($method, 'file_put_contents($filename_digital, $pdf_digital)');
        Assert::same(true, $guard !== false && $write !== false && $guard < $write);
    }

    public function testSifPaidProjectionIsAcceptedAtPaymentAndRedemptionBoundaries(): void
    {
        $payment = $this->read('codi-drive/pay-prisma-cat-canvis-verifactu/PagamentRegalAutomatic.php');
        $redeem = $this->read('codi-drive/web-actual/BescanviaRegal.php');

        Assert::stringContainsString('private $sifPaid = false', $payment);
        Assert::stringContainsString('if (!$this->estaPagat())', $payment);
        Assert::stringContainsString('FACT_REL, USAT, OBSERVACIONS', $redeem);
        Assert::stringContainsString('if ($factura==0 && !$sifPaid)', $redeem);
    }

    private function read(string $relativePath): string
    {
        $content = file_get_contents(dirname(__DIR__, 3) . '/' . $relativePath);
        if ($content === false) {
            Assert::fail('Could not read ' . $relativePath);
        }
        return $content;
    }
}
