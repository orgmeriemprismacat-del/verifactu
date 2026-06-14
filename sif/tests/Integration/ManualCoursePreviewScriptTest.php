<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualCoursePreviewScriptTest
{
    public function testPreviewBuildsManualCoursePayloadWithoutIssuingInvoice(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preview-manual-course.php');

        if ($source === false) {
            Assert::fail('Could not read manual course preview script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('new LegacyCourseSnapshotRepository()', $source);
        Assert::stringContainsString('new ManualCourseInvoicePayloadBuilder()', $source);
        Assert::stringContainsString('loadByIdpag($legacyDb, $idpag, $input[\'amount\'])', $source);
        Assert::stringContainsString('--discount-file=discount.json', $source);
        Assert::stringContainsString('new DiscountSnapshotFileReader()', $source);
        Assert::stringContainsString('->read($discountFile)', $source);
        Assert::stringContainsString('$snapshot[\'discount\'] = $discountSnapshot;', $source);
        Assert::stringContainsString('buildFromSnapshot($snapshot, $input)', $source);
        Assert::stringContainsString('preview-manual-course.php IDPAG AMOUNT MOVEMENT_DATE', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new InvoiceService(')) {
            Assert::fail('Preview must not build InvoiceService.');
        }

        if (str_contains($source, 'issueInvoice(') || str_contains($source, 'issueFromLegacyCoursePayment(')) {
            Assert::fail('Preview must not issue invoices.');
        }

        if (str_contains($source, 'syncAfterSifSuccess(')) {
            Assert::fail('Preview must not sync legacy.');
        }
    }
}
