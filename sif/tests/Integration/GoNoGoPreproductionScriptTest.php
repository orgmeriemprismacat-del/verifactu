<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class GoNoGoPreproductionScriptTest
{
    public function testGoNoGoScriptChecksBlockingPreproductionReadiness(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/go-no-go-preproduction.php');

        if ($source === false) {
            Assert::fail('Could not read go/no-go preproduction script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('environment_not_production', $source);
        Assert::stringContainsString('core_preflight_script_present', $source);
        Assert::stringContainsString('tests_runner_present', $source);
        Assert::stringContainsString('sif_database_connectivity', $source);
        Assert::stringContainsString('legacy_database_connectivity', $source);
        Assert::stringContainsString('redsys_merchant_key_configured', $source);
        Assert::stringContainsString('manual_payment_circuit_present', $source);
        Assert::stringContainsString('manual_installment_circuit_present', $source);
        Assert::stringContainsString('manual_rectification_circuit_present', $source);
        Assert::stringContainsString('manual_invoice_circuit_present', $source);
        Assert::stringContainsString('historical_migration_circuit_present', $source);
        Assert::stringContainsString('manual_gift_circuit_present', $source);
        Assert::stringContainsString('redsys_course_circuit_present', $source);
        Assert::stringContainsString('go_no_go_decision', $source);
        Assert::stringContainsString('NO-GO', $source);
        Assert::stringContainsString('GO', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'issueInvoice(') || str_contains($source, 'registerPayment(')) {
            Assert::fail('Go/no-go preproduction script must not emit invoices or register payments.');
        }

        if (str_contains($source, 'syncAfterSifSuccess(')) {
            Assert::fail('Go/no-go preproduction script must not sync legacy.');
        }
    }
}
