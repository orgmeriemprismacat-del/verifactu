<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class RedsysNotificationRepository
{
    public function findByDsOrder(\PDO $db, string $dsOrder, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM redsys_notifications WHERE DS_ORDER = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$dsOrder]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function recordReceived(
        \PDO $db,
        string $dsOrder,
        ?int $idpag,
        mixed $amount,
        string $responseCode,
        bool $signatureValid,
        ?array $rawPayload = null,
        string $status = 'RECEIVED'
    ): array {
        $candidate = $this->candidate(
            $dsOrder,
            $idpag,
            $amount,
            $responseCode,
            $signatureValid,
            $rawPayload,
            $status
        );

        try {
            $db->prepare(
                'INSERT INTO redsys_notifications (
                    DS_ORDER, IDPAG, IMPORT, CURRENCY_CODE, TERMINAL, RESPONSE_CODE,
                    STATUS, RAW_PAYLOAD_JSON, SIGNATURE_VALID, SIGNATURE_VERSION, PAYLOAD_HASH
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $candidate['ds_order'],
                $candidate['idpag'],
                $candidate['amount'],
                $candidate['currency_code'],
                $candidate['terminal'],
                $candidate['response_code'],
                $candidate['status'],
                $this->encodeRawPayload($rawPayload),
                $candidate['signature_valid'],
                $candidate['signature_version'],
                $candidate['payload_hash'],
            ]);

            return [
                'duplicate' => false,
                'notification_id' => (int) $db->lastInsertId(),
                'ds_order' => $dsOrder,
                'status' => $status,
            ];
        } catch (\PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                $existing = $this->findByDsOrder($db, $dsOrder);
                if ($existing === null) {
                    throw $exception;
                }
                if (!$this->sameNotification($existing, $candidate)) {
                    throw SifException::conflict('Contradictory Redsys callback for existing DS_ORDER');
                }

                return [
                    'duplicate' => true,
                    'notification_id' => (int) $existing['ID'],
                    'ds_order' => $dsOrder,
                    'status' => 'DUPLICATE',
                ];
            }

            throw $exception;
        }
    }

    private function candidate(
        string $dsOrder,
        ?int $idpag,
        mixed $amount,
        string $responseCode,
        bool $signatureValid,
        ?array $rawPayload,
        string $status
    ): array {
        return [
            'ds_order' => $dsOrder,
            'idpag' => $idpag,
            'amount' => number_format((float) $amount, 2, '.', ''),
            'currency_code' => $rawPayload['currency_code'] ?? null,
            'terminal' => $rawPayload['terminal'] ?? null,
            'response_code' => $responseCode,
            'status' => $status,
            'signature_valid' => $signatureValid ? 1 : 0,
            'signature_version' => $rawPayload['signature_version'] ?? null,
            'payload_hash' => $rawPayload['payload_hash'] ?? null,
        ];
    }

    private function sameNotification(array $existing, array $candidate): bool
    {
        return number_format((float) $existing['IMPORT'], 2, '.', '') === $candidate['amount']
            && (string) $existing['RESPONSE_CODE'] === $candidate['response_code']
            && (string) $existing['CURRENCY_CODE'] === (string) $candidate['currency_code']
            && (string) $existing['TERMINAL'] === (string) $candidate['terminal']
            && (string) $existing['SIGNATURE_VERSION'] === (string) $candidate['signature_version']
            && hash_equals((string) $existing['PAYLOAD_HASH'], (string) $candidate['payload_hash']);
    }

    private function encodeRawPayload(?array $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        if ($json === false) {
            throw new \RuntimeException('Could not encode Redsys raw payload.');
        }

        return $json;
    }
}
