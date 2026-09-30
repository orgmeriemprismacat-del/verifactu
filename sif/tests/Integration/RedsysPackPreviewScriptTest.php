<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RedsysPackPreviewScriptTest
{
    public function testPreviewBuildsPackPayloadWithoutIssuingInvoice(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preview-redsys-pack.php');

        if ($source === false) {
            Assert::fail('Could not read Redsys pack preview script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        if (str_contains($source, 'ConnectionFactory::makeLegacy($config)')) {
            Assert::fail('Redsys PACK preview must use the frozen intent snapshot, not reconstruct from legacy DB.');
        }
        Assert::stringContainsString('new RedsysNotificationRepository()', $source);
        Assert::stringContainsString('new RedsysPaymentIntentRepository()', $source);
        Assert::stringContainsString("SOURCE_TYPE", $source);
        Assert::stringContainsString("SNAPSHOT_JSON", $source);
        Assert::stringContainsString('new LegacyPackInvoicePayloadBuilder()', $source);
        Assert::stringContainsString('new RedsysInvoicePayloadBuilder($notifications)', $source);
        Assert::stringContainsString('json_decode((string) ($intent[\'SNAPSHOT_JSON\'] ?? \'\'), true)', $source);
        Assert::stringContainsString('buildFromValidatedNotification($sifDb, $dsOrder, $basePayload)', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new InvoiceService(')) {
            Assert::fail('Pack preview must not build InvoiceService.');
        }

        if (str_contains($source, 'issueInvoice(') || str_contains($source, 'issueFromValidatedNotification(')) {
            Assert::fail('Pack preview must not issue invoices.');
        }
    }
}
