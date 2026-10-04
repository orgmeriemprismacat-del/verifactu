<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class EnrollmentFundTransferReversalPreviewScriptTest
{
    public function testPreviewBuildsTransferReversalPayloadWithoutDatabaseMutation(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/scripts/preview-enrollment-fund-transfer-reversal.php'
        );

        if ($source === false) {
            Assert::fail('Could not read enrollment fund transfer reversal preview script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('new EnrollmentFundTransferPayloadBuilder()', $source);
        Assert::stringContainsString('buildReversal($input)', $source);
        Assert::stringContainsString('--movement-uuid=', $source);
        Assert::stringContainsString('--correlation-id=', $source);
        Assert::stringContainsString('--uuid-operation=', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'ConnectionFactory::make($config)')) {
            Assert::fail('Transfer reversal preview must not open a database connection.');
        }

        if (str_contains($source, 'reverseTransfer(')) {
            Assert::fail('Transfer reversal preview must not mutate the ledger.');
        }
    }
}
