<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class InternalInvoiceIssuePayloadPolicy
{
    public function __construct(
        private string $issuerNif,
        private string $issuerName
    ) {
        $this->issuerNif = trim($this->issuerNif);
        $this->issuerName = trim($this->issuerName);
    }

    public function prepare(array $payload, array $actor): array
    {
        $actorId = trim((string) ($actor['actor_id'] ?? ''));
        if ($actorId === '') {
            throw SifException::forbidden('Authenticated actor is required');
        }

        if (!empty($payload['emesa_abans_cobrament']) || !empty($payload['uc004_invoice_before_payment'])) {
            throw SifException::validation(
                'Invoice-before-payment operations must use the dedicated UC-004 endpoint'
            );
        }

        $sourceChannel = strtoupper(trim((string) ($payload['source_channel'] ?? '')));
        if ($sourceChannel === 'REDSYS') {
            throw SifException::validation(
                'Redsys invoices must be issued from the validated Redsys callback flow'
            );
        }

        $payment = $payload['payment'] ?? null;
        if (is_array($payment)) {
            $method = strtoupper(trim((string) ($payment['method'] ?? '')));
            $paymentChannel = strtoupper(trim((string) ($payment['source_channel'] ?? '')));
            if ($method === 'REDSYS' || $paymentChannel === 'REDSYS') {
                throw SifException::validation(
                    'Redsys payments must be created from the validated Redsys callback flow'
                );
            }
        }

        $payload['created_by'] = $actorId;

        if (array_key_exists('aeat_fields', $payload)) {
            if ($this->issuerNif === '' || $this->issuerName === '' || $this->issuerNif === 'G00000000') {
                throw new \RuntimeException('Configured non-placeholder SIF issuer is required for official AEAT payloads');
            }

            $header = $payload['aeat_header'] ?? [];
            if (!is_array($header)) {
                throw SifException::validation('Invalid AEAT header');
            }

            $header['ObligadoEmision'] = [
                'NIF' => $this->issuerNif,
                'NombreRazon' => $this->issuerName,
            ];
            $payload['aeat_header'] = $header;
        }

        return $payload;
    }
}
