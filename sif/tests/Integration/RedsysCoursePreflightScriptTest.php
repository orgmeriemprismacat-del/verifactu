<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RedsysCoursePreflightScriptTest
{
    public function testPreflightChecksRedsysCourseReadinessWithoutIssuingInvoices(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preflight-redsys-course.php');

        if ($source === false) {
            Assert::fail('Could not read Redsys course preflight script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('environment_is_test_or_preproduction', $source);
        Assert::stringContainsString('redsys_merchant_key_configured', $source);
        Assert::stringContainsString('bridge_redsys_merchant_code_configured', $source);
        Assert::stringContainsString('bridge_redsys_merchant_key_configured', $source);
        Assert::stringContainsString('bridge_redsys_terminal_configured', $source);
        Assert::stringContainsString('bridge_and_sif_redsys_keys_match', $source);
        Assert::stringContainsString('internal_api_base_url_https_configured', $source);
        Assert::stringContainsString('redsys_callback_url_https_configured', $source);
        Assert::stringContainsString('redsys_gateway_url_https_configured', $source);
        Assert::stringContainsString('legacy_db_configured', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('redsys_notifications', $source);
        Assert::stringContainsString('payment_transaction', $source);
        Assert::stringContainsString('inscripcions', $source);
        Assert::stringContainsString('curs', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);
        Assert::stringContainsString('exit(count($failed) === 0 ? 0 : 1)', $source);

        if (str_contains($source, 'RedsysCourseInvoiceService')) {
            Assert::fail('Preflight must not build the invoice orchestration service.');
        }

        if (str_contains($source, 'issueFromValidatedNotification')) {
            Assert::fail('Preflight must not issue invoices.');
        }
    }
}
