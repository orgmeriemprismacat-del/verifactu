<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RedsysPackPreproductionScriptTest
{
    public function testScriptBuildsManualPreproductionPackProcessor(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/process-redsys-pack.php');

        if ($source === false) {
            Assert::fail('Could not read Redsys pack preproduction script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('new RedsysPackInvoiceService(', $source);
        Assert::stringContainsString('new LegacySyncService(new LegacySyncRepository())', $source);
        Assert::stringContainsString('new RedsysPaymentIntentRepository()', $source);
        Assert::stringContainsString("SOURCE_TYPE", $source);
        Assert::stringContainsString("SNAPSHOT_JSON", $source);
        Assert::stringContainsString('new LegacyPackInvoicePayloadBuilder()', $source);
        Assert::stringContainsString('new RedsysInvoicePayloadBuilder($notifications)', $source);
        Assert::stringContainsString('$service->issueFromIntentSnapshot($sifDb, $dsOrder, $snapshot)', $source);
        Assert::stringContainsString('new PackPaymentNotificationService(', $source);
        Assert::stringContainsString('new NotificationOutboxRepository(', $source);
        Assert::stringContainsString('new PackEnrollmentFundAllocationService(', $source);
        Assert::stringContainsString('new EnrollmentFundMovementRepository(', $source);
        Assert::stringContainsString('--sync-legacy', $source);
        Assert::stringContainsString('syncAfterSifSuccess(', $source);
        Assert::stringContainsString('syncPackFullPayment(', $source);
        Assert::stringContainsString('legacy_sync_executed', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'RedsysSignatureValidator')) {
            Assert::fail('Manual pack processor must consume an already validated notification, not re-parse Redsys POST.');
        }
    }
}
