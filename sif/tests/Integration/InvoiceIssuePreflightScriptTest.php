<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class InvoiceIssuePreflightScriptTest
{
    public function testPreflightChecksSecureInvoiceIssueReadinessWithoutMutatingData(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preflight-invoice-issue.php');

        if ($source === false) {
            Assert::fail('Could not read invoice issue preflight script');
        }

        Assert::stringContainsString("PHP_SAPI !== 'cli'", $source);
        Assert::stringContainsString('internal_api_key_id_configured', $source);
        Assert::stringContainsString('internal_api_secret_configured', $source);
        Assert::stringContainsString('invoice_issue_signed_path_configured', $source);
        Assert::stringContainsString('invoice_issue_write_roles_configured', $source);
        Assert::stringContainsString('issuer_nif_configured', $source);
        Assert::stringContainsString('issuer_name_configured', $source);
        Assert::stringContainsString('internal_api_request_table', $source);
        Assert::stringContainsString('sif_audit_event_table', $source);
        Assert::stringContainsString('operational_event_table', $source);
        Assert::stringContainsString('factura_table', $source);
        Assert::stringContainsString('factura_linia_table', $source);
        Assert::stringContainsString('factura_registres_table', $source);
        Assert::stringContainsString('factura_registre_control_table', $source);
        Assert::stringContainsString('fiscal_sequence_table', $source);
        Assert::stringContainsString('fiscal_chain_state_table', $source);
        Assert::stringContainsString('fiscal_queue_table', $source);
        Assert::stringContainsString('fact_rels_table', $source);
        Assert::stringContainsString('commercial_operation_table', $source);
        Assert::stringContainsString('commercial_operation_line_table', $source);
        Assert::stringContainsString('operation_line_invoice_link_table', $source);
        Assert::stringContainsString('payment_transaction_table', $source);
        Assert::stringContainsString('payment_allocation_table', $source);
        Assert::stringContainsString('fiscal_chain_state_seeded', $source);
        Assert::stringContainsString("'G00000000'", $source);

        if (str_contains($source, "['secret']") && str_contains($source, "result['secret']")) {
            Assert::fail('Invoice issue preflight must never expose the configured internal API secret.');
        }

        if (str_contains($source, 'issueInvoice(') || str_contains($source, 'registerPayment(')) {
            Assert::fail('Invoice issue preflight must not mutate fiscal or payment data.');
        }
    }
}
