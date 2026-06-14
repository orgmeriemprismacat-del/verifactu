<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RedsysUsocPreviewScriptTest
{
    public function testPreviewBuildsUsocStudentPayloadWithoutIssuingInvoice(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preview-redsys-usoc.php');

        if ($source === false) {
            Assert::fail('Could not read Redsys USOC preview script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('new RedsysNotificationRepository()', $source);
        Assert::stringContainsString('new LegacyUsocSnapshotRepository()', $source);
        Assert::stringContainsString('new LegacyUsocInvoicePayloadBuilder()', $source);
        Assert::stringContainsString('new RedsysInvoicePayloadBuilder($notifications)', $source);
        Assert::stringContainsString('loadByIdpag($legacyDb, $idpag, $studentAmount, $usocAmount)', $source);
        Assert::stringContainsString('buildStudentPayload($snapshot)', $source);
        Assert::stringContainsString('buildFromValidatedNotification($sifDb, $dsOrder, $basePayload)', $source);
        Assert::stringContainsString('entity_invoice_pending', $source);
        Assert::stringContainsString('requires_explicit_billing', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new InvoiceService(')) {
            Assert::fail('USOC preview must not build InvoiceService.');
        }

        if (str_contains($source, 'issueInvoice(') || str_contains($source, 'issueStudentFromValidatedNotification(')) {
            Assert::fail('USOC preview must not issue invoices.');
        }
    }
}
