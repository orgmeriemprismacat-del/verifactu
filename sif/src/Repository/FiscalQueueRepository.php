<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Contract\PayloadIdempotencyValidatorInterface;
use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Exception\SifException;

final class FiscalQueueRepository
{
    public function recoverStaleLocks(\PDO $db, string $lockedBefore): int
    {
        $stmt = $db->prepare(
            "UPDATE fiscal_queue
             SET STATUS = 'RETRY', LOCKED_AT = NULL, CLAIM_TOKEN = NULL, NEXT_RETRY_AT = NULL,
                 LAST_ERROR = 'Recovered stale worker lock'
             WHERE STATUS = 'PROCESSING' AND LOCKED_AT IS NOT NULL AND LOCKED_AT < ?"
        );
        $stmt->execute([$lockedBefore]);

        return $stmt->rowCount();
    }

    public function claimNext(\PDO $db, int $maxAttempts): ?array
    {
        $stmt = $db->prepare(
            "SELECT * FROM fiscal_queue
             WHERE STATUS IN ('PENDING', 'RETRY')
               AND ATTEMPTS < ?
               AND (NEXT_RETRY_AT IS NULL OR NEXT_RETRY_AT <= NOW())
             ORDER BY ID
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute([$maxAttempts]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $claimToken = $this->uuidV4();
        $update = $db->prepare(
            "UPDATE fiscal_queue
             SET STATUS = 'PROCESSING', ATTEMPTS = ATTEMPTS + 1, LOCKED_AT = NOW(),
                 CLAIM_TOKEN = ?, LAST_ERROR = NULL
             WHERE ID = ? AND STATUS IN ('PENDING', 'RETRY')"
        );
        $update->execute([$claimToken, $row['ID']]);
        if ($update->rowCount() !== 1) {
            throw new \RuntimeException('Fiscal queue item could not be claimed.');
        }

        $row['STATUS'] = 'PROCESSING';
        $row['ATTEMPTS'] = (int) $row['ATTEMPTS'] + 1;
        $row['CLAIM_TOKEN'] = $claimToken;

        return $row;
    }

    public function assertImmutablePayload(
        \PDO $db,
        array $queueItem,
        PayloadIdempotencyValidatorInterface $validator
    ): void {
        $queuePayload = json_decode((string) ($queueItem['PAYLOAD_JSON'] ?? ''), true);
        if (!is_array($queuePayload) || !isset($queuePayload['fiscal_order'])) {
            throw SifException::conflict('Missing or invalid fiscal queue payload');
        }

        $stmt = $db->prepare(
            'SELECT PAYLOAD_JSON, HASH_FACT, HASH_FACT_ANT FROM factura_registres
             WHERE UUID_FACTURA = ? AND FISCAL_ORDER = ? LIMIT 1'
        );
        $stmt->execute([$queueItem['UUID_FACTURA'], $queuePayload['fiscal_order']]);
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($record === false) {
            throw SifException::conflict('Fiscal queue registration record not found');
        }

        $frozenPayload = json_decode((string) $record['PAYLOAD_JSON'], true);
        if (!is_array($frozenPayload)) {
            throw SifException::conflict('Invalid stored fiscal registration payload');
        }

        $validator->assertMatches($queuePayload, $validator->calculateHash($frozenPayload));
        $chainHash = (new HashCalculator())->calculate(
            $frozenPayload,
            $record['HASH_FACT_ANT'] === null ? null : (string) $record['HASH_FACT_ANT']
        );
        if (!hash_equals((string) $record['HASH_FACT'], $chainHash)) {
            throw SifException::conflict('Fiscal registration hash chain does not match frozen payload');
        }
    }

    public function rejectIntegrity(\PDO $db, array $queueItem, string $message): void
    {
        $stmt = $db->prepare(
            "UPDATE fiscal_queue SET STATUS = 'DEAD_LETTER', LAST_ERROR = ?,
              LOCKED_AT = NULL, CLAIM_TOKEN = NULL, NEXT_RETRY_AT = NULL
              WHERE ID = ? AND STATUS = 'PROCESSING' AND CLAIM_TOKEN = ?"
        );
        $stmt->execute([
            substr($message, 0, 2000),
            $queueItem['ID'],
            $this->claimToken($queueItem),
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('Fiscal queue item could not be quarantined');
        }
    }

    public function complete(
        \PDO $db,
        array $queueItem,
        string $aeatStatus,
        array $response,
        ?string $requestXml
    ): void {
        $payload = $this->payload($queueItem);
        $responseJson = $this->encode($response);

        $queue = $db->prepare(
            "UPDATE fiscal_queue
             SET AEAT_CSV = ?, AEAT_ERROR_CODE = ?, AEAT_ERROR_MESSAGE = ?, FLOW_WAIT_SECONDS = ?,
                 STATUS = 'SENT', SENT_AT = NOW(), LOCKED_AT = NULL, CLAIM_TOKEN = NULL,
                 NEXT_RETRY_AT = NULL, LAST_ERROR = NULL
             WHERE ID = ? AND STATUS = 'PROCESSING' AND CLAIM_TOKEN = ?"
        );
        $queue->execute([
            isset($response['csv']) ? mb_substr((string) $response['csv'], 0, 120, 'UTF-8') : null,
            isset($response['error_code']) ? mb_substr((string) $response['error_code'], 0, 80, 'UTF-8') : null,
            isset($response['error_message']) ? mb_substr((string) $response['error_message'], 0, 500, 'UTF-8') : null,
            $response['flow_wait_seconds'] ?? null,
            $queueItem['ID'],
            $this->claimToken($queueItem),
        ]);
        if ($queue->rowCount() !== 1) {
            throw new \RuntimeException('Fiscal queue ownership was lost before completion.');
        }

        $record = $db->prepare(
            'UPDATE factura_registres
             SET XML_PAYLOAD = ?, AEAT_RESPONSE_JSON = ?, ESTAT_AEAT = ?, DATE_SENT = NOW()
             WHERE UUID_FACTURA = ? AND FISCAL_ORDER = ?'
        );
        $record->execute([
            $requestXml,
            $responseJson,
            $aeatStatus,
            $queueItem['UUID_FACTURA'],
            $payload['fiscal_order'],
        ]);
        if ($record->rowCount() !== 1) {
            throw new \RuntimeException('Fiscal queue item does not match an immutable registration record.');
        }

        $db->prepare('UPDATE factura SET ESTAT_AEAT = ? WHERE UUID_FACTURA = ?')
            ->execute([$aeatStatus, $queueItem['UUID_FACTURA']]);
    }

    public function fail(
        \PDO $db,
        array $queueItem,
        string $error,
        int $maxAttempts,
        ?string $nextRetryAt
    ): string {
        $status = (int) $queueItem['ATTEMPTS'] >= $maxAttempts ? 'DEAD_LETTER' : 'RETRY';
        $stmt = $db->prepare(
            'UPDATE fiscal_queue
             SET STATUS = ?, LAST_ERROR = ?, LOCKED_AT = NULL, CLAIM_TOKEN = NULL, NEXT_RETRY_AT = ?
             WHERE ID = ? AND STATUS = \'PROCESSING\' AND CLAIM_TOKEN = ?'
        );
        $stmt->execute([
            $status,
            substr($error, 0, 2000),
            $status === 'RETRY' ? $nextRetryAt : null,
            $queueItem['ID'],
            $this->claimToken($queueItem),
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('Fiscal queue ownership was lost before failure handling.');
        }

        if ($status === 'DEAD_LETTER') {
            $this->markFiscalError($db, $queueItem, $error);
        }

        return $status;
    }

    public function holdForReview(\PDO $db, array $queueItem, string $reason): void
    {
        $stmt = $db->prepare(
            "UPDATE fiscal_queue
             SET STATUS = 'REVIEW', LAST_ERROR = ?, LOCKED_AT = NULL, CLAIM_TOKEN = NULL,
                 NEXT_RETRY_AT = NULL
             WHERE ID = ? AND STATUS = 'PROCESSING' AND CLAIM_TOKEN = ?"
        );
        $stmt->execute([
            substr($reason, 0, 2000),
            $queueItem['ID'],
            $this->claimToken($queueItem),
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('Fiscal queue ownership was lost before review hold.');
        }
    }

    public function exhaust(\PDO $db, array $queueItem, string $reason): void
    {
        $stmt = $db->prepare(
            "UPDATE fiscal_queue
             SET STATUS = 'DEAD_LETTER', LAST_ERROR = ?, LOCKED_AT = NULL, CLAIM_TOKEN = NULL,
                 NEXT_RETRY_AT = NULL
             WHERE ID = ? AND STATUS IN ('PENDING', 'RETRY') AND ATTEMPTS >= ?"
        );
        $stmt->execute([
            substr($reason, 0, 2000),
            $queueItem['ID'],
            (int) $queueItem['ATTEMPTS'],
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('Exhausted fiscal queue item could not be quarantined.');
        }
        $this->markFiscalError($db, $queueItem, $reason);
    }

    private function markFiscalError(\PDO $db, array $queueItem, string $error): void
    {
        $payload = json_decode((string) $queueItem['PAYLOAD_JSON'], true);
        if (is_array($payload) && isset($payload['fiscal_order'])) {
            $db->prepare(
                "UPDATE factura_registres
                 SET ESTAT_AEAT = 'ERROR', AEAT_RESPONSE_JSON = ?
                 WHERE UUID_FACTURA = ? AND FISCAL_ORDER = ?"
            )->execute([
                $this->encode(['status' => 'ERROR', 'message' => $error]),
                $queueItem['UUID_FACTURA'],
                $payload['fiscal_order'],
            ]);
        }
        $db->prepare("UPDATE factura SET ESTAT_AEAT = 'ERROR' WHERE UUID_FACTURA = ?")
            ->execute([$queueItem['UUID_FACTURA']]);
    }

    private function payload(array $queueItem): array
    {
        $payload = json_decode((string) $queueItem['PAYLOAD_JSON'], true);
        if (!is_array($payload) || !isset($payload['fiscal_order'])) {
            throw new \RuntimeException('Invalid queued fiscal payload.');
        }

        return $payload;
    }

    private function claimToken(array $queueItem): string
    {
        $token = (string) ($queueItem['CLAIM_TOKEN'] ?? '');
        if ($token === '') {
            throw new \RuntimeException('Missing fiscal queue claim token.');
        }
        return $token;
    }

    private function encode(array $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Could not encode AEAT response.');
        }

        return $json;
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
