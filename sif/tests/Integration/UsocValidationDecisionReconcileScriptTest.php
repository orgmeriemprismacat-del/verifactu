<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class UsocValidationDecisionReconcileScriptTest
{
    public function testReconcileScriptProcessesOnlyPersistedRequestedDecisions(): void
    {
        $root = dirname(__DIR__, 3);
        $script = file_get_contents($root . '/sif/scripts/reconcile-usoc-validation-decisions.php');
        $preflight = file_get_contents($root . '/sif/scripts/preflight-usoc-intranet.php');
        $repository = file_get_contents($root . '/sif/src/Repository/UsocValidationDecisionRepository.php');

        if ($script === false || $preflight === false || $repository === false) {
            Assert::fail('Could not read USOC validation reconciliation files');
        }

        Assert::stringContainsString("PHP_SAPI !== 'cli'", $script);
        Assert::stringContainsString("SIF_ENV=production", $script);
        Assert::stringContainsString('findRequested', $script);
        Assert::stringContainsString('->complete(', $script);
        Assert::stringContainsString("'state' => 'ERROR'", $script);

        Assert::stringContainsString('usoc_validation_decision_table', $preflight);
        Assert::stringContainsString("tableExists($db, 'usoc_validation_decision')", $preflight);

        Assert::stringContainsString("WHERE STATE = 'REQUESTED'", $repository);
        Assert::stringContainsString('ORDER BY REQUESTED_AT ASC, ID ASC', $repository);
    }
}
