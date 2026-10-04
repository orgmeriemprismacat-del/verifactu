<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Aeat\{EvidenceStore, XmlCodec};

final class AeatSubmissionAttemptRepository
{
    public function begin(\PDO $db, array $queueItem, array $payload): array
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
        $evidenceId = EvidenceStore::generateId();
        $stmt = $db->prepare(
            'INSERT INTO aeat_submission_attempt
             (UUID_ATTEMPT, FACTURA_REGISTRE_ID, FISCAL_QUEUE_ID, ATTEMPT_NO,
              ENVIRONMENT, ENDPOINT_CODE, REQUEST_HASH, EVIDENCE_ID, STATUS, STARTED_AT)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(6))'
        );
        $stmt->execute([
            $uuid,
            (int) $recordId,
            (int) $queueItem['ID'],
            (int) $queueItem['ATTEMPTS'],
            'preproduction',
            'AEAT_WORKER',
            $requestHash,
            $evidenceId,
            'STARTED',
        ]);

        return [
            'uuid_attempt' => $uuid,
            'evidence_id' => $evidenceId,
        ];
    }

    public function complete(\PDO $db, string $uuidAttempt, string $status, array $response): void
    {
        if (!in_array($status, ['ACCEPTED', 'ACCEPTED_WITH_ERRORS', 'REJECTED'], true)) {
            throw new \InvalidArgumentException('Invalid AEAT terminal attempt status.');
        }

        $json = json_encode(
            $response,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $evidenceId = $this->normalizeEvidenceId($response['evidence_id'] ?? null);
        $responseSha256 = strtolower(trim((string) ($response['response_sha256'] ?? '')));
        $responseHttpStatus = $response['evidence_http_status'] ?? null;
        if ($evidenceId === null
            || preg_match('/^[a-f0-9]{64}$/D', $responseSha256) !== 1
            || !is_int($responseHttpStatus)
            || $responseHttpStatus !== 200
        ) {
            throw new \RuntimeException(
                'AEAT terminal result requires the preassigned, anchored HTTP 200 evidence response.'
            );
        }
        $this->assertPreassignedEvidenceMatches($db, $uuidAttempt, $evidenceId);
        $this->assertTerminalEvidenceAnchored(
            $db,
            $uuidAttempt,
            $evidenceId,
            $responseSha256,
            $responseHttpStatus
        );
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

    public function fail(
        \PDO $db,
        string $uuidAttempt,
        string $status,
        string $detail,
        ?string $evidenceId = null
    ): void {
        if (!in_array($status, ['FAILED', 'UNCERTAIN'], true)) {
            throw new \InvalidArgumentException('Invalid AEAT attempt failure status.');
        }
        $evidenceId = $this->normalizeEvidenceId($evidenceId);
        if ($evidenceId !== null) {
            $this->assertPreassignedEvidenceMatches($db, $uuidAttempt, $evidenceId);
        }
        $stmt = $db->prepare(
            'UPDATE aeat_submission_attempt
             SET STATUS = ?, ERROR_DETAIL = ?, FINISHED_AT = NOW(6)
             WHERE UUID_ATTEMPT = ? AND STATUS = \'STARTED\''
        );
        $stmt->execute([
            $status,
            mb_substr($detail, 0, 2000, 'UTF-8'),
            $uuidAttempt,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('AEAT submission attempt is no longer open.');
        }
    }




    public function anchorEvidenceResponse(
        \PDO $db,
        string $uuidAttempt,
        string $evidenceId,
        string $responseSha256,
        int $httpStatus
    ): void {
        $evidenceId = $this->normalizeEvidenceId($evidenceId);
        if ($evidenceId === null
            || preg_match('/^[a-f0-9]{64}$/D', $responseSha256) !== 1
            || $httpStatus < 0
            || $httpStatus > 599
        ) {
            throw new \InvalidArgumentException('Invalid AEAT evidence response anchor.');
        }

        $stmt = $db->prepare(
            'SELECT EVIDENCE_ID, EVIDENCE_RESPONSE_SHA256, EVIDENCE_HTTP_STATUS, STATUS
             FROM aeat_submission_attempt
             WHERE UUID_ATTEMPT = ?
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$uuidAttempt]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)
            || !is_string($row['EVIDENCE_ID'])
            || !hash_equals($row['EVIDENCE_ID'], $evidenceId)
        ) {
            throw new \RuntimeException(
                'AEAT response anchor does not match the preassigned evidence.'
            );
        }

        $currentHash = $row['EVIDENCE_RESPONSE_SHA256'];
        $currentHttp = $row['EVIDENCE_HTTP_STATUS'];
        if ($currentHash !== null || $currentHttp !== null) {
            if (!is_string($currentHash)
                || !hash_equals($currentHash, $responseSha256)
                || (int) $currentHttp !== $httpStatus
            ) {
                throw new \RuntimeException(
                    'AEAT evidence response anchor is immutable and does not match.'
                );
            }
            return;
        }

        if (!in_array(strtoupper((string) $row['STATUS']), ['STARTED', 'UNCERTAIN'], true)) {
            throw new \RuntimeException(
                'AEAT evidence response can only be anchored on an open/review attempt.'
            );
        }

        $update = $db->prepare(
            'UPDATE aeat_submission_attempt
             SET EVIDENCE_RESPONSE_SHA256 = ?, EVIDENCE_HTTP_STATUS = ?
             WHERE UUID_ATTEMPT = ?
               AND EVIDENCE_ID = ?
               AND EVIDENCE_RESPONSE_SHA256 IS NULL
               AND EVIDENCE_HTTP_STATUS IS NULL'
        );
        $update->execute([
            $responseSha256,
            $httpStatus,
            $uuidAttempt,
            $evidenceId,
        ]);
        if ($update->rowCount() !== 1) {
            throw new \RuntimeException('AEAT evidence response anchor could not be persisted.');
        }
    }

    public function markStartedUncertain(\PDO $db, string $uuidAttempt, string $detail): void
    {
        $stmt = $db->prepare(
            'UPDATE aeat_submission_attempt
             SET STATUS = \'UNCERTAIN\', ERROR_DETAIL = ?, FINISHED_AT = NOW(6)
             WHERE UUID_ATTEMPT = ? AND STATUS = \'STARTED\''
        );
        $stmt->execute([
            mb_substr($detail, 0, 2000, 'UTF-8'),
            $uuidAttempt,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException(
                'AEAT started attempt could not be moved to uncertain review.'
            );
        }
    }

    public function completeUncertainFromEvidence(
        \PDO $db,
        string $uuidAttempt,
        string $evidenceId,
        string $status,
        array $response
    ): void {
        if (!in_array($status, ['ACCEPTED', 'ACCEPTED_WITH_ERRORS', 'REJECTED'], true)) {
            throw new \InvalidArgumentException('Invalid AEAT terminal evidence status.');
        }
        $evidenceId = $this->normalizeEvidenceId($evidenceId);
        if ($evidenceId === null) {
            throw new \InvalidArgumentException('Missing AEAT evidence id.');
        }

        $responseAnchor = strtolower(trim((string) (
            $response['evidence_response_sha256'] ?? ''
        )));
        $responseHttpStatus = $response['evidence_http_status'] ?? null;
        if (preg_match('/^[a-f0-9]{64}$/D', $responseAnchor) !== 1
            || !is_int($responseHttpStatus)
            || $responseHttpStatus !== 200
        ) {
            throw new \InvalidArgumentException(
                'AEAT evidence reconciliation requires a successful database response anchor.'
            );
        }

        $json = json_encode(
            $response,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $stmt = $db->prepare(
            'UPDATE aeat_submission_attempt
             SET STATUS = ?, RESPONSE_CODE = ?, RESPONSE_CSV = ?, RESPONSE_JSON = ?,
                 ERROR_CODE = ?, ERROR_DETAIL = ?, FINISHED_AT = NOW(6)
             WHERE UUID_ATTEMPT = ? AND STATUS = \'UNCERTAIN\' AND EVIDENCE_ID = ?
               AND EVIDENCE_RESPONSE_SHA256 = ? AND EVIDENCE_HTTP_STATUS = ?'
        );
        $stmt->execute([
            $status,
            $response['estado_registro'] ?? null,
            $response['csv'] ?? null,
            $json,
            $response['error_code'] ?? null,
            $response['error_message'] ?? null,
            $uuidAttempt,
            $evidenceId,
            $responseAnchor,
            $responseHttpStatus,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('AEAT uncertain attempt could not be finalized from evidence.');
        }
    }



    private function assertTerminalEvidenceAnchored(
        \PDO $db,
        string $uuidAttempt,
        string $evidenceId,
        string $responseSha256,
        int $responseHttpStatus
    ): void {
        $stmt = $db->prepare(
            'SELECT EVIDENCE_RESPONSE_SHA256, EVIDENCE_HTTP_STATUS, STATUS
             FROM aeat_submission_attempt
             WHERE UUID_ATTEMPT = ? AND EVIDENCE_ID = ?
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$uuidAttempt, $evidenceId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)
            || strtoupper((string) ($row['STATUS'] ?? '')) !== 'STARTED'
            || !is_string($row['EVIDENCE_RESPONSE_SHA256'] ?? null)
            || preg_match(
                '/^[a-f0-9]{64}$/D',
                (string) $row['EVIDENCE_RESPONSE_SHA256']
            ) !== 1
            || !hash_equals(
                strtolower((string) $row['EVIDENCE_RESPONSE_SHA256']),
                $responseSha256
            )
            || (int) ($row['EVIDENCE_HTTP_STATUS'] ?? 0) !== $responseHttpStatus
            || $responseHttpStatus !== 200
        ) {
            throw new \RuntimeException(
                'AEAT terminal result requires an anchored HTTP 200 evidence response.'
            );
        }
    }

    private function assertPreassignedEvidenceMatches(
        \PDO $db,
        string $uuidAttempt,
        string $evidenceId
    ): void {
        $stmt = $db->prepare(
            'SELECT EVIDENCE_ID
             FROM aeat_submission_attempt
             WHERE UUID_ATTEMPT = ?
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$uuidAttempt]);
        $current = $stmt->fetchColumn();
        if (!is_string($current)
            || $current === ''
            || !hash_equals($current, $evidenceId)
        ) {
            throw new \RuntimeException(
                'AEAT evidence id does not match the preassigned submission attempt.'
            );
        }
    }

    private function normalizeEvidenceId(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = trim((string) $value);
        if (preg_match('/^\d{8}T\d{6}Z-[a-f0-9]{24}$/D', $value) !== 1) {
            throw new \InvalidArgumentException('Invalid AEAT evidence id.');
        }
        return $value;
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
