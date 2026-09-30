<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\NovicePromotionEvidenceFilePolicy;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

/**
 * UC-111 documentary evidence persistence.
 *
 * Database metadata is committed as PREPARED before bytes are written. The
 * private object is then verified and the row moves to READY. A storage
 * failure is recorded as FAILED and can be retried with the same idempotency
 * key without creating another evidence row.
 */
final class NovicePromotionEvidenceService
{
    public function __construct(
        private UuidGenerator $uuids,
        private NovicePromotionEvidenceFilePolicy $policy,
        private NovicePromotionPrivateEvidenceStorageInterface $storage
    ) {
    }

    public function store(
        \PDO $db,
        string $uuidValidation,
        string $sourceLocalPath,
        string $originalName,
        string $mimeType,
        string $uploadedBy,
        string $uploadIdempotencyKey,
        string $purposeCode,
        string $retentionPolicyCode,
        ?\DateTimeImmutable $deleteAfter = null
    ): array {
        if ($db->inTransaction()) {
            throw new \LogicException('Evidence storage must control its own transactions.');
        }

        foreach ([
            'uuid_validation' => $uuidValidation,
            'uploaded_by' => $uploadedBy,
            'upload_idempotency_key' => $uploadIdempotencyKey,
            'purpose_code' => $purposeCode,
            'retention_policy_code' => $retentionPolicyCode,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw SifException::validation('Missing novice evidence field: ' . $field);
            }
        }
        if (strlen($uploadedBy) > 100 || strlen($uploadIdempotencyKey) > 140
            || strlen($purposeCode) > 60 || strlen($retentionPolicyCode) > 60
        ) {
            throw SifException::validation('Novice evidence metadata exceeds allowed length.');
        }

        $source = realpath($sourceLocalPath);
        if ($source === false || !is_file($source) || !is_readable($source)) {
            throw SifException::unavailable('Evidence source file is unavailable.');
        }

        $size = filesize($source);
        $sha256 = hash_file('sha256', $source);
        if ($size === false || $sha256 === false) {
            throw SifException::unavailable('Could not inspect evidence source file.');
        }

        $mimeType = strtolower(trim($mimeType));
        $sha256 = strtolower($sha256);
        $this->policy->assertAcceptable($mimeType, (int) $size, $sha256);
        $originalNameHash = hash('sha256', $originalName);
        $deleteAfterUtc = $deleteAfter?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $row = $this->prepareMetadata(
            $db,
            $uuidValidation,
            $mimeType,
            (int) $size,
            $sha256,
            $originalNameHash,
            $uploadedBy,
            $uploadIdempotencyKey,
            $purposeCode,
            $retentionPolicyCode,
            $deleteAfterUtc
        );

        if ((string) $row['STORAGE_STATUS'] === 'READY') {
            return $this->result($row, true);
        }

        try {
            $this->storage->putPrivate(
                $source,
                (string) $row['STORAGE_REF'],
                $mimeType,
                $sha256,
                (int) $size
            );
        } catch (\Throwable $exception) {
            $this->markFailed($db, (string) $row['UUID_EVIDENCE'], 'PRIVATE_STORAGE_WRITE_FAILED');
            throw $exception;
        }

        $storedAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                "UPDATE discount_evidence
                 SET STORAGE_STATUS='READY', STORED_AT=?,
                     STORAGE_FAILED_AT=NULL, LAST_STORAGE_ERROR_CODE=NULL
                 WHERE UUID_EVIDENCE=? AND STORAGE_STATUS IN ('PREPARED','FAILED')"
            );
            $stmt->execute([$storedAt, (string) $row['UUID_EVIDENCE']]);
            if ($stmt->rowCount() !== 1) {
                $current = $this->one(
                    $db,
                    'SELECT * FROM discount_evidence WHERE UUID_EVIDENCE=? FOR UPDATE',
                    [(string) $row['UUID_EVIDENCE']]
                );
                if ($current === null || (string) $current['STORAGE_STATUS'] !== 'READY') {
                    throw SifException::conflict('Evidence metadata changed while committing private storage.');
                }
            }
            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        $ready = $this->one(
            $db,
            'SELECT * FROM discount_evidence WHERE UUID_EVIDENCE=?',
            [(string) $row['UUID_EVIDENCE']]
        );
        if ($ready === null) {
            throw SifException::conflict('Stored evidence metadata disappeared.');
        }

        return $this->result($ready, false);
    }

    private function prepareMetadata(
        \PDO $db,
        string $uuidValidation,
        string $mimeType,
        int $size,
        string $sha256,
        string $originalNameHash,
        string $uploadedBy,
        string $uploadIdempotencyKey,
        string $purposeCode,
        string $retentionPolicyCode,
        ?string $deleteAfter
    ): array {
        $db->beginTransaction();
        try {
            $validation = $this->one(
                $db,
                'SELECT UUID_VALIDATION, DISCOUNT_TYPE, STATUS
                 FROM discount_validation WHERE UUID_VALIDATION=? FOR UPDATE',
                [$uuidValidation]
            );
            if ($validation === null
                || (string) $validation['DISCOUNT_TYPE'] !== NovicePromotionGrantService::VALIDATION_TYPE
                || (string) $validation['STATUS'] !== 'PENDING'
            ) {
                throw SifException::conflict('Evidence can only be attached to a pending novice validation.');
            }

            $existing = $this->one(
                $db,
                'SELECT * FROM discount_evidence WHERE UPLOAD_IDEMPOTENCY_KEY=? FOR UPDATE',
                [$uploadIdempotencyKey]
            );
            if ($existing === null) {
                $existing = $this->one(
                    $db,
                    'SELECT * FROM discount_evidence
                     WHERE UUID_VALIDATION=? AND CONTENT_HASH=? FOR UPDATE',
                    [$uuidValidation, $sha256]
                );
            }

            if ($existing !== null) {
                if ((string) $existing['UUID_VALIDATION'] !== $uuidValidation
                    || (string) $existing['CONTENT_HASH'] !== $sha256
                    || strtolower((string) $existing['MIME_TYPE']) !== $mimeType
                    || (int) $existing['SIZE_BYTES'] !== $size
                    || (string) $existing['PURPOSE_CODE'] !== $purposeCode
                    || (string) $existing['RETENTION_POLICY_CODE'] !== $retentionPolicyCode
                ) {
                    throw SifException::conflict('Evidence idempotency key or content is already bound to another snapshot.');
                }

                $db->commit();
                return $existing;
            }

            $uuidEvidence = $this->uuids->generate();
            $storageRef = 'novice-evidence/' . strtolower($uuidEvidence) . '.bin';
            $stmt = $db->prepare(
                "INSERT INTO discount_evidence
                 (UUID_EVIDENCE, UUID_VALIDATION, EVIDENCE_TYPE, STORAGE_REF,
                  CONTENT_HASH, MIME_TYPE, SIZE_BYTES, ACCESS_CLASSIFICATION,
                  PURPOSE_CODE, RETENTION_POLICY_CODE, DELETE_AFTER, UPLOADED_BY,
                  STORAGE_STATUS, UPLOAD_IDEMPOTENCY_KEY, ORIGINAL_NAME_HASH)
                 VALUES (?, ?, 'ACADEMIC_QUALIFICATION', ?, ?, ?, ?, 'RESTRICTED',
                         ?, ?, ?, ?, 'PREPARED', ?, ?)"
            );
            $stmt->execute([
                $uuidEvidence,
                $uuidValidation,
                $storageRef,
                $sha256,
                $mimeType,
                $size,
                $purposeCode,
                $retentionPolicyCode,
                $deleteAfter,
                $uploadedBy,
                $uploadIdempotencyKey,
                $originalNameHash,
            ]);

            $db->commit();

            return [
                'UUID_EVIDENCE' => $uuidEvidence,
                'UUID_VALIDATION' => $uuidValidation,
                'STORAGE_REF' => $storageRef,
                'CONTENT_HASH' => $sha256,
                'MIME_TYPE' => $mimeType,
                'SIZE_BYTES' => $size,
                'PURPOSE_CODE' => $purposeCode,
                'RETENTION_POLICY_CODE' => $retentionPolicyCode,
                'STORAGE_STATUS' => 'PREPARED',
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    private function markFailed(\PDO $db, string $uuidEvidence, string $errorCode): void
    {
        $failedAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $stmt = $db->prepare(
            "UPDATE discount_evidence
             SET STORAGE_STATUS='FAILED', STORED_AT=NULL,
                 STORAGE_FAILED_AT=?, LAST_STORAGE_ERROR_CODE=?
             WHERE UUID_EVIDENCE=? AND STORAGE_STATUS IN ('PREPARED','FAILED')"
        );
        $stmt->execute([$failedAt, $errorCode, $uuidEvidence]);
    }

    private function result(array $row, bool $reused): array
    {
        return [
            'uuid_evidence' => (string) $row['UUID_EVIDENCE'],
            'uuid_validation' => (string) $row['UUID_VALIDATION'],
            'content_hash' => (string) $row['CONTENT_HASH'],
            'mime_type' => (string) $row['MIME_TYPE'],
            'size_bytes' => (int) $row['SIZE_BYTES'],
            'storage_status' => (string) $row['STORAGE_STATUS'],
            'idempotency_reused' => $reused,
        ];
    }

    private function one(\PDO $db, string $sql, array $params): ?array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}
