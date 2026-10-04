<?php

require_once __DIR__ . '/SifInternalUsocClient.php';

final class SifInternalClaimPaymentClient
{
    private SifInternalUsocClient $client;

    public function __construct()
    {
        $url = trim((string) (getenv('SIF_INTERNAL_CLAIM_PAYMENT_URL') ?: ''));
        $signedPath = trim((string) (
            getenv('SIF_INTERNAL_CLAIM_PAYMENT_SIGNED_PATH')
                ?: '/api/claim-payments/register.php'
        ));

        $this->client = new SifInternalUsocClient($url, $signedPath);
    }

    public function registerByUuid(
        string $actorId,
        array $roles,
        int $sourceInscriptionId,
        string $claimCaseId,
        string $externalReceiptId,
        string $uuidFactura,
        array $payment
    ): array {
        return $this->client->request($actorId, $roles, [
            'source_inscription_id' => $sourceInscriptionId,
            'claim_case_id' => trim($claimCaseId),
            'external_receipt_id' => trim($externalReceiptId),
            'uuid_factura' => trim($uuidFactura),
            'payment' => $payment,
        ]);
    }

    public function registerByNumVisible(
        string $actorId,
        array $roles,
        int $sourceInscriptionId,
        string $claimCaseId,
        string $externalReceiptId,
        string $numVisible,
        array $payment
    ): array {
        return $this->client->request($actorId, $roles, [
            'source_inscription_id' => $sourceInscriptionId,
            'claim_case_id' => trim($claimCaseId),
            'external_receipt_id' => trim($externalReceiptId),
            'num_visible' => trim($numVisible),
            'payment' => $payment,
        ]);
    }
}
