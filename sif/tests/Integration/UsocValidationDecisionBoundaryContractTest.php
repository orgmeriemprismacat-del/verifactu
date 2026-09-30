<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class UsocValidationDecisionBoundaryContractTest
{
    public function testLegacyMutationIsStrictlyBetweenRequestedAndCommittedSifPhases(): void
    {
        $root = dirname(__DIR__, 3);
        $endpoint = file_get_contents(
            $root . '/codi-drive/intranet-actual/ajax/alumnes/sendMsgValidatCurosDescomptes.php'
        );

        if ($endpoint === false) {
            Assert::fail('Could not read legacy USOC validation endpoint');
        }

        $begin = strpos($endpoint, 'beginValidationDecision(');
        $legacyMutation = strpos($endpoint, 'sendMsgValidatCurosDescomptes($idInsc, $verificat)');
        $complete = strpos($endpoint, 'completeValidationDecision(');
        $sessionMemo = strpos($endpoint, 'validar_descomptes_requests'][$requestId] = $resultat');

        if ($begin === false || $legacyMutation === false || $complete === false || $sessionMemo === false) {
            Assert::fail('USOC validation two-phase boundary markers are incomplete');
        }

        if (!($begin < $legacyMutation && $legacyMutation < $complete && $complete < $sessionMemo)) {
            Assert::fail('USOC validation must persist REQUESTED before legacy mutation and COMMITTED before memoizing success');
        }

        Assert::stringContainsString("state'] ?? '') === 'REVIEW_REQUIRED'", $endpoint);
        Assert::stringContainsString("should_apply_legacy", $endpoint);
        Assert::stringContainsString("state'] ?? '') !== 'COMMITTED'", $endpoint);
    }

    public function testSignedUsocApiExposesTwoPhaseValidationActionsAndRecoveryComponents(): void
    {
        $root = dirname(__DIR__, 3);
        $api = file_get_contents($root . '/sif/public/api/usoc/manage.php');
        $client = file_get_contents($root . '/codi-drive/intranet-actual/SifInternalUsocClient.php');
        $service = file_get_contents($root . '/sif/src/Service/UsocValidationDecisionService.php');
        $repository = file_get_contents($root . '/sif/src/Repository/UsocValidationDecisionRepository.php');
        $reconcile = file_get_contents($root . '/sif/scripts/reconcile-usoc-validation-decisions.php');

        if ($api === false || $client === false || $service === false || $repository === false || $reconcile === false) {
            Assert::fail('Could not read USOC validation decision contract files');
        }

        Assert::stringContainsString("begin_validation_decision", $api);
        Assert::stringContainsString("complete_validation_decision", $api);
        Assert::stringContainsString('new UsocValidationDecisionService', $api);
        Assert::stringContainsString('assertUsocRole($actor, $manageRoles', $api);

        Assert::stringContainsString('beginValidationDecision(', $client);
        Assert::stringContainsString('completeValidationDecision(', $client);
        Assert::stringContainsString("'action' => 'begin_validation_decision'", $client);
        Assert::stringContainsString("'action' => 'complete_validation_decision'", $client);

        Assert::stringContainsString('beginTransaction()', $service);
        Assert::stringContainsString('markCommitted(', $service);
        Assert::stringContainsString('markReviewRequired(', $service);
        Assert::stringContainsString('Committed USOC validation decision no longer matches legacy state', $service);

        Assert::stringContainsString("'REQUESTED'", $repository);
        Assert::stringContainsString("'COMMITTED'", $repository);
        Assert::stringContainsString("'REVIEW_REQUIRED'", $repository);
        Assert::stringContainsString('findRequested(', $repository);

        Assert::stringContainsString('findRequested($db, $limit)', $reconcile);
        Assert::stringContainsString('->complete($db, $legacyDb, $requestId, $actorId)', $reconcile);
    }
}
