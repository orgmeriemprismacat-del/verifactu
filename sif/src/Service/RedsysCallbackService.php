<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\RedsysNotificationRepository;

final class RedsysCallbackService
{
    public function __construct(private RedsysNotificationRepository $notifications)
    {
    }

    public function receiveCallback(\PDO $db, array $payload, bool $signatureValid = false): array
    {
        if (!$signatureValid) {
            throw SifException::validation('Invalid Redsys signature');
        }

        return $this->receiveAuthorizedCallback($db, $payload);
    }

    public function receiveAuthorizedCallback(\PDO $db, array $signedData): array
    {
        $payload = $this->validatePayload($signedData);
        $record = $this->notifications->recordReceived(
            $db,
            $payload['ds_order'],
            $payload['idpag'],
            $payload['amount'],
            $payload['response_code'],
            true,
            $signedData
        );

        return [
            'ok' => true,
            'duplicate' => (bool) $record['duplicate'],
            'ds_order' => $record['ds_order'],
            'status' => $record['status'],
        ];
    }

    private function validatePayload(array $payload): array
    {
        foreach (['ds_order', 'amount', 'response_code'] as $field) {
            if (!array_key_exists($field, $payload) || $payload[$field] === '') {
                throw SifException::validation("Missing Redsys field {$field}");
            }
        }

        $dsOrder = (string) $payload['ds_order'];
        if (strlen($dsOrder) > 40) {
            throw SifException::validation('Invalid Redsys DS_ORDER');
        }

        if (!is_numeric($payload['amount'])) {
            throw SifException::validation('Invalid Redsys amount');
        }

        $responseCode = (string) $payload['response_code'];
        if ($responseCode === '' || strlen($responseCode) > 10) {
            throw SifException::validation('Invalid Redsys response code');
        }

        $idpag = null;
        if (array_key_exists('idpag', $payload) && $payload['idpag'] !== '' && $payload['idpag'] !== null) {
            if (!is_numeric($payload['idpag'])) {
                throw SifException::validation('Invalid Redsys IDPAG');
            }

            $idpag = (int) $payload['idpag'];
        }

        return [
            'ds_order' => $dsOrder,
            'idpag' => $idpag,
            'amount' => number_format((float) $payload['amount'], 2, '.', ''),
            'response_code' => $responseCode,
        ];
    }
}
