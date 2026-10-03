<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class DebtClaimInternalApiContractTest
{
    public function testSignedApiAndIntranetClientExposeDebtClaimActions(): void
    {
        $root = dirname(__DIR__, 3);
        $api = file_get_contents($root . '/sif/public/api/debt-claims/manage.php');
        $client = file_get_contents($root . '/codi-drive/intranet-actual/SifInternalDebtClaimClient.php');

        if ($api === false || $client === false) {
            Assert::fail('Could not read UC-012 internal API contract files');
        }

        Assert::stringContainsString('InternalApiAuthenticator', $api);
        Assert::stringContainsString('SIF_DEBT_CLAIM_READ_ROLES', $api);
        Assert::stringContainsString('SIF_DEBT_CLAIM_MANAGE_ROLES', $api);
        Assert::stringContainsString('record_notice', $api);
        Assert::stringContainsString('reconcile_after_payment', $api);
        Assert::stringContainsString('DebtClaimCoordinator', $api);

        Assert::stringContainsString('SIF_INTERNAL_DEBT_CLAIM_URL', $client);
        Assert::stringContainsString('SIF_INTERNAL_DEBT_CLAIM_SIGNED_PATH', $client);
        Assert::stringContainsString('X-SIF-Signature', $client);
        Assert::stringContainsString("hash_hmac('sha256'", $client);
        Assert::stringContainsString('public function preview', $client);
        Assert::stringContainsString('public function recordNotice', $client);
        Assert::stringContainsString('public function reconcileAfterPayment', $client);

        require_once $root . '/codi-drive/intranet-actual/SifInternalDebtClaimClient.php';
        $instance = new \\SifInternalDebtClaimClient(
            'https://sif.example.test/api/debt-claims/manage.php',
            '/api/debt-claims/manage.php',
            'test-key',
            'test-secret'
        );
        Assert::same(true, $instance instanceof \\SifInternalDebtClaimClient);
    }
}
