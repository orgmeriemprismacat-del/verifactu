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
        Assert::stringContainsString("UsocStudentInvoiceLinkRepository", $api);
        Assert::stringContainsString("UsocEntityPaymentService", $api);

        Assert::stringContainsString("SIF_INTERNAL_USOC_URL", $client);
        Assert::stringContainsString("SIF_INTERNAL_USOC_SIGNED_PATH", $client);
        Assert::stringContainsString("X-SIF-Signature", $client);
        Assert::stringContainsString("hash_hmac('sha256'", $client);
        Assert::stringContainsString("public function issueEntityInvoice", $client);
        Assert::stringContainsString("public function registerEntityPayment", $client);
        Assert::stringContainsString("public function reconcile", $client);

        Assert::stringContainsString("SIF_USOC_READ_ROLES", $config);
        Assert::stringContainsString("SIF_USOC_MANAGE_ROLES", $config);
        Assert::stringContainsString("SIF_INTERNAL_USOC_SIGNED_PATH", $config);
    }
}
