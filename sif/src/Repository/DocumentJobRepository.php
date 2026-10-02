<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

final class DocumentJobRepository
{
    public function __construct(private ?UuidGenerator $uuidGenerator = null)
    {
        $this->uuidGenerator ??= new UuidGenerator();
    }

    public function ensurePending(
        \PDO $db,
        string $uuidFactura,
        string $documentType,
        string $generatorVersion,
        string $correlationId
    ): array {
        $uuidFactura = strtolower(trim($uuidFactura));
        $documentType = strtoupper(trim($documentType));
        $generatorVersion = trim($generatorVersion);
        $correlationId = trim($correlationId);

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $uuidFactura) !== 1) {
            throw SifException::validation('Invalid document job invoice UUID');
        }
        if (!in_array($documentType, ['PDF', 'XML', 'QR'], true)) {
            throw SifException::validation('Invalid document job type');
        }
        if ($generatorVersion === '' || strlen($generatorVersion) > 80) {
            throw SifException::validation('Invalid document generator version');
        }
        if ($correlationId === '' || strlen($correlationId) > 120) {
            throw SifException::validation('Invalid document job correlation id');
        }

        $idempotencyKey = $this->idempotencyKey(
            $uuidFactura,
            $documentType,
            $generatorVersion
        );

        $existing = $this->findByIdempotencyKey($db, $idempotencyKey, true);
        if ($existing !== null) {
            return $this->result($existing, true);
        }

        $uuidJob = $this->uuidGenerator->generate();

        try {
            $db->prepare(
                'INSERT INTO document_job (
                    UUID_JOB, UUID_FACTURA, DOCUMENT_TYPE, IDEMPOTENCY_KEY,
                    GENERATOR_VERSION, STATUS, CORRELATION_ID
                 ) VALUES (?, ?, ?, ?, ?, \'PENDING\', ?)'
            )->execute([
                $uuidJob,
                $uuidFactura,
                $documentType,
                $idempotencyKey,
                $generatorVersion,
                $correlationId,
            ]);
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            $existing = $this->findByIdempotencyKey($db, $idempotencyKey, true);
            if ($existing === null) {
                throw $exception;
            }

            return $this->result($existing, true);
        }

        return [
            'ok' => true,
            'reused' => false,
            'document_job_id' => (int) $db->lastInsertId(),
            'uuid_job' => $uuidJob,
            'uuid_factura' => $uuidFactura,
            'document_type' => $documentType,
            'generator_version' => $generatorVersion,
            'status' => 'PENDING',
            'idempotency_key' => $idempotencyKey,
            'correlation_id' => $correlationId,
        ];
    }

    public function claimNext(\PDO $db, ?\DateTimeImmutable $now = null): ?array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid'));
        $timestamp = $now->format('Y-m-d H:i:s.u');

        $stmt = $db->prepare(
            "SELECT *
             FROM document_job
             WHERE STATUS IN ('PENDING', 'RETRY')
               AND ATTEMPTS < MAX_ATTEMPTS
               AND (NEXT_ATTEMPT_AT IS NULL OR NEXT_ATTEMPT_AT <= ?)
             ORDER BY CREATED_AT ASC, ID ASC
             LIMIT 1
             FOR UPDATE SKIP LOCKED"
        );
        $stmt->execute([$timestamp]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        $update = $db->prepare(
            "UPDATE document_job
             SET STATUS = 'PROCESSING',
                 ATTEMPTS = ATTEMPTS + 1,
                 LOCKED_AT = ?,
                 NEXT_ATTEMPT_AT = NULL
             WHERE ID = ?
               AND STATUS IN ('PENDING', 'RETRY')"
        );
        $update->execute([$timestamp, (int) $row['ID']]);
        if ($update->rowCount() !== 1) {
            throw SifException::conflict('Document job could not be claimed');
        }

        $claimed = $this->findById($db, (int) $row['ID'], true);
        if ($claimed === null) {
            throw SifException::conflict('Claimed document job disappeared');
        }

        return $claimed;
    }

    public function complete(
        \PDO $db,
        int $jobId,
        int $documentId,
        string $storageKey,
        string $outputHash,
        ?\DateTimeImmutable $finishedAt = null
    ): array {
        if ($jobId < 1 || $documentId < 1) {
            throw SifException::validation('Invalid document completion identifiers');
        }

        $storageKey = trim($storageKey);
        $outputHash = strtolower(trim($outputHash));
        if ($storageKey === '' || strlen($storageKey) > 255) {
            throw SifException::validation('Invalid document storage key');
        }
        if (preg_match('/^[0-9a-f]{64}$/D', $outputHash) !== 1) {
            throw SifException::validation('Invalid document output hash');
        }

        $finishedAt ??= new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid'));

        $stmt = $db->prepare(
            "UPDATE document_job
             SET STATUS = 'COMPLETED',
                 FACTURA_DOCUMENT_ID = ?,
                 STORAGE_KEY = ?,
                 OUTPUT_HASH = ?,
                 LAST_ERROR = NULL,
                 LOCKED_AT = NULL,
                 NEXT_ATTEMPT_AT = NULL,
                 FINISHED_AT = ?
             WHERE ID = ? AND STATUS = 'PROCESSING'"
        );
        $stmt->execute([
            $documentId,
            $storageKey,
            $outputHash,
            $finishedAt->format('Y-m-d H:i:s.u'),
            $jobId,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw SifException::conflict('Document job is not in PROCESSING state');
        }

        $row = $this->findById($db, $jobId, false);
        if ($row === null) {
            throw SifException::conflict('Completed document job disappeared');
        }

        return $row;
    }

    public function fail(
        \PDO $db,
        int $jobId,
        string $error,
        int $retrySeconds = 60,
        ?\DateTimeImmutable $now = null
    ): array {
        if ($jobId < 1) {
            throw SifException::validation('Invalid document job id');
        }

        $error = trim($error);
        if ($error === '') {
            $error = 'Unknown document processing error';
        }
        if (mb_strlen($error, 'UTF-8') > 4000) {
            $error = mb_substr($error, 0, 4000, 'UTF-8');
        }

        $job = $this->findById($db, $jobId, true);
        if ($job === null) {
            throw SifException::notFound('Document job not found');
        }
        if (strtoupper((string) $job['STATUS']) !== 'PROCESSING') {
            throw SifException::conflict('Document job is not in PROCESSING state');
        }

        $attempts = (int) $job['ATTEMPTS'];
        $maxAttempts = (int) $job['MAX_ATTEMPTS'];
        $terminal = $attempts >= $maxAttempts;
        $status = $terminal ? 'ERROR' : 'RETRY';

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid'));
        $nextAttempt = $terminal
            ? null
            : $now->modify('+' . max(1, $retrySeconds) . ' seconds')->format('Y-m-d H:i:s.u');

        $stmt = $db->prepare(
            'UPDATE document_job
             SET STATUS = ?,
                 LAST_ERROR = ?,
                 LOCKED_AT = NULL,
                 NEXT_ATTEMPT_AT = ?,
                 FINISHED_AT = ?
             WHERE ID = ? AND STATUS = \'PROCESSING\''
        );
        $stmt->execute([
            $status,
            $error,
            $nextAttempt,
            $terminal ? $now->format('Y-m-d H:i:s.u') : null,
            $jobId,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw SifException::conflict('Document job failure could not be recorded');
        }

        $updated = $this->findById($db, $jobId, false);
        if ($updated === null) {
            throw SifException::conflict('Failed document job disappeared');
        }

        return $updated;
    }

    public function findById(\PDO $db, int $jobId, bool $forUpdate = false): ?array
    {
        if ($jobId < 1) {
            throw SifException::validation('Invalid document job id');
        }

        $sql = 'SELECT * FROM document_job WHERE ID = ? LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$jobId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function findByInvoiceAndType(
        \PDO $db,
        string $uuidFactura,
        string $documentType
    ): array {
        $stmt = $db->prepare(
            'SELECT *
             FROM document_job
             WHERE UUID_FACTURA = ? AND DOCUMENT_TYPE = ?
             ORDER BY CREATED_AT DESC, ID DESC'
        );
        $stmt->execute([
            strtolower(trim($uuidFactura)),
            strtoupper(trim($documentType)),
        ]);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    private function findByIdempotencyKey(
        \PDO $db,
        string $idempotencyKey,
        bool $forUpdate = false
    ): ?array {
        $sql = 'SELECT * FROM document_job WHERE IDEMPOTENCY_KEY = ? LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$idempotencyKey]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function idempotencyKey(
        string $uuidFactura,
        string $documentType,
        string $generatorVersion
    ): string {
        return 'DOCUMENT|'
            . $documentType
            . '|'
            . hash('sha256', $uuidFactura . '|' . $generatorVersion);
    }

    private function result(array $row, bool $reused): array
    {
        return [
            'ok' => true,
            'reused' => $reused,
            'document_job_id' => (int) $row['ID'],
            'uuid_job' => (string) $row['UUID_JOB'],
            'uuid_factura' => (string) $row['UUID_FACTURA'],
            'document_type' => (string) $row['DOCUMENT_TYPE'],
            'generator_version' => (string) $row['GENERATOR_VERSION'],
            'status' => (string) $row['STATUS'],
            'idempotency_key' => (string) $row['IDEMPOTENCY_KEY'],
            'correlation_id' => (string) $row['CORRELATION_ID'],
        ];
    }
}
