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
        string $uuidFactura,
        array $payment
    ): array {
        return $this->client->request($actorId, $roles, [
            'uuid_factura' => trim($uuidFactura),
            'payment' => $payment,
        ]);
    }

    public function registerByNumVisible(
        string $actorId,
        array $roles,
        string $numVisible,
        array $payment
    ): array {
        return $this->client->request($actorId, $roles, [
            'num_visible' => trim($numVisible),
            'payment' => $payment,
        ]);
    }
}
