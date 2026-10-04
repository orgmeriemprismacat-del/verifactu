<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Service\PayloadIdempotencyValidator;

final class SifVersionActivationRepository
{
    public function __construct(
        private ?UuidGenerator $uuidGenerator = null,
        private ?PayloadIdempotencyValidator $idempotency = null
    ) {
        $this->uuidGenerator ??= new UuidGenerator();
        $this->idempotency ??= new PayloadIdempotencyValidator();
    }

    public function findByIdempotencyKey(\PDO $db, string $key, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM sif_version_activation WHERE IDEMPOTENCY_KEY = ? LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function assertReplay(array $existing, array $input): void
    {
        $payload = [
            'uuid_version' => (string) ($input['uuid_version'] ?? ''),
            'previous_uuid_version' => $existing['PREVIOUS_UUID_VERSION'] ?? null,
            'uuid_declaration' => (string) ($existing['UUID_DECLARATION'] ?? ''),
            'uuid_backup_evidence' => $input['uuid_backup_evidence'] ?? null,
            'actor_id' => (string) ($input['actor_id'] ?? ''),
            'actor_role' => (string) ($input['actor_role'] ?? ''),
            'reason_code' => (string) ($input['reason_code'] ?? ''),
            'correlation_id' => (string) ($input['correlation_id'] ?? ''),
            'environment' => (string) ($existing['ENVIRONMENT'] ?? ''),
            'runtime_git_revision' => (string) ($existing['RUNTIME_GIT_REVISION'] ?? ''),
            'runtime_artifact_hash' => (string) ($existing['RUNTIME_ARTIFACT_HASH'] ?? ''),
            'runtime_config_hash' => (string) ($existing['RUNTIME_CONFIG_HASH'] ?? ''),
            'runtime_database_version' => (string) ($existing['RUNTIME_DATABASE_VERSION'] ?? ''),
        ];

        $this->idempotency->assertMatches(
            $payload,
            (string) ($existing['IDEMPOTENCY_PAYLOAD_HASH'] ?? '')
        );
    }

    public function append(\PDO $db, array $input): array
    {
        $payload = [
            'uuid_version' => (string) $input['uuid_version'],
            'previous_uuid_version' => $input['previous_uuid_version'] ?? null,
            'uuid_declaration' => (string) $input['uuid_declaration'],
            'uuid_backup_evidence' => $input['uuid_backup_evidence'] ?? null,
            'actor_id' => (string) $input['actor_id'],
            'actor_role' => (string) $input['actor_role'],
            'reason_code' => (string) $input['reason_code'],
            'correlation_id' => (string) $input['correlation_id'],
            'environment' => (string) $input['environment'],
            'runtime_git_revision' => (string) $input['runtime_git_revision'],
            'runtime_artifact_hash' => (string) $input['runtime_artifact_hash'],
            'runtime_config_hash' => (string) $input['runtime_config_hash'],
            'runtime_database_version' => (string) $input['runtime_database_version'],
        ];
        $idempotencyKey = trim((string) ($input['idempotency_key'] ?? ''));
        $payloadHash = $this->idempotency->calculateHash($payload);

        $existing = $this->findByIdempotencyKey($db, $idempotencyKey);
        if ($existing !== null) {
            $this->idempotency->assertMatches($payload, (string) ($existing['IDEMPOTENCY_PAYLOAD_HASH'] ?? ''));
            return ['reused' => true, 'activation' => $existing];
        }

        $uuid = $this->uuidGenerator->generate();
        $evidenceJson = json_encode(
            $input['runtime_evidence'] ?? [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $db->prepare(
            'INSERT INTO sif_version_activation (
                UUID_ACTIVATION, IDEMPOTENCY_KEY, IDEMPOTENCY_PAYLOAD_HASH,
                UUID_VERSION, PREVIOUS_UUID_VERSION, UUID_DECLARATION, UUID_BACKUP_EVIDENCE,
                ACTOR_ID, ACTOR_ROLE, REASON_CODE, CORRELATION_ID, ENVIRONMENT,
                RUNTIME_GIT_REVISION, RUNTIME_ARTIFACT_HASH, RUNTIME_CONFIG_HASH,
                RUNTIME_DATABASE_VERSION, RUNTIME_EVIDENCE_JSON, STATUS
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'ACTIVATED\')'
        )->execute([
            $uuid,
            $idempotencyKey,
            $payloadHash,
            $payload['uuid_version'],
            $payload['previous_uuid_version'],
            $payload['uuid_declaration'],
            $payload['uuid_backup_evidence'],
            $payload['actor_id'],
            $payload['actor_role'],
            $payload['reason_code'],
            $payload['correlation_id'],
            $payload['environment'],
            $payload['runtime_git_revision'],
            $payload['runtime_artifact_hash'],
            $payload['runtime_config_hash'],
            $payload['runtime_database_version'],
            $evidenceJson,
        ]);

        return ['reused' => false, 'activation' => $this->findByIdempotencyKey($db, $idempotencyKey)];
    }

    public function listByVersion(\PDO $db, string $uuidVersion): array
    {
        $stmt = $db->prepare(
            'SELECT * FROM sif_version_activation
             WHERE UUID_VERSION = ?
             ORDER BY CREATED_AT DESC, ID DESC'
        );
        $stmt->execute([$uuidVersion]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
