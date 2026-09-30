<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class InvoiceBeforePaymentLegacyScriptsTest
{
    public function testPreviewUsesServerSideLegacySourcesWithoutIssuing(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/scripts/preview-invoice-before-payment-from-legacy.php'
        );

        if ($source === false) {
            Assert::fail('Could not read legacy-backed invoice-before-payment preview script');
        }

        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacyIntranet($config)', $source);
        Assert::stringContainsString('InvoiceBeforePaymentLegacyPreparationService', $source);
        Assert::stringContainsString('--inscriptions=', $source);
        Assert::stringContainsString('--entity-id=', $source);
        Assert::stringContainsString('--created-by=', $source);
        Assert::stringContainsString("'fingerprint' =>", $source);

        if (str_contains($source, 'issueBeforePayment(') || str_contains($source, 'issueInvoice(')) {
            Assert::fail('Legacy-backed preview must not issue a fiscal invoice.');
        }
    }

    public function testProcessorRequiresPreviewFingerprintBeforeIssuing(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/scripts/process-invoice-before-payment-from-legacy.php'
        );

        if ($source === false) {
            Assert::fail('Could not read legacy-backed invoice-before-payment processor script');
        }

        Assert::stringContainsString('--expected-fingerprint=', $source);
        Assert::stringContainsString('hash_equals($expectedFingerprint', $source);
        Assert::stringContainsString('preview changed before confirmation', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacyIntranet($config)', $source);
        Assert::stringContainsString('new InvoiceBeforePaymentCoverageRepository()', $source);
        Assert::stringContainsString('issueBeforePayment($prepared[\'input\'])', $source);
        Assert::stringContainsString('fingerprint_verified', $source);

        if (str_contains($source, 'registerPayment(') || str_contains($source, 'LegacySyncService')) {
            Assert::fail('UC-004 confirmation must not register payment or sync legacy in this cut.');
        }
    }

    public function testPreflightChecksSifAndBothLegacyDatabasesWithoutMutating(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/scripts/preflight-invoice-before-payment-from-legacy.php'
        );

        if ($source === false) {
            Assert::fail('Could not read legacy-backed invoice-before-payment preflight script');
        }

        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacyIntranet($config)', $source);
        Assert::stringContainsString('invoice_before_payment_coverage', $source);
        Assert::stringContainsString('inscripcions', $source);
        Assert::stringContainsString('curs', $source);
        Assert::stringContainsString('entitats', $source);
        Assert::stringContainsString('entitats_resp', $source);

        if (
            str_contains($source, 'issueBeforePayment(')
            || str_contains($source, 'issueInvoice(')
            || str_contains($source, 'registerPayment(')
            || str_contains($source, 'INSERT INTO')
            || str_contains($source, 'UPDATE ')
            || str_contains($source, 'DELETE ')
        ) {
            Assert::fail('Legacy-backed preflight must be read-only.');
        }
    }

}
