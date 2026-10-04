<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RedsysCoursePreviewScriptTest
{
    public function testPreviewBuildsPayloadWithoutIssuingInvoice(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preview-redsys-course.php');

        if ($source === false) {
            Assert::fail('Could not read Redsys course preview script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('new RedsysNotificationRepository()', $source);
        Assert::stringContainsString('new RedsysPaymentIntentRepository()', $source);
        Assert::stringContainsString('findByDsOrder($sifDb, $dsOrder)', $source);
        Assert::stringContainsString("$intent['SNAPSHOT_JSON']", $source);
        Assert::stringContainsString('new LegacyCourseInvoicePayloadBuilder()', $source);
        Assert::stringContainsString('new RedsysInvoicePayloadBuilder($notifications)', $source);
        Assert::stringContainsString('--discount-file=discount.json', $source);
        Assert::stringContainsString('new DiscountSnapshotFileReader()', $source);
        Assert::stringContainsString('->read($discountFile)', $source);
        Assert::stringContainsString('$snapshot[\'discount\'] = $discountSnapshot;', $source);
        Assert::stringContainsString('buildFromValidatedNotification($sifDb, $dsOrder, $basePayload)', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'ConnectionFactory::makeLegacy($config)')
            || str_contains($source, 'LegacyCourseSnapshotRepository')
        ) {
            Assert::fail('Course preview must use the frozen intent snapshot, not live legacy data.');
        }

        if (str_contains($source, 'new InvoiceService(')) {
            Assert::fail('Preview must not build InvoiceService.');
        }

        if (str_contains($source, 'issueInvoice(') || str_contains($source, 'issueFromValidatedNotification(')) {
            Assert::fail('Preview must not issue invoices.');
        }
    }
}
