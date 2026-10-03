<?php

require_once __DIR__ . '/SifInternalApiClient.php';

final class SifManualTransferGateway
{
    public function __construct(
        private SifInternalApiClient $client,
        private string $path = '/api/payments/manual-transfer.php'
    ) {
    }

    public static function fromEnvironment(): self
    {
        return new self(
            SifInternalApiClient::fromEnvironment(),
            (string) (getenv('SIF_INTERNAL_MANUAL_TRANSFER_SIGNED_PATH') ?: '/api/payments/manual-transfer.php')
        );
    }

    public function register(
        string $actorId,
        array $roles,
        string $numVisible,
        string $amount,
        string $movementDate,
        string $externalBankEventId,
        ?string $reference = null,
        ?string $bank = null,
        ?string $notes = null
    ): array {
        $payload = [
            'num_visible' => trim($numVisible),
            'amount' => trim($amount),
            'movement_date' => trim($movementDate),
            'external_bank_event_id' => trim($externalBankEventId),
        ];

        foreach ([
            'reference' => $reference,
            'bank' => $bank,
            'notes' => $notes,
        ] as $key => $value) {
            if ($value !== null && trim($value) !== '') {
                $payload[$key] = trim($value);
            }
        }

        return $this->client->post($this->path, $payload, $actorId, $roles);
    }
}
