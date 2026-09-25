<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class FiscalRecordScriptsTest
{
    public function testPreviewDoesNotCreateFiscalRecordsOrPayments(): void
    {
        $source = $this->source('preview-fiscal-record.php');

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('new FiscalRecordPayloadBuilder()', $source);
        Assert::stringContainsString('FiscalRecordArguments', $source);
        Assert::stringContainsString('dry_run', $source);

        foreach (['createCancellation', 'createSubsanation', 'new PaymentService(', 'new InvoiceService('] as $forbidden) {
            if (str_contains($source, $forbidden)) {
                Assert::fail('Fiscal record preview contains a write service: ' . $forbidden);
            }
        }
    }

    public function testProcessorBuildsFiscalRecordServiceWithoutPaymentsOrLegacySync(): void
    {
        $source = $this->source('process-fiscal-record.php');

        Assert::stringContainsString('new FiscalRecordService(', $source);
        Assert::stringContainsString('new FiscalRecordRepository(new HashCalculator())', $source);
        Assert::stringContainsString('createCancellationByUuid', $source);
        Assert::stringContainsString('createSubsanationByNumVisible', $source);
        Assert::stringContainsString('FiscalRecordArguments', $source);

        foreach (['new PaymentService(', 'new InvoiceService(', 'LegacySyncService'] as $forbidden) {
            if (str_contains($source, $forbidden)) {
                Assert::fail('Fiscal record processor contains an unrelated service: ' . $forbidden);
            }
        }
    }

    private function source(string $file): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/' . $file);
        if ($source === false) {
            Assert::fail('Could not read fiscal record script ' . $file);
        }

        return $source;
    }
}
