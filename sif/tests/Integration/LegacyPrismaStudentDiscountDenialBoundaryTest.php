<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class LegacyPrismaStudentDiscountDenialBoundaryTest
{
    public function testDeniedDocumentalDiscountUsesPrismaStudentPolicyV2HistoryBoundary(): void
    {
        $source = $this->readIntranet();
        $marker = strpos($source, 'UC-020/P05: després de denegar el dret documental');
        Assert::same(true, $marker !== false);

        $flow = substr($source, $marker, 6500);
        Assert::stringContainsString('WHERE DNI = ? AND ID <> ? AND DATA_INSC <= ?', $flow);
        Assert::stringContainsString('(A_PAGAR > 0 AND PAGAMENT > 0)', $flow);
        Assert::stringContainsString("OBSERVACIONS LIKE '%CURS REGAL%'", $flow);
        Assert::stringContainsString('OR GENERAT = 1', $flow);
        Assert::stringContainsString("UPPER(\`INSC CURS\`) != 'D'", $flow);
        Assert::stringContainsString("UPPER(\`INSC CURS\`) != 'M'", $flow);
    }

    public function testDeniedDocumentalDiscountUsesExactUniquePrismaStudentTariff(): void
    {
        $source = $this->readIntranet();
        $marker = strpos($source, '$cnsTarifaApV2 = "SELECT PREU FROM descomptes');
        Assert::same(true, $marker !== false);

        $flow = substr($source, $marker, 3500);
        Assert::stringContainsString('WHERE ID_PREU = ? AND TIPUS = 1', $flow);
        Assert::stringContainsString('DATAI <= CURRENT_TIMESTAMP', $flow);
        Assert::stringContainsString('(DATAF IS NULL OR CURRENT_TIMESTAMP <= DATAF)', $flow);
        Assert::stringContainsString("(CURS = 'TOTS' OR CURS = ? OR CURS = ?)", $flow);
        Assert::stringContainsString("(MES = 'TOTS' OR MES = ?)", $flow);
        Assert::stringContainsString('if ($stmt->num_rows() !== 1)', $flow);
        Assert::stringContainsString('tarifa Alumne PrisMa inexistent o ambigua', $flow);
    }

    public function testDeniedDocumentalDiscountFailsClosedOnIncoherentPrismaStudentPrice(): void
    {
        $source = $this->readIntranet();
        $marker = strpos($source, '$cnsTarifaApV2 = "SELECT PREU FROM descomptes');
        Assert::same(true, $marker !== false);

        $flow = substr($source, $marker, 4500);
        Assert::stringContainsString('(float) $preuCar <= 0', $flow);
        Assert::stringContainsString('(float) $preuDescAlumne <= 0', $flow);
        Assert::stringContainsString('(float) $preuDescAlumne >= (float) $preuCar', $flow);
        Assert::stringContainsString('$tipus = 1;', $flow);
        Assert::stringContainsString('$preuDescompte = $preuDescAlumne;', $flow);
    }

    public function testDeniedDocumentalDiscountPromotesEligiblePrismaStudentToPayableState(): void
    {
        $source = $this->readIntranet();
        $marker = strpos($source, '$tipus = 1;');
        Assert::same(true, $marker !== false);

        $flow = substr($source, $marker, 900);
        Assert::stringContainsString('$validDesc = 1;', $flow);
        Assert::stringContainsString('$preuDescompte = $preuDescAlumne;', $flow);

        $validPos = strpos($flow, '$validDesc = 1;');
        $pricePos = strpos($flow, '$preuDescompte = $preuDescAlumne;');
        Assert::same(true, $validPos !== false && $pricePos !== false && $validPos < $pricePos);
    }

    private function readIntranet(): string
    {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root . '/codi-drive/intranet-actual/Intranet.php');
        if ($content === false) {
            Assert::fail('Could not read Intranet.php.');
        }

        return $content;
    }
}
