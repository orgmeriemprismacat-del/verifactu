<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualInstallmentPaymentEvidenceScriptTest
{
    public function testEvidenceVerifierIsReadOnlyAndSupportsUc023Selectors(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/scripts/verify-manual-installment-evidence.php'
        );

        if ($source === false) {
            Assert::fail('Could not read UC-023 evidence verifier');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString("PHP_SAPI !== 'cli'", $source);
        Assert::stringContainsString('SIF_UC023_EVIDENCE_ALLOW_PRODUCTION', $source);
        Assert::stringContainsString('--uuid-payment=', $source);
        Assert::stringContainsString('--reference=', $source);
        Assert::stringContainsString('--ds-order=', $source);
        Assert::stringContainsString('payment_transaction', $source);
        Assert::stringContainsString('payment_allocation', $source);
        Assert::stringContainsString('factura_registres', $source);
        Assert::stringContainsString('FISCAL_REGISTER_COUNT', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        foreach (['INSERT ', 'UPDATE ', 'DELETE ', 'TRUNCATE ', 'REPLACE '] as $mutation) {
            if (stripos($source, $mutation) !== false) {
                Assert::fail('UC-023 evidence verifier must remain read-only: ' . trim($mutation));
            }
        }
    }
}
