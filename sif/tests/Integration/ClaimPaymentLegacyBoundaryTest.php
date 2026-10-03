<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ClaimPaymentLegacyBoundaryTest
{
    public function testRecordatoriPageDoesNotKeepStaleUndefinedHandler(): void
    {
        $root = dirname(__DIR__, 3);
        $path = $root . '/codi-drive/intranet-actual/js/facturacio-recordatori-pagament-final.js';
        $js = file_get_contents($path);

        if ($js === false) {
            Assert::fail('Unable to read recordatori JS');
        }

        Assert::same(false, str_contains($js, '#upd-baixes'));
        Assert::same(false, str_contains($js, 'confirmaReclamacio()'));
        Assert::stringContainsString('#confirma-reclamacio', $js);
        Assert::stringContainsString('confirmaRecordatori', $js);
    }

    public function testMorosityEntityMailBranchesUseLoadedCourseName(): void
    {
        $root = dirname(__DIR__, 3);
        $path = $root . '/codi-drive/intranet-actual/Intranet.php';
        $php = file_get_contents($path);

        if ($php === false) {
            Assert::fail('Unable to read Intranet.php');
        }

        $wrong = <<<'PHP'
$this->__sendMsgRespEntity_EntityClaimPayDefaulter($nom, $cognoms,
			$correu, $titol, $dataf, $idpag, $tipusInsc, $entitat);
PHP;
        $correct = <<<'PHP'
$this->__sendMsgRespEntity_EntityClaimPayDefaulter($nom, $cognoms,
			$correu, $nomCurs, $dataf, $idpag, $tipusInsc, $entitat);
PHP;

        Assert::same(0, substr_count($php, $wrong));
        Assert::same(2, substr_count($php, $correct));
    }
}
