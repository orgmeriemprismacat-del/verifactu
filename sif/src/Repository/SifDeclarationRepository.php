<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\PayloadIdempotencyValidator;

final class SifDeclarationRepository
{
    public function __construct(
        private ?UuidGenerator $uuidGenerator = null,
        private ?PayloadIdempotencyValidator $idempotency = null
    ) {
        $this->uuidGenerator ??= new UuidGenerator();
        $this->idempotency ??= new PayloadIdempotencyValidator();
    }

    public function appendApproved(\PDO $db, array $input): array
    {
        $uuidVersion = strtolower(trim((string) ($input['uuid_version'] ?? '')));
        $declarationVersion = trim((string) ($input['declaration_version'] ?? ''));
        $documentHash = strtolower(trim((string) ($input['document_hash'] ?? '')));
        $storageKey = trim((string) ($input['storage_key'] ?? ''));
        $approvedBy = trim((string) ($input['approved_by'] ?? ''));
        $approvedAt = trim((string) ($input['approved_at'] ?? ''));
        $idempotencyKey = trim((string) ($input['idempotency_key'] ?? ''));

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $uuidVersion) !== 1) {
            throw SifException::validation('Invalid declaration version UUID');
        }
        if ($declarationVersion === '' || strlen($declarationVersion) > 40) {
            throw SifException::validation('Invalid declaration version');
        }
        if (preg_match('/^[0-9a-f]{64}$/D', $documentHash) !== 1) {
            throw SifException::validation('Invalid declaration document hash');
        }
        if ($storageKey === '' || strlen($storageKey) > 255) {
            throw SifException::validation('Invalid declaration storage key');
        }
        if ($approvedBy === '' || strlen($approvedBy) > 120 || $approvedAt === '') {
            throw SifException::validation('Invalid declaration approval');
        }
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 140) {
            throw SifException::validation('Invalid declaration idempotency key');
        }

        $payload = [
            'uuid_version' => $uuidVersion,
            'declaration_version' => $declarationVersion,
            'document_hash' => $documentHash,
            'storage_key' => $storageKey,
            'approved_by' => $approvedBy,
        ];

        $existing = $this->findByIdempotencyKey($db, $idempotencyKey);
        if ($existing !== null) {
            $this->idempotency->assertMatches($payload, (string) ($existing['IDEMPOTENCY_PAYLOAD_HASH'] ?? ''));
            return ['reused' => true, 'declaration' => $existing];
        }

        $uuid = $this->uuidGenerator->generate();
        $hash = $this->idempotency->calculateHash($payload);

        try {
            $db->prepare(
                'INSERT INTO sif_declaration (
                    UUID_DECLARATION, UUID_VERSION, DECLARATION_VERSION, DOCUMENT_HASH,
                    STORAGE_KEY, APPROVED_BY, APPROVED_AT, STATUS,
                    IDEMPOTENCY_KEY, IDEMPOTENCY_PAYLOAD_HASH
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, \'APPROVED\', ?, ?)'
            )->execute([
                $uuid,
                $uuidVersion,
                $declarationVersion,
                $documentHash,
                $storageKey,
                $approvedBy,
                $approvedAt,
                $idempotencyKey,
                $hash,
            ]);
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                $existing = $this->findByIdempotencyKey($db, $idempotencyKey, true);
                if ($existing !== null) {
                    $this->idempotency->assertMatches($payload, (string) ($existing['IDEMPOTENCY_PAYLOAD_HASH'] ?? ''));
                    return ['reused' => true, 'declaration' => $existing];
                }
                throw SifException::conflict('Declaration version already exists for this SIF version');
            }
            throw $exception;
        }

        return [
            'reused' => false,
            'declaration' => $this->findByUuid($db, $uuid) ?? throw new \RuntimeException('Created declaration not found'),
        ];
    }

    public function findLatestApprovedByVersion(\PDO $db, string $uuidVersion, bool $forUpdate = false): ?array
    {
        $sql = "SELECT * FROM sif_declaration
                WHERE UUID_VERSION = ? AND STATUS = 'APPROVED'
                ORDER BY APPROVED_AT DESC, ID DESC LIMIT 1";
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([$uuidVersion]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function findByUuid(\PDO $db, string $uuid): ?array
    {
        $stmt = $db->prepare('SELECT * FROM sif_declaration WHERE UUID_DECLARATION = ? LIMIT 1');
        $stmt->execute([$uuid]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function findByIdempotencyKey(\PDO $db, string $key, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM sif_declaration WHERE IDEMPOTENCY_KEY = ? LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
