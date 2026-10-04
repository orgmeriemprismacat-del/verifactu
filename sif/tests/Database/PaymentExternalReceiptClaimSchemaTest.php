<?php

namespace Prisma\Sif\Tests\Database;

use Prisma\Sif\Tests\Support\Assert;

final class PaymentExternalReceiptClaimSchemaTest
{
    public function testUc023ExternalReceiptClaimIsUniqueAndCascadesWithPayment(): void
    {
        $sql = file_get_contents(
            dirname(__DIR__, 2)
            . '/database/migrations/2026_10_04_000033_add_payment_external_receipt_claim.sql'
        );

        if ($sql === false) {
            Assert::fail('Could not read UC-023 external receipt claim migration');
        }

        Assert::stringContainsString(
            'CREATE TABLE IF NOT EXISTS payment_external_receipt_claim',
            $sql
        );
        Assert::stringContainsString(
            'UNIQUE KEY uq_payment_external_receipt_type_value (RECEIPT_TYPE, RECEIPT_VALUE)',
            $sql
        );
        Assert::stringContainsString(
            'FOREIGN KEY (UUID_PAYMENT) REFERENCES payment_transaction(UUID_PAYMENT)',
            $sql
        );
        Assert::stringContainsString('ON DELETE CASCADE', $sql);
    }
}
