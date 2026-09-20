<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\IncidentRepository;
use Prisma\Sif\Repository\RedsysCallbackQueueRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;

final class RedsysCallbackService
{
    public function __construct(
        private RedsysPaymentIntentRepository $intents,
        private RedsysNotificationRepository $notifications,
        private RedsysCallbackQueueRepository $queue,
        private IncidentRepository $incidents
    ) {
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
        $db->beginTransaction();

        try {
            $intent = $this->intents->findByDsOrder($db, $payload['ds_order'], true);
            if ($intent === null) {
                throw SifException::validation('Unknown Redsys payment intent');
            }

            $this->assertMatchesIntent($intent, $payload);
            $status = $this->statusForResponseCode($payload['response_code']);
            $record = $this->notifications->recordReceived(
                $db,
                $payload['ds_order'],
                $intent['IDPAG'] === null ? null : (int) $intent['IDPAG'],
                $payload['amount'],
                $payload['response_code'],
                true,
                $signedData,
                $status
            );

            $job = null;
            if ($status === 'VALIDATED') {
                $job = $this->queue->enqueue(
                    $db,
                    (int) $record['notification_id'],
                    (string) $intent['UUID_INTENT']
                );
            }

            $db->commit();

            return [
                'ok' => true,
                'duplicate' => (bool) $record['duplicate'],
                'ds_order' => $record['ds_order'],
                'status' => $record['status'],
                'queue_status' => $job['STATUS'] ?? null,
                'uuid_job' => $job['UUID_JOB'] ?? null,
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($exception instanceof SifException && $exception->getCode() === 409) {
                $details = json_encode([
                    'ds_order' => $payload['ds_order'],
                    'message' => $exception->getMessage(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $this->incidents->open(
                    $db,
                    null,
                    'REDSYS_CALLBACK',
                    $details === false ? $exception->getMessage() : $details
                );
            }

            throw $exception;
        }
    }

    private function validatePayload(array $payload): array
    {
        $decoded = $payload['redsys']['decoded'] ?? [];
        if (!array_key_exists('currency', $payload) && is_array($decoded)) {
            $currencyCode = $decoded['Ds_Currency'] ?? null;
            $payload['currency'] = $currencyCode === '978' ? 'EUR' : $currencyCode;
        }
        if (!array_key_exists('terminal', $payload) && is_array($decoded)) {
            $payload['terminal'] = $decoded['Ds_Terminal'] ?? null;
        }

        foreach (['ds_order', 'amount', 'response_code', 'currency', 'terminal'] as $field) {
            if (!array_key_exists($field, $payload) || $payload[$field] === '' || $payload[$field] === null) {
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

        return [
            'ds_order' => $dsOrder,
            'amount' => number_format((float) $payload['amount'], 2, '.', ''),
            'response_code' => $responseCode,
            'currency' => strtoupper(trim((string) $payload['currency'])),
            'terminal' => trim((string) $payload['terminal']),
        ];
    }

    private function assertMatchesIntent(array $intent, array $payload): void
    {
        $matches = number_format((float) $intent['EXPECTED_AMOUNT'], 2, '.', '') === $payload['amount']
            && (string) $intent['CURRENCY'] === $payload['currency']
            && (string) $intent['TERMINAL'] === $payload['terminal'];

        if (!$matches) {
            throw SifException::validation('Redsys callback does not match payment intent');
        }
    }

    private function statusForResponseCode(string $responseCode): string
    {
        if (ctype_digit($responseCode) && (int) $responseCode >= 0 && (int) $responseCode <= 99) {
            return 'VALIDATED';
        }

        return 'ERROR';
    }
}
