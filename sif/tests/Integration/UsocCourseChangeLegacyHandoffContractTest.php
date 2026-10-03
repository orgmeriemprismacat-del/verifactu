<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class UsocCourseChangeLegacyHandoffContractTest
{
    public function testLegacyHandoffUsesReservedDestinationAndFinalizesSif(): void
    {
        $root = dirname(__DIR__, 3);
        $controller = file_get_contents(
            $root . '/codi-drive/intranet-actual/ajax/alumnes/realitzarCanviCurs_CanviCurs.php'
        );
        $prepare = file_get_contents(
            $root . '/codi-drive/intranet-actual/ajax/alumnes/sifUsocCourseChangePrepare.php'
        );
        $intranet = file_get_contents(
            $root . '/codi-drive/intranet-actual/Intranet.php'
        );
        $ui = file_get_contents(
            $root . '/codi-drive/intranet-actual/js/alumnes-usoc-lifecycle-preview.js'
        );

        if ($controller === false || $prepare === false || $intranet === false || $ui === false) {
            Assert::fail('Could not read UC-013 legacy handoff contract files');
        }

        Assert::stringContainsString('LegacyUsocCourseChangeDestinationReservationService', $prepare);
        Assert::stringContainsString('bindCourseChangeDestination(', $prepare);
        Assert::stringContainsString("'destination_idpag'", $prepare);
        Assert::stringContainsString("'reservation_marker'", $prepare);

        Assert::stringContainsString("'legacy_completed'", $controller);
        Assert::stringContainsString('executeCourseChange(', $controller);
        Assert::stringContainsString('getDarrerIdCanviCurs()', $controller);
        Assert::stringContainsString("'destination_id_insc'", $controller);
        Assert::stringContainsString("'destination_idpag'", $controller);

        Assert::stringContainsString('$reservedDestination = null', $intranet);
        Assert::stringContainsString('pag_observacions = ?', $intranet);
        Assert::stringContainsString('PAGAMENT = 0', $intranet);
        Assert::stringContainsString('public function getDarrerIdCanviCurs()', $intranet);

        Assert::stringContainsString('sifUsocCourseChangePrepare.php', $ui);
        Assert::stringContainsString('allowLegacyCourseChangeConfirmClick', $ui);
        Assert::stringContainsString('uc013-course-change-continue', $ui);
        Assert::stringContainsString('#modalConfirmacioCanvi #confirmar-canvi', $ui);
    }

    public function testExecutorRequiresDurablyBoundDestinationIdpag(): void
    {
        $root = dirname(__DIR__, 3);
        $executor = file_get_contents(
            $root . '/sif/src/Service/UsocCourseChangeExecutionService.php'
        );

        if ($executor === false) {
            Assert::fail('Could not read UC-013 course change executor');
        }

        Assert::stringContainsString("'DESTINATION_RESERVED'", $executor);
        Assert::stringContainsString("'destination_idpag'", $executor);
        Assert::stringContainsString(
            'USOC course change destination must be durably bound before execution',
            $executor
        );
        Assert::stringContainsString("'legacy_handoff_completed' => true", $executor);
    }
}
