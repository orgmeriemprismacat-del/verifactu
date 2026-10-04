<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class EnrollmentFundTransferPreproductionScriptTest
{
    public function testProcessBuildsTransferServiceWithoutCreatingCashMovement(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/scripts/process-enrollment-fund-transfer.php'
        );

        if ($source === false) {
            Assert::fail('Could not read enrollment fund transfer process script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('new EnrollmentFundTransferService(', $source);
        Assert::stringContainsString('new EnrollmentFundMovementRepository(new UuidGenerator())', $source);
        Assert::stringContainsString('new EnrollmentFundTransferPayloadBuilder()', $source);
        Assert::stringContainsString('--idempotency-key=', $source);
        Assert::stringContainsString('--source-enrollment-id=', $source);
        Assert::stringContainsString('--target-enrollment-id=', $source);
        Assert::stringContainsString('--correlation-id=', $source);
        Assert::stringContainsString('--uuid-operation=', $source);
        Assert::stringContainsString('->transfer($input)', $source);

        if (str_contains($source, 'registerPayment(')
            || str_contains($source, 'createPayment(')
            || str_contains($source, 'issueInvoice(')
        ) {
            Assert::fail('Enrollment fund transfer must not create a cash movement or invoice.');
        }
    }
}
