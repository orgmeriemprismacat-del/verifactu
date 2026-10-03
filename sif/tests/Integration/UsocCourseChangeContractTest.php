<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class UsocCourseChangeContractTest
{
    public function testLegacyRecalculatesTargetUsocPriceAndCarriesValidationState(): void
    {
        $root = dirname(__DIR__, 3);
        $intranet = file_get_contents($root . '/codi-drive/intranet-actual/Intranet.php');

        if ($intranet === false) {
            Assert::fail('Could not read legacy Intranet.php for USOC course change contract');
        }

        Assert::stringContainsString(
            '$validDesc == 1 && ( $tipusDesc == 4 || $tipusDesc == 5',
            $intranet
        );
        Assert::stringContainsString(
            'SELECT PREU FROM descomptes',
            $intranet
        );
        Assert::stringContainsString(
            '$stmt->bind_param("ddssss", $tipusDesc, $idPreu, $curs, $mes, $dataInsc, $dataInsc);',
            $intranet
        );
        Assert::stringContainsString(
            '$apagarC2 = $apagarC + $despesesC;',
            $intranet
        );
        Assert::stringContainsString(
            '$idPagBD, $tipusDesc, $validDesc);',
            $intranet
        );
    }

    public function testSifModelsEntityAmountAsUsocDiscountDifference(): void
    {
        $root = dirname(__DIR__, 3);
        $builder = file_get_contents(
            $root . '/sif/src/Service/LegacyUsocInvoicePayloadBuilder.php'
        );

        if ($builder === false) {
            Assert::fail('Could not read LegacyUsocInvoicePayloadBuilder');
        }

        Assert::stringContainsString(
            '$entityAmount = $this->optional($snapshot[\'usoc\'] ?? [], [\'entity_amount\', \'ENTITY_AMOUNT\']);',
            $builder
        );
        Assert::stringContainsString(
            '$discount = $this->positiveMoney($entityAmount, \'Invalid USOC entity amount\');',
            $builder
        );
        Assert::stringContainsString(
            '$base = $this->money((float) $studentAmount + (float) $discount);',
            $builder
        );
        Assert::stringContainsString(
            '$line[\'discount_origin\'] = \'USOC\';',
            $builder
        );
    }

    public function testCourseChangeFundsMustUseAuditableCompensationInsteadOfCopiedLegacyPayment(): void
    {
        $root = dirname(__DIR__, 3);
        $repository = file_get_contents(
            $root . '/sif/src/Repository/EnrollmentFundMovementRepository.php'
        );

        if ($repository === false) {
            Assert::fail('Could not read EnrollmentFundMovementRepository');
        }

        Assert::stringContainsString(
            'public function insertOrReuseCompensationAllocation(',
            $repository
        );
        Assert::stringContainsString(
            "'movement_type' => 'COMPENSATION_ALLOCATION'",
            $repository
        );
        Assert::stringContainsString(
            'in_array((string) $payment[\'TIPUS_MOVIMENT\'], [\'CHARGE\', \'COMPENSATION\'], true)',
            $repository
        );
        Assert::stringContainsString(
            '(string) $payment[\'ESTAT\'] !== \'CONFIRMED\'',
            $repository
        );
        Assert::stringContainsString(
            'Compensation allocation requires confirmed traceable origin funds',
            $repository
        );
    }
}
