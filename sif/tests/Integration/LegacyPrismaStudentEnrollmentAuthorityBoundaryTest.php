<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class LegacyPrismaStudentEnrollmentAuthorityBoundaryTest
{
    public function testServerAuthoritativePrismaStudentPriceIsNotOverwrittenBeforeInsert(): void
    {
        $source = $this->read('codi-drive/web-actual/ajax/enviarInscripcio.php');

        $serverGuard = strpos($source, 'if ($tipusDescompte == 1) {');
        $serverPrice = strpos($source, '$preuDescompte = (float) $preuApServidor;', $serverGuard ?: 0);
        $insert = strpos($source, 'INSERT INTO inscripcions', $serverPrice ?: 0);

        Assert::same(true, $serverGuard !== false);
        Assert::same(true, $serverPrice !== false);
        Assert::same(true, $insert !== false);
        Assert::same(true, $serverGuard < $serverPrice && $serverPrice < $insert);

        $authoritativeTail = substr($source, $serverPrice, $insert - $serverPrice);
        if (str_contains($authoritativeTail, '$preuDescompte = $numPreuDescompte->obtenirNumero();')) {
            Assert::fail('Browser-supplied Alumne PrisMa price must not overwrite the server-authoritative tariff before INSERT.');
        }

        Assert::stringContainsString('$preuDescompte, $usuariBD, $idPag', substr($source, $insert));
    }

    public function testPrismaStudentEnrollmentRejectsPromotionCombinationBeforePersistence(): void
    {
        $source = $this->read('codi-drive/web-actual/ajax/enviarInscripcio.php');
        $serverGuard = strpos($source, 'if ($tipusDescompte == 1) {');
        $insert = strpos($source, 'INSERT INTO inscripcions', $serverGuard ?: 0);

        Assert::same(true, $serverGuard !== false && $insert !== false);

        $guardedFlow = substr($source, $serverGuard, $insert - $serverGuard);
        Assert::stringContainsString('$promocioATrobadaplicada', $guardedFlow);
        Assert::stringContainsString('$promocioAplicada', $guardedFlow);
        Assert::stringContainsString('http_response_code(409)', $guardedFlow);
    }

    public function testCourseTypeComesFromServerMetadataBeforeFreeCourseOverride(): void
    {
        $source = $this->read('codi-drive/web-actual/ajax/enviarInscripcio.php');

        Assert::same(false, str_contains($source, '$tipusCurs = $_GET[\'tipusCurs\']'));
        Assert::stringContainsString('SELECT TITOL, TIPUS_CURS FROM informacio', $source);
        Assert::stringContainsString('$tipusCurs = (string) $tipusCursServidor;', $source);

        $serverAssignment = strpos($source, '$tipusCurs = (string) $tipusCursServidor;');
        $freeOverride = strpos($source, 'if ( $tipusCurs == \'S\' ) $preuDescompte = 0;');
        Assert::same(true, $serverAssignment !== false && $freeOverride !== false && $serverAssignment < $freeOverride);
    }

    public function testPreviewEligibilityMatchesPolicyV2AndDoesNotUseUnpaidInvoiceShortcut(): void
    {
        $source = $this->read('codi-drive/web-actual/inc/buscarAlumnePrisMa.php');

        Assert::stringContainsString('(A_PAGAR>0 AND PAGAMENT>0)', $source);
        Assert::stringContainsString("OBSERVACIONS LIKE '%CURS REGAL%'", $source);
        Assert::stringContainsString('(GENERAT=1)', $source);
        Assert::stringContainsString("UPPER(`INSC CURS`)!='D'", $source);
        Assert::stringContainsString("UPPER(`INSC CURS`)!='M'", $source);
        Assert::same(false, str_contains($source, 'FACTURA_RELACIONADA'));
    }

    public function testPricePreviewHasSafeFallbackWhenNoDiscountCandidateExists(): void
    {
        $source = $this->read('codi-drive/web-actual/ajax/calcularPreu.php');

        Assert::stringContainsString('$descomptes = [];', $source);
        Assert::stringContainsString('$i = 0;', $source);
        Assert::stringContainsString('while ($pos<$i && !$trobat)', $source);
        Assert::stringContainsString("if (!\$trobat)\n      \$mostrar = '0|0|0|0';", $source);
    }

    public function testPublicEnrollmentCommandUsesPostAndDoesNotSendClientCourseType(): void
    {
        $endpoint = $this->read('codi-drive/web-actual/ajax/enviarInscripcio.php');
        $js = $this->read('codi-drive/web-actual/js1619773569/mostrarInscripcions.min.js');

        Assert::stringContainsString("REQUEST_METHOD", $endpoint);
        Assert::stringContainsString("!== 'POST'", $endpoint);
        Assert::same(false, str_contains($endpoint, '$_GET['));

        $start = strpos($js, 'var sendInscr = $.ajax({');
        $end = strpos($js, 'sendInscr.done', $start === false ? 0 : $start);
        Assert::same(true, $start !== false && $end !== false && $end > $start);

        $sendBlock = substr($js, $start, $end - $start);
        Assert::stringContainsString('method: "POST"', $sendBlock);
        Assert::same(false, str_contains($sendBlock, 'tipusCurs: tipusCurs'));
        Assert::stringContainsString('dni: documentacio', $sendBlock);
        Assert::stringContainsString('email: email', $sendBlock);
    }

    public function testPricePreviewDoesNotMutateEnrollmentPaymentOrFiscalState(): void
    {
        $source = $this->read('codi-drive/web-actual/ajax/calcularPreu.php')
            . "\n"
            . $this->read('codi-drive/web-actual/inc/buscarAlumnePrisMa.php');

        if (preg_match('/\\b(?:INSERT|UPDATE|DELETE|REPLACE)\\s+(?:INTO\\s+)?(?:inscripcions|factura|payment_transaction|commercial_operation|discount_validation)\\b/i', $source) === 1) {
            Assert::fail('UC-020 price preview must remain read-only for commercial, payment and fiscal state.');
        }

        Assert::same(false, str_contains($source, 'InvoiceService'));
        Assert::same(false, str_contains($source, 'registerPayment('));
        Assert::same(false, str_contains($source, 'issueInvoice('));
    }

    private function read(string $relativePath): string
    {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root . '/' . $relativePath);
        if ($content === false) {
            Assert::fail('Could not read ' . $relativePath);
        }

        return $content;
    }
}
