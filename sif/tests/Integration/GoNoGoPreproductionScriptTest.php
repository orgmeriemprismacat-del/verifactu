<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\ScriptRunner;
use Prisma\Sif\Tests\Support\TestDatabase;

final class GoNoGoPreproductionScriptTest
{
    public function testExecutableReportsCompleteSchemaButDoesNotAuthorizeProduction(): void
    {
        $db = TestDatabase::fresh();
        $result = ScriptRunner::run('scripts/go-no-go-preproduction.php', [
            'SIF_REDSYS_MERCHANT_KEY' => '',
            'SIF_LEGACY_DB_DSN' => '',
        ]);
        Assert::same(1, $result['exit_code']);
        Assert::same('', $result['stderr']);
        $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
        Assert::same('NO-GO', $json['go_no_go_decision']);
        Assert::same(true, $json['checks']['schema_verified']);
        Assert::same(true, $json['checks']['redsys_callback_queue_table']);
        Assert::same(true, $json['checks']['academic_reconciliation_item_table']);
        Assert::same(false, $json['checks']['redsys_merchant_key_configured']);
        Assert::same(false, $json['checks']['legacy_database_configured']);
        Assert::same('technical_preflight_only', $json['scope']);
        Assert::same(false, $json['production_authorized']);
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
    }

    public function testExecutableRejectsMissingExtensionTableAndProductionEnvironment(): void
    {
        $db = TestDatabase::fresh();
        $db->exec('RENAME TABLE academic_reconciliation_item TO test_hidden_academic_item');
        try {
            $result = ScriptRunner::run('scripts/go-no-go-preproduction.php', [
                'SIF_ENV' => 'production',
                'SIF_LEGACY_DB_DSN' => '',
            ]);
            Assert::same(1, $result['exit_code']);
            $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
            Assert::same(false, $json['checks']['schema_verified']);
            Assert::same(false, $json['checks']['academic_reconciliation_item_table']);
            Assert::same(false, $json['checks']['environment_not_production']);
            Assert::same('NO-GO', $json['go_no_go_decision']);
        } finally {
            $db->exec('RENAME TABLE test_hidden_academic_item TO academic_reconciliation_item');
        }
    }

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
        Assert::stringContainsString('credit_balance_circuit_present', $source);
        Assert::stringContainsString('manual_refund_circuit_present', $source);
        Assert::stringContainsString('redsys_usoc_circuit_present', $source);
        Assert::stringContainsString('usoc_entity_circuit_present', $source);
        Assert::stringContainsString('redsys_group_circuit_present', $source);
        Assert::stringContainsString('manual_group_circuit_present', $source);
        Assert::stringContainsString('claim_payment_circuit_present', $source);
        Assert::stringContainsString('redsys_async_circuit_present', $source);
        Assert::stringContainsString('redsys_payment_intent_table', $source);
        Assert::stringContainsString('redsys_callback_queue_table', $source);
        Assert::stringContainsString('2026_06_19_000003_create_redsys_callback_queue.sql', $source);
        Assert::stringContainsString('2026_06_19_000004_harden_redsys_notifications.sql', $source);
        Assert::stringContainsString('preflight-redsys-callback-queue.php', $source);
        Assert::stringContainsString('process-redsys-callback-queue.php', $source);
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
