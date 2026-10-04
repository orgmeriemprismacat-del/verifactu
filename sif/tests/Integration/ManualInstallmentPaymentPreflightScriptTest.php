<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualInstallmentPaymentPreflightScriptTest
{
    public function testUc023PreflightChecksSecureCutoverDependenciesWithoutExposingSecret(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/scripts/preflight-manual-installment.php'
        );

        if ($source === false) {
            Assert::fail('Could not read UC-023 preflight script');
        }

        Assert::stringContainsString("PHP_SAPI !== 'cli'", $source);
        Assert::stringContainsString('environment_not_production', $source);
        Assert::stringContainsString('internal_api_key_id_configured', $source);
        Assert::stringContainsString('internal_api_secret_configured', $source);
        Assert::stringContainsString('installment_write_roles_configured', $source);
        Assert::stringContainsString('payment_external_receipt_claim', $source);
        Assert::stringContainsString('internal_api_request', $source);
        Assert::stringContainsString('uq_payment_external_receipt_type_value', $source);
        Assert::stringContainsString('fk_payment_external_receipt_payment', $source);
        Assert::stringContainsString("=== 'CASCADE'", $source);
        Assert::stringContainsString('MigrationRunner', $source);

        if (str_contains($source, "'secret' =>")) {
            Assert::fail('UC-023 preflight must never emit the internal API secret');
        }
    }
}
