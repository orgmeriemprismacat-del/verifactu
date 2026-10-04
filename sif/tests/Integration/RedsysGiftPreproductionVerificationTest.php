<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RedsysGiftPreproductionVerificationTest
{
    public function testVerifierIsReadOnlyAndRequiresTestOrPreproduction(): void
    {
        $source = $this->read('sif/scripts/verify-redsys-gift-preproduction.php');

        Assert::stringContainsString('uc-017-redsys-gift-preproduction-verification', $source);
        Assert::stringContainsString("['test', 'preproduction', 'preprod']", $source);
        Assert::stringContainsString('preflight-redsys-gift.php', $source);
        Assert::stringContainsString('preflight-redsys-callback-queue.php', $source);
        Assert::stringContainsString('gift_intent_present', $source);
        Assert::stringContainsString('validated_notification_present', $source);
        Assert::stringContainsString('worker_processed', $source);
        Assert::stringContainsString('gift_purchase_operation_present', $source);
        Assert::stringContainsString('gift_entitlement_present', $source);
        Assert::stringContainsString('gift_purchase_links_invoice_payment', $source);

        foreach ([
            'issueInvoice(',
            'process-redsys-gift.php',
            'process-redsys-callback-queue.php',
            'INSERT INTO',
            'UPDATE factura',
            'UPDATE regal',
        ] as $mutation) {
            if (str_contains($source, $mutation)) {
                Assert::fail('Preproduction verifier must remain read-only: ' . $mutation);
            }
        }
    }

    public function testVerifierSanitizesSensitiveEvidence(): void
    {
        $source = $this->read('sif/scripts/verify-redsys-gift-preproduction.php');

        foreach ([
            "str_contains($normalized, 'secret')",
            "str_contains($normalized, 'password')",
            "str_contains($normalized, 'signature')",
            "str_contains($normalized, 'raw_payload')",
            "str_contains($normalized, 'merchant_key')",
        ] as $guard) {
            Assert::stringContainsString($guard, $source);
        }
    }

    public function testGiftPreflightAndConfigExposeIntentAndStatusEndpoints(): void
    {
        $preflight = $this->read('sif/scripts/preflight-redsys-gift.php');
        $config = $this->read('sif/config/sif.php');

        Assert::stringContainsString('gift_intent_endpoint_present', $preflight);
        Assert::stringContainsString('gift_status_endpoint_present', $preflight);
        Assert::stringContainsString('gift_intent_signed_path_matches_bridge', $preflight);
        Assert::stringContainsString('gift_status_signed_path_matches_bridge', $preflight);
        Assert::stringContainsString('aeat_system_name_present', $preflight);
        Assert::stringContainsString('aeat_system_id_valid', $preflight);
        Assert::stringContainsString('aeat_producer_name_present', $preflight);
        Assert::stringContainsString('aeat_producer_nif_present', $preflight);
        Assert::stringContainsString('aeat_gift_tax_code_valid', $preflight);
        Assert::stringContainsString('aeat_gift_regime_key_valid', $preflight);
        Assert::stringContainsString('aeat_gift_exemption_code_valid', $preflight);

        Assert::stringContainsString('SIF_INTERNAL_REDSYS_GIFT_INTENT_SIGNED_PATH', $config);
        Assert::stringContainsString('SIF_INTERNAL_REDSYS_GIFT_STATUS_SIGNED_PATH', $config);
        Assert::stringContainsString('SIF_AEAT_SYSTEM_NAME', $config);
        Assert::stringContainsString('SIF_AEAT_PRODUCER_NAME', $config);
        Assert::stringContainsString('SIF_AEAT_PRODUCER_NIF', $config);
        Assert::stringContainsString('SIF_AEAT_GIFT_TAX_CODE', $config);
        Assert::stringContainsString('SIF_AEAT_GIFT_REGIME_KEY', $config);
        Assert::stringContainsString('SIF_AEAT_GIFT_EXEMPTION_CODE', $config);
    }

    private function read(string $relativePath): string
    {
        $path = dirname(__DIR__, 3) . '/' . $relativePath;
        $content = file_get_contents($path);
        if ($content === false) {
            Assert::fail('Could not read ' . $relativePath);
        }

        return $content;
    }
}
