<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ClaimPaymentPreproductionScriptTest
{
    public function testPreflightCoversSignedApiAuditAndLegacyProjectionDependencies(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preflight-claim-payment.php');

        if ($source === false) {
            Assert::fail('Could not read claim payment preflight script');
        }

        foreach ([
            'environment_not_production',
            'internal_api_key_configured',
            'internal_api_secret_configured',
            'claim_payment_signed_path_configured',
            'claim_payment_manage_roles_configured',
            'fact_rels_table',
            'payment_action_event_table',
            'internal_api_request_table',
            'payment_payload_hash_version_column',
            'legacy_database_connectivity',
            'legacy_inscripcions_table',
            'legacy_claim_payment_columns',
            'ConnectionFactory::makeLegacy($config)',
            'DATA PAG',
            'INSC CURS',
        ] as $required) {
            Assert::stringContainsString($required, $source);
        }

        Assert::stringContainsString('safeError', $source);
        Assert::stringContainsString('configuration/connectivity check failed', $source);
    }

    public function testScriptBuildsPreproductionClaimPaymentProcessor(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/process-claim-payment.php');

        if ($source === false) {
            Assert::fail('Could not read claim payment preproduction script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('new ClaimPaymentService(', $source);
        Assert::stringContainsString('new ManualPaymentInvoiceRepository()', $source);
        Assert::stringContainsString('new ClaimPaymentPayloadBuilder()', $source);
        Assert::stringContainsString('new PaymentService(', $source);
        Assert::stringContainsString('--uuid-factura=', $source);
        Assert::stringContainsString('--num-visible=', $source);
        Assert::stringContainsString('--claim-reference=', $source);
        Assert::stringContainsString('registerByUuid(', $source);
        Assert::stringContainsString('registerByNumVisible(', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new InvoiceService(') || str_contains($source, 'issueInvoice(')) {
            Assert::fail('Claim payment processor must not issue invoices.');
        }
    }
}
