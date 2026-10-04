<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

final class DocumentJobRepository
{
    public function __construct(private UuidGenerator $uuidGenerator)
    {
    }

    public function enqueue(
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

        if (preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',
            $uuidFactura
        ) !== 1) {
            throw SifException::validation('Invalid invoice UUID for document job');
        }
        if (!in_array($documentType, ['PDF', 'XML', 'QR'], true)) {
            throw SifException::validation('Invalid document job type');
        }
        if ($generatorVersion === '' || mb_strlen($generatorVersion, 'UTF-8') > 80) {
            throw SifException::validation('Invalid document generator version');
        }
        if ($correlationId === '' || mb_strlen($correlationId, 'UTF-8') > 120) {
            throw SifException::validation('Invalid document job correlation id');
        }

        $idempotencyKey = $this->idempotencyKey(
            $uuidFactura,
            $documentType,
            $generatorVersion
        );
        $existing = $this->findByIdempotencyKey($db, $idempotencyKey, true);
        if ($existing !== null) {
            return [
                'uuid_job' => (string) $existing['UUID_JOB'],
                'document_type' => (string) $existing['DOCUMENT_TYPE'],
                'status' => (string) $existing['STATUS'],
                'idempotency_reused' => true,
            ];
        }

        $uuidJob = $this->uuidGenerator->generate();

        try {
            $db->prepare(
                'INSERT INTO document_job (
                    UUID_JOB, UUID_FACTURA, DOCUMENT_TYPE, IDEMPOTENCY_KEY,
                    GENERATOR_VERSION, STATUS, ATTEMPTS, MAX_ATTEMPTS, CORRELATION_ID
                 ) VALUES (?, ?, ?, ?, ?, \'PENDING\', 0, 5, ?)'
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

            return [
                'uuid_job' => (string) $existing['UUID_JOB'],
                'document_type' => (string) $existing['DOCUMENT_TYPE'],
                'status' => (string) $existing['STATUS'],
                'idempotency_reused' => true,
            ];
        }

        return [
            'uuid_job' => $uuidJob,
            'document_type' => $documentType,
            'status' => 'PENDING',
            'idempotency_reused' => false,
        ];
    }

    private function findByIdempotencyKey(
        \PDO $db,
        string $idempotencyKey,
        bool $forUpdate
    ): ?array {
        $sql = 'SELECT UUID_JOB, DOCUMENT_TYPE, STATUS
                FROM document_job
                WHERE IDEMPOTENCY_KEY = ?
                LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $statement = $db->prepare($sql);
        $statement->execute([$idempotencyKey]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private function idempotencyKey(
        string $uuidFactura,
        string $documentType,
        string $generatorVersion
    ): string {
        $candidate = 'DOCUMENT|' . $documentType
            . '|FACT:' . $uuidFactura
            . '|GEN:' . $generatorVersion;

        if (strlen($candidate) <= 140) {
            return $candidate;
        }

        return 'DOCUMENT|' . $documentType
            . '|FACT:' . $uuidFactura
            . '|GENHASH:' . substr(hash('sha256', $generatorVersion), 0, 24);
    }
}
