<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualRectificationPreproductionScriptTest
{
    public function testScriptBuildsRectificationProcessorWithoutPaymentsOrLegacySync(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/process-manual-rectification.php');

        if ($source === false) {
            Assert::fail('Could not read manual rectification processor script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('new InvoiceService(', $source);
        Assert::stringContainsString('new ManualRectificationService(', $source);
        Assert::stringContainsString('new RectificationRepository()', $source);
        Assert::stringContainsString('new ManualRectificationPayloadBuilder()', $source);
        Assert::stringContainsString('--uuid-factura=', $source);
        Assert::stringContainsString('--num-visible=', $source);
        Assert::stringContainsString('--reason=', $source);
        Assert::stringContainsString('--mode=', $source);
        Assert::stringContainsString('issueByUuid', $source);
        Assert::stringContainsString('issueByNumVisible', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new PaymentService(') || str_contains($source, 'registerPayment(')) {
            Assert::fail('Manual rectification processor must not register payments.');
        }

        if (str_contains($source, 'LegacySyncService')) {
            Assert::fail('Manual rectification processor must not sync legacy in this cut.');
        }
    }
}
