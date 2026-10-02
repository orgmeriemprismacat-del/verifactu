<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class UsocInternalApiContractTest
{
    public function testSignedUsocApiAndIntranetClientExposeExpectedActions(): void
    {
        $root = dirname(__DIR__, 3);
        $api = file_get_contents($root . '/sif/public/api/usoc/manage.php');
        $client = file_get_contents($root . '/codi-drive/intranet-actual/SifInternalUsocClient.php');
        $config = file_get_contents($root . '/sif/config/sif.php');

        if ($api === false || $client === false || $config === false) {
            Assert::fail('Could not read USOC internal API contract files');
        }

        Assert::stringContainsString("REQUEST_METHOD", $api);
        Assert::stringContainsString("InternalApiAuthenticator", $api);
        Assert::stringContainsString("usoc_signed_path", $api);
        Assert::stringContainsString("assertUsocRole", $api);
        Assert::stringContainsString("issue_entity_invoice", $api);
        Assert::stringContainsString("register_entity_payment", $api);
        Assert::stringContainsString("reconcile", $api);
        Assert::stringContainsString("begin_validation_decision", $api);
        Assert::stringContainsString("complete_validation_decision", $api);
        Assert::stringContainsString("lifecycle_guard", $api);
        Assert::stringContainsString("lifecycle_plan", $api);
        Assert::stringContainsString("execute_cancellation", $api);
        Assert::stringContainsString("cancellation_execution_status", $api);
        Assert::stringContainsString("UsocLifecycleGuardService", $api);
        Assert::stringContainsString("UsocLifecyclePlanService", $api);
        Assert::stringContainsString("UsocCancellationExecutionService", $api);
        Assert::stringContainsString("UsocLifecycleExecutionRepository", $api);
        Assert::stringContainsString("UsocValidationDecisionService", $api);
        Assert::stringContainsString("UsocStudentInvoiceLinkRepository", $api);
        Assert::stringContainsString("UsocEntityPaymentService", $api);

        Assert::stringContainsString("SIF_INTERNAL_USOC_URL", $client);
        Assert::stringContainsString("SIF_INTERNAL_USOC_SIGNED_PATH", $client);
        Assert::stringContainsString("X-SIF-Signature", $client);
        Assert::stringContainsString("hash_hmac('sha256'", $client);
        Assert::stringContainsString("public function issueEntityInvoice", $client);
        Assert::stringContainsString("public function registerEntityPayment", $client);
        Assert::stringContainsString("public function reconcile", $client);
        Assert::stringContainsString("public function beginValidationDecision", $client);
        Assert::stringContainsString("public function completeValidationDecision", $client);
        Assert::stringContainsString("public function lifecycleGuard", $client);
        Assert::stringContainsString("public function lifecyclePlan", $client);
        Assert::stringContainsString("public function executeCancellation", $client);
        Assert::stringContainsString("public function cancellationExecutionStatus", $client);

        Assert::stringContainsString("SIF_USOC_READ_ROLES", $config);
        Assert::stringContainsString("SIF_USOC_MANAGE_ROLES", $config);
        Assert::stringContainsString("SIF_INTERNAL_USOC_SIGNED_PATH", $config);
    }
}
