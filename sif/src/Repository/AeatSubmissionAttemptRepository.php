<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Aeat\XmlCodec;

final class AeatSubmissionAttemptRepository
{
    public function begin(\PDO $db, array $queueItem, array $payload): string
    {
        $fiscalOrder = $payload['fiscal_order'] ?? null;
        if (!is_int($fiscalOrder) && !ctype_digit((string) $fiscalOrder)) {
            throw new \RuntimeException('Cannot start AEAT attempt without fiscal order.');
        }

        $record = $db->prepare(
            'SELECT ID FROM factura_registres WHERE UUID_FACTURA = ? AND FISCAL_ORDER = ? LIMIT 1'
        );
        $record->execute([$queueItem['UUID_FACTURA'], (int) $fiscalOrder]);
        $recordId = $record->fetchColumn();
        if ($recordId === false) {
            throw new \RuntimeException('Cannot start AEAT attempt for missing fiscal record.');
        }

        $requestHash = hash('sha256', (string) ($queueItem['PAYLOAD_JSON'] ?? ''));
        if (isset($payload['aeat']) && is_array($payload['aeat'])) {
            $requestHash = hash('sha256', (new XmlCodec())->request($payload['aeat']));
        }

        $uuid = $this->uuidV4();
        $stmt = $db->prepare(
            'INSERT INTO aeat_submission_attempt
             (UUID_ATTEMPT, FACTURA_REGISTRE_ID, FISCAL_QUEUE_ID, ATTEMPT_NO,
              ENVIRONMENT, ENDPOINT_CODE, REQUEST_HASH, STATUS, STARTED_AT)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(6))'
        );
        $stmt->execute([
            $uuid,
            (int) $recordId,
            (int) $queueItem['ID'],
            (int) $queueItem['ATTEMPTS'],
            'preproduction',
            'AEAT_WORKER',
            $requestHash,
            'STARTED',
        ]);

        return $uuid;
    }

    public function complete(\PDO $db, string $uuidAttempt, string $status, array $response): void
    {
        $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $stmt = $db->prepare(
            'UPDATE aeat_submission_attempt
             SET STATUS = ?, RESPONSE_CODE = ?, RESPONSE_CSV = ?, RESPONSE_JSON = ?,
                 ERROR_CODE = ?, ERROR_DETAIL = ?, FINISHED_AT = NOW(6)
             WHERE UUID_ATTEMPT = ? AND STATUS = \'STARTED\''
        );
        $stmt->execute([
            $status,
            $response['estado_registro'] ?? null,
            $response['csv'] ?? null,
            $json,
            $response['error_code'] ?? null,
            $response['error_message'] ?? null,
            $uuidAttempt,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('AEAT submission attempt is no longer open.');
        }
    }

    public function fail(\PDO $db, string $uuidAttempt, string $status, string $detail): void
    {
        if (!in_array($status, ['FAILED', 'UNCERTAIN'], true)) {
            throw new \InvalidArgumentException('Invalid AEAT attempt failure status.');
        }
        $stmt = $db->prepare(
            'UPDATE aeat_submission_attempt
             SET STATUS = ?, ERROR_DETAIL = ?, FINISHED_AT = NOW(6)
             WHERE UUID_ATTEMPT = ? AND STATUS = \'STARTED\''
        );
        $stmt->execute([$status, mb_substr($detail, 0, 2000, 'UTF-8'), $uuidAttempt]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('AEAT submission attempt is no longer open.');
        }
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
