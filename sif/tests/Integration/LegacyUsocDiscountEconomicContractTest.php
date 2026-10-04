<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class LegacyUsocDiscountEconomicContractTest
{
    public function testUsocEnrollmentStartsAtAdvanceAmountAndValidationPreservesPricingSemantics(): void
    {
        $root = dirname(__DIR__, 3);
        $affiliate = file_get_contents(
            $root . '/codi-drive/web-actual/ajax/enviarInscripcioAfiliat.php'
        );
        $intranet = file_get_contents(
            $root . '/codi-drive/intranet-actual/Intranet.php'
        );

        if ($affiliate === false || $intranet === false) {
            Assert::fail('Could not read legacy USOC enrollment/validation sources');
        }

        Assert::stringContainsString("\$tipusDescompte = 4;", $affiliate);
        Assert::stringContainsString("\$validDesc = 0;", $affiliate);
        Assert::stringContainsString("\$preuDescompte = \$anticipiPreu;", $affiliate);
        Assert::stringContainsString('A_PAGAR, USUARI, IDPAG, PERENNE, CONEGUT, TIPUS_DESC, VALID_DESC', $affiliate);

        Assert::stringContainsString(
            'UPDATE inscripcions SET VALID_DESC=? WHERE ID=?',
            $intranet
        );
        Assert::stringContainsString(
            'UPDATE inscripcions SET TIPUS_DESC=?, VALID_DESC=?, A_PAGAR=? WHERE ID=?',
            $intranet
        );
        Assert::stringContainsString(
            'public function sendMsgValidatCurosDescomptes',
            $intranet
        );
    }
}
