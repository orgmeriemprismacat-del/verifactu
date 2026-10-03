<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class DebtClaimScriptsContractTest
{
    public function testDebtClaimCliToolsAreNonProductionAndUseCoordinator(): void
    {
        $root = dirname(__DIR__, 2);
        $preflight = file_get_contents($root . '/scripts/preflight-debt-claim.php');
        $preview = file_get_contents($root . '/scripts/preview-debt-claim.php');
        $process = file_get_contents($root . '/scripts/process-debt-claim.php');

        if ($preflight === false || $preview === false || $process === false) {
            Assert::fail('Could not read UC-012 CLI scripts');
        }

        Assert::stringContainsString('debt_claim_case_table', $preflight);
        Assert::stringContainsString('debt_claim_event_table', $preflight);
        Assert::stringContainsString('notification_outbox_table', $preflight);
        Assert::stringContainsString('operational_event_table', $preflight);

        Assert::stringContainsString('SIF_ENV=production', $preview);
        Assert::stringContainsString('new DebtClaimCoordinator(', $preview);
        Assert::stringContainsString('->preview(', $preview);
        Assert::stringContainsString('--uuid-factura=', $preview);
        Assert::stringContainsString('--num-visible=', $preview);

        Assert::stringContainsString('SIF_ENV=production', $process);
        Assert::stringContainsString('new DebtClaimCoordinator(', $process);
        Assert::stringContainsString('FINAL_REMINDER', $process);
        Assert::stringContainsString('FIRST_CLAIM', $process);
        Assert::stringContainsString('FINAL_CLAIM', $process);
        Assert::stringContainsString('RECONCILE_AFTER_PAYMENT', $process);
        Assert::stringContainsString('--uuid-payment=', $process);
        Assert::stringContainsString('->recordNotice(', $process);
        Assert::stringContainsString('->reconcileAfterPayment(', $process);
    }
}
