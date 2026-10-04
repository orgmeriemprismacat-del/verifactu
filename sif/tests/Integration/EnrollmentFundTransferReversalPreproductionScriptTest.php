<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class EnrollmentFundTransferReversalPreproductionScriptTest
{
    public function testProcessBuildsTransferReversalServiceWithoutCashMutation(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/scripts/process-enrollment-fund-transfer-reversal.php'
        );

        if ($source === false) {
            Assert::fail('Could not read enrollment fund transfer reversal process script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('new EnrollmentFundTransferService(', $source);
        Assert::stringContainsString('new EnrollmentFundTransferPayloadBuilder()', $source);
        Assert::stringContainsString('--movement-uuid=', $source);
        Assert::stringContainsString('--correlation-id=', $source);
        Assert::stringContainsString('--uuid-operation=', $source);
        Assert::stringContainsString('reverseTransfer($input)', $source);

        if (str_contains($source, 'registerPayment(')
            || str_contains($source, 'createPayment(')
            || str_contains($source, 'issueInvoice(')
        ) {
            Assert::fail('Transfer reversal must not create a cash movement or invoice.');
        }
    }
}
