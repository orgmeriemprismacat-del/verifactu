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
        $handoff = file_get_contents(
            $root . '/sif/src/Service/UsocCourseChangeLegacyHandoffService.php'
        );

        if (
            $controller === false
            || $prepare === false
            || $intranet === false
            || $ui === false
            || $handoff === false
        ) {
            Assert::fail('Could not read UC-013 legacy handoff contract files');
        }

        Assert::stringContainsString('LegacyUsocCourseChangeDestinationReservationService', $prepare);
        Assert::stringContainsString('bindCourseChangeDestination(', $prepare);
        Assert::stringContainsString("'destination_idpag'", $prepare);
        Assert::stringContainsString("'reservation_marker'", $prepare);

        Assert::stringContainsString("'legacy_completed'", $controller);
        Assert::stringContainsString('confirmCourseChangeLegacyHandoff(', $controller);
        Assert::stringContainsString('executeCourseChange(', $controller);
        Assert::stringContainsString('getDarrerIdCanviCurs()', $controller);
        Assert::stringContainsString("'destination_id_insc'", $controller);
        Assert::stringContainsString("'destination_idpag'", $controller);

        Assert::stringContainsString('$reservedDestination = null', $intranet);
        Assert::stringContainsString('pag_observacions = ?', $intranet);
        Assert::stringContainsString('PAGAMENT = 0', $intranet);
        Assert::stringContainsString('public function getDarrerIdCanviCurs()', $intranet);

        Assert::stringContainsString("'LEGACY_COMPLETED'", $handoff);
        Assert::stringContainsString("'legacy_handoff_completed'", $handoff);
        Assert::stringContainsString("'source_closed'", $handoff);
        Assert::stringContainsString("'REVIEW_REQUIRED'", $handoff);
        Assert::stringContainsString("'DATA_BAIXA'", $handoff);

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

        Assert::stringContainsString("'LEGACY_COMPLETED'", $executor);
        Assert::stringContainsString("'destination_idpag'", $executor);
        Assert::stringContainsString("'legacy_handoff_completed'", $executor);
        Assert::stringContainsString("'source_closed'", $executor);
        Assert::stringContainsString(
            'USOC course change legacy handoff must be durably confirmed before execution',
            $executor
        );
    }
}
