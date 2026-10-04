<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\PayloadIdempotencyValidator;

final class SifVersionRepository
{
    public function __construct(
        private ?UuidGenerator $uuidGenerator = null,
        private ?PayloadIdempotencyValidator $idempotency = null
    ) {
        $this->uuidGenerator ??= new UuidGenerator();
        $this->idempotency ??= new PayloadIdempotencyValidator();
    }

    public function registerCandidate(\PDO $db, array $input): array
    {
        $versionCode = trim((string) ($input['version_code'] ?? ''));
        $gitRevision = strtolower(trim((string) ($input['git_revision'] ?? '')));
        $artifactHash = strtolower(trim((string) ($input['artifact_hash'] ?? '')));
        $configHash = strtolower(trim((string) ($input['config_hash'] ?? '')));
        $databaseVersion = trim((string) ($input['database_version'] ?? ''));
        $createdBy = trim((string) ($input['created_by'] ?? ''));
        $idempotencyKey = trim((string) ($input['idempotency_key'] ?? ''));

        if (preg_match('/^[A-Za-z0-9._-]{1,80}$/D', $versionCode) !== 1) {
            throw SifException::validation('Invalid SIF version code');
        }
        foreach (['git revision' => [$gitRevision, 40], 'artifact hash' => [$artifactHash, 64], 'config hash' => [$configHash, 64]] as $label => [$value, $length]) {
            if (preg_match('/^[0-9a-f]{' . $length . '}$/D', $value) !== 1) {
                throw SifException::validation('Invalid SIF ' . $label);
            }
        }
        if ($databaseVersion === '' || strlen($databaseVersion) > 80) {
            throw SifException::validation('Invalid SIF database version');
        }
        if ($createdBy === '' || strlen($createdBy) > 120) {
            throw SifException::validation('Invalid SIF version creator');
        }
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 140) {
            throw SifException::validation('Invalid SIF version idempotency key');
        }

        $payload = [
            'version_code' => $versionCode,
            'git_revision' => $gitRevision,
            'artifact_hash' => $artifactHash,
            'config_hash' => $configHash,
            'database_version' => $databaseVersion,
            'created_by' => $createdBy,
            'reason_code' => strtoupper(trim((string) ($input['reason_code'] ?? ''))),
        ];
        $payloadHash = $this->idempotency->calculateHash($payload);

        $existing = $this->findByIdempotencyKey($db, $idempotencyKey);
        if ($existing !== null) {
            $this->idempotency->assertMatches($payload, (string) ($existing['IDEMPOTENCY_PAYLOAD_HASH'] ?? ''));
            return ['reused' => true, 'version' => $existing];
        }

        $uuid = $this->uuidGenerator->generate();

        try {
            $db->prepare(
                'INSERT INTO sif_version (
                    UUID_VERSION, VERSION_CODE, GIT_REVISION, ARTIFACT_HASH, CONFIG_HASH,
                    DATABASE_VERSION, STATUS, CREATED_BY, IDEMPOTENCY_KEY, IDEMPOTENCY_PAYLOAD_HASH
                 ) VALUES (?, ?, ?, ?, ?, ?, \'DRAFT\', ?, ?, ?)'
            )->execute([
                $uuid,
                $versionCode,
                $gitRevision,
                $artifactHash,
                $configHash,
                $databaseVersion,
                $createdBy,
                $idempotencyKey,
                $payloadHash,
            ]);
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                $existing = $this->findByIdempotencyKey($db, $idempotencyKey, true);
                if ($existing !== null) {
                    $this->idempotency->assertMatches($payload, (string) ($existing['IDEMPOTENCY_PAYLOAD_HASH'] ?? ''));
                    return ['reused' => true, 'version' => $existing];
                }
                throw SifException::conflict('SIF version code already exists');
            }
            throw $exception;
        }

        return [
            'reused' => false,
            'version' => $this->findByUuid($db, $uuid) ?? throw new \RuntimeException('Created SIF version not found'),
        ];
    }

    public function assertReplay(array $existing, array $input): void
    {
        $payload = [
            'version_code' => trim((string) ($input['version_code'] ?? '')),
            'git_revision' => strtolower((string) ($existing['GIT_REVISION'] ?? '')),
            'artifact_hash' => strtolower((string) ($existing['ARTIFACT_HASH'] ?? '')),
            'config_hash' => strtolower((string) ($existing['CONFIG_HASH'] ?? '')),
            'database_version' => (string) ($existing['DATABASE_VERSION'] ?? ''),
            'created_by' => trim((string) ($input['created_by'] ?? '')),
            'reason_code' => strtoupper(trim((string) ($input['reason_code'] ?? ''))),
        ];

        $this->idempotency->assertMatches(
            $payload,
            (string) ($existing['IDEMPOTENCY_PAYLOAD_HASH'] ?? '')
        );
    }

    public function findByUuid(\PDO $db, string $uuid, bool $forUpdate = false): ?array
    {
        $uuid = strtolower(trim($uuid));
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $uuid) !== 1) {
            throw SifException::validation('Invalid SIF version UUID');
        }

        $sql = 'SELECT * FROM sif_version WHERE UUID_VERSION = ? LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([$uuid]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function list(\PDO $db, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return $db->query(
            'SELECT * FROM sif_version ORDER BY CREATED_AT DESC, ID DESC LIMIT ' . $limit
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function lockState(\PDO $db): array
    {
        $stmt = $db->query('SELECT * FROM sif_version_state WHERE ID = 1 FOR UPDATE');
        $state = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($state)) {
            $db->exec('INSERT INTO sif_version_state (ID, ACTIVE_UUID_VERSION, LOCK_VERSION) VALUES (1, NULL, 0)');
            $state = $db->query('SELECT * FROM sif_version_state WHERE ID = 1 FOR UPDATE')->fetch(\PDO::FETCH_ASSOC);
        }

        return is_array($state) ? $state : throw new \RuntimeException('SIF version state unavailable');
    }

    public function activeRowsForUpdate(\PDO $db): array
    {
        return $db->query(
            "SELECT * FROM sif_version WHERE STATUS = 'ACTIVE' ORDER BY ACTIVATED_AT DESC, ID DESC FOR UPDATE"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function activate(\PDO $db, string $uuid): void
    {
        $candidate = $this->findByUuid($db, $uuid, true);
        if ($candidate === null) {
            throw SifException::notFound('SIF version candidate not found');
        }
        if (strtoupper((string) ($candidate['STATUS'] ?? '')) !== 'DRAFT') {
            throw SifException::conflict('Only a DRAFT SIF version can be activated');
        }

        $db->prepare(
            "UPDATE sif_version
             SET STATUS = 'SUPERSEDED'
             WHERE STATUS = 'ACTIVE' AND UUID_VERSION <> ?"
        )->execute([$uuid]);

        $stmt = $db->prepare(
            "UPDATE sif_version
             SET STATUS = 'ACTIVE', ACTIVATED_AT = NOW(6)
             WHERE UUID_VERSION = ? AND STATUS = 'DRAFT'"
        );
        $stmt->execute([$uuid]);
        if ($stmt->rowCount() !== 1) {
            throw SifException::conflict('SIF version candidate changed before activation');
        }

        $db->prepare(
            'UPDATE sif_version_state
             SET ACTIVE_UUID_VERSION = ?, LOCK_VERSION = LOCK_VERSION + 1
             WHERE ID = 1'
        )->execute([$uuid]);
    }

    public function findByIdempotencyKey(\PDO $db, string $key, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM sif_version WHERE IDEMPOTENCY_KEY = ? LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
