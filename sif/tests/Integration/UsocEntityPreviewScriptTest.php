<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class UsocEntityPreviewScriptTest
{
    public function testPreviewBuildsUsocEntityPayloadWithoutIssuingInvoice(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preview-usoc-entity.php');

        if ($source === false) {
            Assert::fail('Could not read USOC entity preview script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('new LegacyUsocSnapshotRepository()', $source);
        Assert::stringContainsString('new LegacyUsocInvoicePayloadBuilder()', $source);
        Assert::stringContainsString('loadByIdpag($legacyDb, $idpag, $studentAmount, $entityAmount)', $source);
        Assert::stringContainsString('buildEntityPayload($snapshot, $input)', $source);
        Assert::stringContainsString('--payload-file=', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new InvoiceService(') || str_contains($source, 'issueInvoice(')) {
            Assert::fail('USOC entity preview must not issue invoices.');
        }

        if (str_contains($source, 'PaymentService') || str_contains($source, 'registerPayment(')) {
            Assert::fail('USOC entity preview must not register payments.');
        }
    }
}
