<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualGroupPreviewScriptTest
{
    public function testPreviewBuildsManualGroupPayloadWithoutIssuingInvoice(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preview-manual-group.php');

        if ($source === false) {
            Assert::fail('Could not read manual group preview script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('new LegacyGroupSnapshotRepository()', $source);
        Assert::stringContainsString('new ManualGroupInvoicePayloadBuilder()', $source);
        Assert::stringContainsString('loadByIdpag($legacyDb, $idpag, $input[\'amount\'])', $source);
        Assert::stringContainsString('buildFromSnapshot($snapshot, $input)', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new InvoiceService(')) {
            Assert::fail('Manual group preview must not build InvoiceService.');
        }

        if (str_contains($source, 'issueInvoice(') || str_contains($source, 'issueFromLegacyGroupPayment(')) {
            Assert::fail('Manual group preview must not issue invoices.');
        }
    }
}
