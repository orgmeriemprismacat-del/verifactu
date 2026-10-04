<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class LegacyPrismaStudentPaymentStateBoundaryTest
{
    public function testDeniedDocumentalDiscountBecomingPrismaStudentIsMarkedPayable(): void
    {
        $source = $this->read('codi-drive/intranet-actual/Intranet.php');
        $marker = strpos($source, '$tipus = 1;');
        Assert::same(true, $marker !== false);

        $flow = substr($source, $marker, 1000);
        Assert::stringContainsString('$validDesc = 1;', $flow);
        Assert::stringContainsString('$preuDescompte = $preuDescAlumne;', $flow);
    }

    public function testWebTransferInstructionsRequirePayableDiscountState(): void
    {
        $source = $this->read('codi-drive/web-actual/PagamentCursAutomatic.php');

        $marker = strpos($source, '$vistaPag .= $this->__mostrarPagamentTargeta(1);');
        Assert::same(true, $marker !== false);
        $flow = substr($source, $marker, 500);

        Assert::stringContainsString(
            'if ( $this->validDesc == 1 )',
            $flow
        );
        Assert::stringContainsString(
            '$vistaPag .= $this->__mostrarPagamentTransferencia(1);',
            $flow
        );
    }

    public function testCandidatePayBridgeTransferInstructionsUseSamePayableGate(): void
    {
        $source = $this->read('codi-drive/pay-prisma-cat-canvis-verifactu/PagamentCursAutomatic.php');

        $marker = strpos($source, '$mostrar .= $this->__mostrarPagamentTargeta(1);');
        Assert::same(true, $marker !== false);
        $flow = substr($source, $marker, 500);

        Assert::stringContainsString(
            'if ( $this->validDesc == 1 )',
            $flow
        );
        Assert::stringContainsString(
            '$mostrar .= $this->__mostrarPagamentTransferencia(1);',
            $flow
        );
    }

    public function testSifPrismaStudentCardIntentAlsoRequiresPayableState(): void
    {
        $source = $this->read('sif/src/Service/RedsysCoursePaymentIntentService.php');

        Assert::stringContainsString(
            "if ((int) (\$inscription['VALID_DESC'] ?? 0) !== 1)",
            $source
        );
        Assert::stringContainsString(
            'Alumne PrisMa discount is not in a payable state.',
            $source
        );
    }

    private function read(string $relative): string
    {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root . '/' . $relative);
        if ($content === false) {
            Assert::fail('Could not read ' . $relative);
        }

        return $content;
    }
}
