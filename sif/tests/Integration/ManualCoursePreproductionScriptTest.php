<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualCoursePreproductionScriptTest
{
    public function testScriptBuildsManualCoursePreproductionProcessor(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/process-manual-course.php');

        if ($source === false) {
            Assert::fail('Could not read manual course preproduction script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('new ManualCourseInvoiceService(', $source);
        Assert::stringContainsString('new ManualCourseInvoicePayloadBuilder()', $source);
        Assert::stringContainsString('new LegacyCourseSnapshotRepository()', $source);
        Assert::stringContainsString('new LegacySyncService(new LegacySyncRepository())', $source);
        Assert::stringContainsString('$service->issueFromLegacyCoursePayment($legacyDb, $idpag, $input, $discountSnapshot)', $source);
        Assert::stringContainsString('process-manual-course.php IDPAG AMOUNT MOVEMENT_DATE', $source);
        Assert::stringContainsString('--discount-file=discount.json', $source);
        Assert::stringContainsString('new DiscountSnapshotFileReader()', $source);
        Assert::stringContainsString('->read($discountFile)', $source);
        Assert::stringContainsString('--sync-legacy', $source);
        Assert::stringContainsString('syncAfterSifSuccess(', $source);
        Assert::stringContainsString('legacy_sync_executed', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'Redsys')) {
            Assert::fail('Manual course processor must not parse or depend on Redsys notifications.');
        }
    }
}
