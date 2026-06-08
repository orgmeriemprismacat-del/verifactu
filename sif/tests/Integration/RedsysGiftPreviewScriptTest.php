<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RedsysGiftPreviewScriptTest
{
    public function testPreviewBuildsGiftPayloadWithoutIssuingInvoice(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preview-redsys-gift.php');

        if ($source === false) {
            Assert::fail('Could not read Redsys gift preview script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('new RedsysNotificationRepository()', $source);
        Assert::stringContainsString('new LegacyGiftSnapshotRepository()', $source);
        Assert::stringContainsString('new LegacyGiftInvoicePayloadBuilder()', $source);
        Assert::stringContainsString('new RedsysInvoicePayloadBuilder($notifications)', $source);
        Assert::stringContainsString('--gift-id=', $source);
        Assert::stringContainsString('--gift-code=', $source);
        Assert::stringContainsString('loadGiftSnapshot($legacyDb, $selector)', $source);
        Assert::stringContainsString('assertGiftAmountMatchesNotification($snapshot, $notification)', $source);
        Assert::stringContainsString('buildFromValidatedNotification($sifDb, $dsOrder, $basePayload)', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new InvoiceService(')) {
            Assert::fail('Gift preview must not build InvoiceService.');
        }

        if (str_contains($source, 'issueInvoice(') || str_contains($source, 'issueByGift')) {
            Assert::fail('Gift preview must not issue invoices.');
        }
    }
}
