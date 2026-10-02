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
