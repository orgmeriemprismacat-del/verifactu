<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RedsysPackPreflightScriptTest
{
    public function testPreflightChecksRedsysPackReadinessWithoutIssuingInvoices(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preflight-redsys-pack.php');

        if ($source === false) {
            Assert::fail('Could not read Redsys pack preflight script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('environment_safe_for_worker', $source);
        Assert::stringContainsString('SIF_REDSYS_WORKER_ALLOW_PRODUCTION', $source);
        Assert::stringContainsString('redsys_merchant_key_configured', $source);
        Assert::stringContainsString('internal_api_key_configured', $source);
        Assert::stringContainsString('internal_api_secret_configured', $source);
        Assert::stringContainsString('intent_create_roles_configured', $source);
        Assert::stringContainsString('callback_url_secure', $source);
        Assert::stringContainsString('intent_api_url_secure', $source);
        Assert::stringContainsString('checkout_actor_roles_configured', $source);
        Assert::stringContainsString('legacy_db_configured', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('redsys_payment_intent', $source);
        Assert::stringContainsString('redsys_notifications', $source);
        Assert::stringContainsString('redsys_callback_queue', $source);
        Assert::stringContainsString('notification_outbox', $source);
        Assert::stringContainsString('enrollment_fund_movement', $source);
        Assert::stringContainsString('payment_transaction', $source);
        Assert::stringContainsString('factura_linia', $source);
        Assert::stringContainsString('fact_rels', $source);
        Assert::stringContainsString('inscripcions', $source);
        Assert::stringContainsString('curs', $source);
        Assert::stringContainsString('info_pack', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);
        Assert::stringContainsString('exit(count($failed) === 0 ? 0 : 1)', $source);

        if (str_contains($source, 'RedsysPackInvoiceService')) {
            Assert::fail('Pack preflight must not build the invoice orchestration service.');
        }

        if (str_contains($source, 'issueFromValidatedNotification')) {
            Assert::fail('Pack preflight must not issue invoices.');
        }
    }
}
