<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualPackPreviewScriptTest
{
    public function testScriptBuildsDryRunManualPackPayloadOnly(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preview-manual-pack.php');

        if ($source === false) {
            Assert::fail('Could not read manual pack preview script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('new LegacyPackSnapshotRepository()', $source);
        Assert::stringContainsString('new ManualPackInvoicePayloadBuilder()', $source);
        Assert::stringContainsString('dry_run', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new InvoiceService(') || str_contains($source, 'issueInvoice(')) {
            Assert::fail('Manual pack preview must not issue invoices.');
        }

        if (str_contains($source, 'syncAfterSifSuccess(')) {
            Assert::fail('Manual pack preview must not synchronize legacy data.');
        }
    }
}
