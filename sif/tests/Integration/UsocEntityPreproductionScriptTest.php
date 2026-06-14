<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class UsocEntityPreproductionScriptTest
{
    public function testScriptBuildsPreproductionUsocEntityProcessor(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/process-usoc-entity.php');

        if ($source === false) {
            Assert::fail('Could not read USOC entity processor script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('new UsocEntityInvoiceService(', $source);
        Assert::stringContainsString('new LegacyUsocSnapshotRepository()', $source);
        Assert::stringContainsString('new LegacyUsocInvoicePayloadBuilder()', $source);
        Assert::stringContainsString('issueEntityFromExplicitInput($legacyDb, $input)', $source);
        Assert::stringContainsString('--payload-file=', $source);
        Assert::stringContainsString('payment_registered', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'PaymentService') || str_contains($source, 'registerPayment(')) {
            Assert::fail('USOC entity processor must not register payments in this cut.');
        }

        if (str_contains($source, 'LegacySyncService')) {
            Assert::fail('USOC entity processor must not sync legacy in this cut.');
        }
    }
}
