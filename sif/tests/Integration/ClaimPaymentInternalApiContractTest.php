<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ClaimPaymentInternalApiContractTest
{
    public function testSignedClaimPaymentApiKeepsIdentityAndAuditServerSide(): void
    {
        $root = dirname(__DIR__, 3);
        $api = file_get_contents($root . '/sif/public/api/claim-payments/register.php');
        $client = file_get_contents($root . '/codi-drive/intranet-actual/SifInternalClaimPaymentClient.php');
        $transport = file_get_contents($root . '/codi-drive/intranet-actual/SifInternalUsocClient.php');
        $config = file_get_contents($root . '/sif/config/sif.php');

        if ($api === false || $client === false || $transport === false || $config === false) {
            Assert::fail('Could not read UC-024 internal API contract files');
        }

        Assert::stringContainsString('InternalApiAuthenticator', $api);
        Assert::stringContainsString('claim_payment_signed_path', $api);
        Assert::stringContainsString('assertClaimPaymentRole', $api);
        Assert::stringContainsString('PaymentActionGateway', $api);
        Assert::stringContainsString('PaymentActionEventRepository', $api);
        Assert::stringContainsString('LINK_CLAIM_PAYMENT', $api);
        Assert::stringContainsString('registerByUuidInTransaction', $api);
        Assert::stringContainsString('registerByNumVisibleInTransaction', $api);
        Assert::stringContainsString("\$paymentInput['created_by'] = (string) (\$actor['actor_id']", $api);
        Assert::stringContainsString('Provide at most one claim payment invoice selector', $api);
        Assert::stringContainsString('resolveUniqueOriginForInscription', $api);
        Assert::stringContainsString('source_inscription_id', $api);
        Assert::stringContainsString('ClaimPaymentInvoiceLinkRepository', $api);
        Assert::stringContainsString('ClaimPaymentLegacySyncService', $api);
        Assert::stringContainsString('assertBaselineSynchronized', $api);
        Assert::stringContainsString('syncAfterSifSuccess', $api);
        Assert::stringContainsString("'action' => 'SYNC_LEGACY'", $api);
        Assert::stringContainsString('payment_persisted', $api);
        Assert::stringContainsString('requires_reconciliation', $api);
        Assert::stringContainsString('sifPaymentPersisted', $api);
        Assert::stringContainsString('persistedPaymentResult', $api);
        Assert::stringContainsString('reconciliation_error_code', $api);
        Assert::stringContainsString('assertUuidMatches', $api);
        Assert::stringContainsString('assertNumVisibleMatches', $api);
        Assert::stringContainsString('claim_case_id', $api);
        Assert::stringContainsString('external_receipt_id', $api);
        Assert::stringContainsString("unset(\$paymentInput[\$legacyReferenceField])", $api);
        Assert::stringContainsString("\$paymentInput['external_receipt_id'] = \$externalReceiptId", $api);
        Assert::stringContainsString("'changeset' => [", $api);

        Assert::stringContainsString('SIF_INTERNAL_CLAIM_PAYMENT_URL', $client);
        Assert::stringContainsString('SIF_INTERNAL_CLAIM_PAYMENT_SIGNED_PATH', $client);
        Assert::stringContainsString('/api/claim-payments/register.php', $client);
        Assert::stringContainsString('registerByInscription', $client);
        Assert::stringContainsString('registerByUuid', $client);
        Assert::stringContainsString('registerByNumVisible', $client);
        Assert::stringContainsString("'source_inscription_id' => \$sourceInscriptionId", $client);
        Assert::stringContainsString("'claim_case_id' => trim(\$claimCaseId)", $client);
        Assert::stringContainsString("'external_receipt_id' => trim(\$externalReceiptId)", $client);

        Assert::stringContainsString('X-SIF-Signature', $transport);
        Assert::stringContainsString("hash_hmac('sha256'", $transport);

        Assert::stringContainsString('SIF_INTERNAL_CLAIM_PAYMENT_SIGNED_PATH', $config);
        Assert::stringContainsString('SIF_CLAIM_PAYMENT_MANAGE_ROLES', $config);

        foreach ([
            "\$payload['actor_id']",
            "\$payload['roles']",
            'SIF_INTERNAL_API_SECRET'
        ] as $browserControlled) {
            if (str_contains($api, $browserControlled)) {
                Assert::fail('UC-024 API must not accept identity/signing material from request JSON');
            }
        }
    }
}
