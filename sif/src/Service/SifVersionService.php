<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\BackupRestoreEvidenceRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Repository\SifAuditEventRepository;
use Prisma\Sif\Repository\SifDeclarationRepository;
use Prisma\Sif\Repository\SifVersionActivationRepository;
use Prisma\Sif\Repository\SifVersionRepository;

final class SifVersionService
{
    private OperationalEventRepository $operationalEvents;

    public function __construct(
        private \PDO $db,
        private TransactionRunner $transactions,
        private SifVersionRepository $versions,
        private SifDeclarationRepository $declarations,
        private SifVersionActivationRepository $activations,
        private BackupRestoreEvidenceRepository $backupEvidence,
        private SifAuditEventRepository $auditEvents,
        private RuntimeVersionInspector $runtimeInspector,
        private array $config
    ) {
        $this->operationalEvents = new OperationalEventRepository(new UuidGenerator());
    }

    public function runtime(array $actor): array
    {
        $this->assertRead($actor);

        return [
            'ok' => true,
            'runtime' => $this->runtimeInspector->inspect($this->db, $this->config),
        ];
    }

    public function list(array $actor, int $limit = 50): array
    {
        $this->assertRead($actor);
        $state = $this->db->query('SELECT * FROM sif_version_state WHERE ID = 1')->fetch(\PDO::FETCH_ASSOC);

        return [
            'ok' => true,
            'active_uuid_version' => is_array($state) ? ($state['ACTIVE_UUID_VERSION'] ?? null) : null,
            'versions' => array_map(
                fn (array $version): array => $this->versionOutput($version),
                $this->versions->list($this->db, $limit)
            ),
        ];
    }

    public function view(array $actor, string $uuidVersion): array
    {
        $this->assertRead($actor);
        $version = $this->versions->findByUuid($this->db, $uuidVersion);
        if ($version === null) {
            throw SifException::notFound('SIF version not found');
        }

        return [
            'ok' => true,
            'version' => $this->versionOutput($version),
            'declaration' => ($declaration = $this->declarations->findLatestApprovedByVersion($this->db, $uuidVersion))
                ? $this->declarationOutput($declaration)
                : null,
            'activations' => array_map(
                fn (array $activation): array => $this->activationOutput($activation),
                $this->activations->listByVersion($this->db, $uuidVersion)
            ),
        ];
    }

    public function registerCurrentRuntime(array $actor, array $input): array
    {
        $role = $this->assertManage($actor);
        $actorId = $this->actorId($actor);
        $operation = $this->operationInput($input, 'REGISTER_VERSION');
        $versionCode = trim((string) ($input['version_code'] ?? ''));

        $existingReplay = $this->versions->findByIdempotencyKey(
            $this->db,
            $operation['idempotency_key']
        );
        if ($existingReplay !== null) {
            $this->versions->assertReplay($existingReplay, [
                'version_code' => $versionCode,
                'created_by' => $actorId,
                'reason_code' => $operation['reason_code'],
            ]);
            return ['ok' => true, 'reused' => true, 'version' => $this->versionOutput($existingReplay)];
        }

        $runtime = $this->runtimeInspector->inspect($this->db, $this->config);
        if (($runtime['complete'] ?? false) !== true) {
            throw SifException::unavailable('Current SIF runtime cannot be registered because its evidence is incomplete');
        }

        return $this->transactions->run(function (\PDO $db) use (
            $actor,
            $role,
            $actorId,
            $operation,
            $versionCode,
            $runtime
        ): array {
            $result = $this->versions->registerCandidate($db, [
                'version_code' => $versionCode,
                'git_revision' => $runtime['git_revision'],
                'artifact_hash' => $runtime['artifact_hash'],
                'config_hash' => $runtime['config_hash'],
                'database_version' => $runtime['database_version'],
                'created_by' => $actorId,
                'reason_code' => $operation['reason_code'],
                'idempotency_key' => $operation['idempotency_key'],
            ]);

            if (($result['reused'] ?? false) !== true) {
                $version = (array) $result['version'];
                $this->appendMutationEvents(
                    $db,
                    $actor,
                    $role,
                    $operation,
                    'VERSION_REGISTERED',
                    'SIF_VERSION',
                    (string) $version['UUID_VERSION'],
                    null,
                    [
                        'version_code' => $version['VERSION_CODE'],
                        'git_revision' => $version['GIT_REVISION'],
                        'artifact_hash' => $version['ARTIFACT_HASH'],
                        'config_hash' => $version['CONFIG_HASH'],
                        'database_version' => $version['DATABASE_VERSION'],
                        'status' => $version['STATUS'],
                    ]
                );
            }

            return [
                'ok' => true,
                'reused' => (bool) ($result['reused'] ?? false),
                'version' => $this->versionOutput((array) $result['version']),
            ];
        });
    }

    public function attachDeclaration(array $actor, string $uuidVersion, array $input): array
    {
        $role = $this->assertManage($actor);
        $actorId = $this->actorId($actor);
        $operation = $this->operationInput($input, 'APPROVE_DECLARATION');
        $declarationVersion = trim((string) ($input['declaration_version'] ?? ''));
        $storageKey = trim((string) ($input['storage_key'] ?? ''));

        $existingReplay = $this->declarations->findByIdempotencyKey(
            $this->db,
            $operation['idempotency_key']
        );
        if ($existingReplay !== null) {
            $this->declarations->assertReplay($existingReplay, [
                'uuid_version' => $uuidVersion,
                'declaration_version' => $declarationVersion,
                'storage_key' => $storageKey,
                'approved_by' => $actorId,
                'reason_code' => $operation['reason_code'],
            ]);
            return ['ok' => true, 'reused' => true, 'declaration' => $this->declarationOutput($existingReplay)];
        }

        $version = $this->versions->findByUuid($this->db, $uuidVersion);
        if ($version === null) {
            throw SifException::notFound('SIF version not found');
        }
        if (strtoupper((string) ($version['STATUS'] ?? '')) !== 'DRAFT') {
            throw SifException::conflict('Declarations can only be attached to a DRAFT SIF version');
        }

        $document = $this->inspectDeclarationFile($storageKey);
        $approvedAt = $this->now();

        return $this->transactions->run(function (\PDO $db) use (
            $actor,
            $role,
            $actorId,
            $operation,
            $uuidVersion,
            $declarationVersion,
            $storageKey,
            $document,
            $approvedAt
        ): array {
            $lockedVersion = $this->versions->findByUuid($db, $uuidVersion, true);
            if ($lockedVersion === null) {
                throw SifException::notFound('SIF version not found');
            }

            $existingReplay = $this->declarations->findByIdempotencyKey(
                $db,
                $operation['idempotency_key'],
                true
            );
            if ($existingReplay !== null) {
                $this->declarations->assertReplay($existingReplay, [
                    'uuid_version' => $uuidVersion,
                    'declaration_version' => $declarationVersion,
                    'storage_key' => $storageKey,
                    'approved_by' => $actorId,
                    'reason_code' => $operation['reason_code'],
                ]);
                return ['ok' => true, 'reused' => true, 'declaration' => $this->declarationOutput($existingReplay)];
            }

            if (strtoupper((string) ($lockedVersion['STATUS'] ?? '')) !== 'DRAFT') {
                throw SifException::conflict('Declarations can only be attached to a DRAFT SIF version');
            }

            $result = $this->declarations->appendApproved($db, [
                'uuid_version' => $uuidVersion,
                'declaration_version' => $declarationVersion,
                'document_hash' => $document['hash'],
                'storage_key' => $storageKey,
                'approved_by' => $actorId,
                'approved_at' => $approvedAt,
                'reason_code' => $operation['reason_code'],
                'idempotency_key' => $operation['idempotency_key'],
            ]);

            if (($result['reused'] ?? false) !== true) {
                $declaration = (array) $result['declaration'];
                $this->appendMutationEvents(
                    $db,
                    $actor,
                    $role,
                    $operation,
                    'DECLARATION_APPROVED',
                    'SIF_DECLARATION',
                    (string) $declaration['UUID_DECLARATION'],
                    null,
                    [
                        'uuid_version' => $uuidVersion,
                        'declaration_version' => $declarationVersion,
                        'document_hash' => $document['hash'],
                        'storage_key' => $storageKey,
                        'approved_by' => $actorId,
                    ]
                );
            }

            return [
                'ok' => true,
                'reused' => (bool) ($result['reused'] ?? false),
                'declaration' => $this->declarationOutput((array) $result['declaration']),
            ];
        });
    }

    public function preflight(array $actor, string $uuidVersion, ?string $backupEvidenceUuid = null): array
    {
        $this->assertRead($actor);

        return [
            'ok' => true,
            'preflight' => $this->preflightOutput(
                $this->buildPreflight($uuidVersion, $backupEvidenceUuid)
            ),
        ];
    }

    public function activate(array $actor, string $uuidVersion, array $input): array
    {
        $role = $this->assertManage($actor);
        $actorId = $this->actorId($actor);
        $operation = $this->operationInput($input, 'ACTIVATE_VERSION');
        $backupUuid = $this->nullableUuid($input['backup_evidence_uuid'] ?? null);

        $existing = $this->activations->findByIdempotencyKey($this->db, $operation['idempotency_key']);
        if ($existing !== null) {
            $this->activations->assertReplay($existing, [
                'uuid_version' => $uuidVersion,
                'uuid_backup_evidence' => $backupUuid,
                'actor_id' => $actorId,
                'actor_role' => $role,
                'reason_code' => $operation['reason_code'],
                'correlation_id' => $operation['correlation_id'],
            ]);
            return ['ok' => true, 'reused' => true, 'activation' => $this->activationOutput($existing)];
        }

        $preflight = $this->buildPreflight($uuidVersion, $backupUuid);
        if (($preflight['ok'] ?? false) !== true) {
            throw SifException::conflict(
                'SIF version activation preflight failed: ' . implode(', ', (array) ($preflight['failed'] ?? []))
            );
        }

        return $this->transactions->run(function (\PDO $db) use (
            $actor,
            $role,
            $actorId,
            $operation,
            $uuidVersion,
            $backupUuid
        ): array {
            // Serialize all activation decisions before the authoritative replay
            // lookup. This makes concurrent requests with the same key converge
            // on the activation journal created by the first transaction.
            $state = $this->versions->lockState($db);

            $existing = $this->activations->findByIdempotencyKey($db, $operation['idempotency_key'], true);
            if ($existing !== null) {
                $this->activations->assertReplay($existing, [
                    'uuid_version' => $uuidVersion,
                    'uuid_backup_evidence' => $backupUuid,
                    'actor_id' => $actorId,
                    'actor_role' => $role,
                    'reason_code' => $operation['reason_code'],
                    'correlation_id' => $operation['correlation_id'],
                ]);
                return ['ok' => true, 'reused' => true, 'activation' => $this->activationOutput($existing)];
            }

            $candidate = $this->versions->findByUuid($db, $uuidVersion, true);
            if ($candidate === null) {
                throw SifException::notFound('SIF version candidate not found');
            }
            if (strtoupper((string) ($candidate['STATUS'] ?? '')) !== 'DRAFT') {
                throw SifException::conflict('Only a DRAFT SIF version can be activated');
            }

            $activeRows = $this->versions->activeRowsForUpdate($db);
            if (count($activeRows) > 1) {
                throw SifException::conflict('Multiple ACTIVE SIF versions detected');
            }

            $stateUuid = $state['ACTIVE_UUID_VERSION'] ?? null;
            $rowUuid = $activeRows === [] ? null : (string) $activeRows[0]['UUID_VERSION'];
            if ($stateUuid !== null && $stateUuid !== '' && $rowUuid !== (string) $stateUuid) {
                throw SifException::conflict('SIF version state pointer is inconsistent with ACTIVE rows');
            }

            $previousUuid = $stateUuid !== null && $stateUuid !== '' ? (string) $stateUuid : $rowUuid;

            // Re-read physical/runtime evidence while holding the version-state lock.
            // No deployment is performed here: activation only records an already-observed runtime.
            $preflight = $this->buildPreflight($uuidVersion, $backupUuid);
            if (($preflight['ok'] ?? false) !== true) {
                throw SifException::conflict(
                    'SIF version activation preflight changed: ' . implode(', ', (array) ($preflight['failed'] ?? []))
                );
            }

            $declaration = (array) ($preflight['declaration'] ?? []);
            $runtime = (array) ($preflight['runtime'] ?? []);

            $this->versions->activate($db, $uuidVersion);
            $activation = $this->activations->append($db, [
                'idempotency_key' => $operation['idempotency_key'],
                'uuid_version' => $uuidVersion,
                'previous_uuid_version' => $previousUuid,
                'uuid_declaration' => (string) $declaration['UUID_DECLARATION'],
                'uuid_backup_evidence' => $backupUuid,
                'actor_id' => $actorId,
                'actor_role' => $role,
                'reason_code' => $operation['reason_code'],
                'correlation_id' => $operation['correlation_id'],
                'environment' => (string) $runtime['environment'],
                'runtime_git_revision' => (string) $runtime['git_revision'],
                'runtime_artifact_hash' => (string) $runtime['artifact_hash'],
                'runtime_config_hash' => (string) $runtime['config_hash'],
                'runtime_database_version' => (string) $runtime['database_version'],
                'runtime_evidence' => [
                    'schema_verified' => $runtime['schema_verified'] ?? false,
                    'schema_checks' => $runtime['schema_checks'] ?? [],
                    'manifest' => $runtime['manifest'] ?? [],
                    'preflight_checks' => $preflight['checks'] ?? [],
                    'declaration' => [
                        'uuid_declaration' => $declaration['UUID_DECLARATION'] ?? null,
                        'declaration_version' => $declaration['DECLARATION_VERSION'] ?? null,
                        'document_hash' => $declaration['DOCUMENT_HASH'] ?? null,
                        'approved_by' => $declaration['APPROVED_BY'] ?? null,
                        'approved_at' => $declaration['APPROVED_AT'] ?? null,
                    ],
                    'backup' => $preflight['backup'] ?? null,
                ],
            ]);

            $this->appendMutationEvents(
                $db,
                $actor,
                $role,
                $operation,
                'VERSION_ACTIVATED',
                'SIF_VERSION',
                $uuidVersion,
                $previousUuid === null ? null : ['active_uuid_version' => $previousUuid],
                [
                    'active_uuid_version' => $uuidVersion,
                    'uuid_declaration' => $declaration['UUID_DECLARATION'],
                    'declaration_document_hash' => $declaration['DOCUMENT_HASH'] ?? null,
                    'uuid_backup_evidence' => $backupUuid,
                    'backup_evidence' => $preflight['backup'] ?? null,
                    'runtime_git_revision' => $runtime['git_revision'],
                    'runtime_artifact_hash' => $runtime['artifact_hash'],
                    'runtime_config_hash' => $runtime['config_hash'],
                    'runtime_database_version' => $runtime['database_version'],
                ]
            );

            return [
                'ok' => true,
                'reused' => (bool) ($activation['reused'] ?? false),
                'activation' => $this->activationOutput((array) $activation['activation']),
            ];
        });
    }

    private function buildPreflight(string $uuidVersion, ?string $backupUuid): array
    {
        $version = $this->versions->findByUuid($this->db, $uuidVersion);
        if ($version === null) {
            throw SifException::notFound('SIF version not found');
        }

        $runtime = $this->runtimeInspector->inspect($this->db, $this->config);
        $declaration = $this->declarations->findLatestApprovedByVersion($this->db, $uuidVersion);
        $declarationIntegrity = false;
        $declarationError = null;

        if (is_array($declaration)) {
            try {
                $document = $this->inspectDeclarationFile((string) $declaration['STORAGE_KEY']);
                $declarationIntegrity = hash_equals(
                    strtolower((string) $declaration['DOCUMENT_HASH']),
                    strtolower((string) $document['hash'])
                );
            } catch (\Throwable $exception) {
                $declarationError = $exception->getMessage();
            }
        }

        $state = $this->versions->state($this->db);
        $activeRows = $this->versions->activeRows($this->db);
        $stateUuid = is_array($state) ? trim((string) ($state['ACTIVE_UUID_VERSION'] ?? '')) : '';
        $activeUuid = count($activeRows) === 1
            ? trim((string) ($activeRows[0]['UUID_VERSION'] ?? ''))
            : '';
        $activeStateCoherent = is_array($state)
            && count($activeRows) <= 1
            && (
                (count($activeRows) === 0 && $stateUuid === '')
                || (count($activeRows) === 1 && $stateUuid !== '' && hash_equals($stateUuid, $activeUuid))
            );

        $governance = (array) ($this->config['version_governance'] ?? []);
        $activationEnabled = (bool) ($governance['activation_enabled'] ?? false);
        $backupRequired = (bool) ($governance['require_backup_evidence'] ?? true);
        $backup = null;
        $backupAcceptable = !$backupRequired;

        if ($backupUuid !== null) {
            $backup = $this->backupEvidence->findByUuid($this->db, $backupUuid);
            $backupAcceptable = is_array($backup)
                && $this->backupEvidence->isAcceptable($backup, (string) ($runtime['environment'] ?? ''));
        }

        $checks = [
            'activation_enabled' => $activationEnabled,
            'candidate_is_draft' => strtoupper((string) ($version['STATUS'] ?? '')) === 'DRAFT',
            'version_state_singleton_present' => is_array($state),
            'active_version_state_coherent' => $activeStateCoherent,
            'runtime_complete' => ($runtime['complete'] ?? false) === true,
            'git_revision_matches' => $this->same((string) ($version['GIT_REVISION'] ?? ''), $runtime['git_revision'] ?? null),
            'artifact_hash_matches' => $this->same((string) ($version['ARTIFACT_HASH'] ?? ''), $runtime['artifact_hash'] ?? null),
            'config_hash_matches' => $this->same((string) ($version['CONFIG_HASH'] ?? ''), $runtime['config_hash'] ?? null),
            'database_version_matches' => $this->same((string) ($version['DATABASE_VERSION'] ?? ''), $runtime['database_version'] ?? null),
            'schema_verified' => ($runtime['schema_verified'] ?? false) === true,
            'approved_declaration_present' => is_array($declaration),
            'approved_declaration_file_integrity' => $declarationIntegrity,
            'backup_evidence_acceptable' => $backupAcceptable,
        ];

        $failed = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));

        return [
            'ok' => $failed === [],
            'checks' => $checks,
            'failed' => $failed,
            'version' => $version,
            'runtime' => $runtime,
            'declaration' => $declaration,
            'declaration_error' => $declarationError,
            'backup_required' => $backupRequired,
            'backup' => $this->backupEvidenceSummary($backup),
        ];
    }

    private function preflightOutput(array $preflight): array
    {
        return [
            'ok' => (bool) ($preflight['ok'] ?? false),
            'checks' => (array) ($preflight['checks'] ?? []),
            'failed' => array_values((array) ($preflight['failed'] ?? [])),
            'version' => isset($preflight['version']) && is_array($preflight['version'])
                ? $this->versionOutput($preflight['version'])
                : null,
            'runtime' => (array) ($preflight['runtime'] ?? []),
            'declaration' => isset($preflight['declaration']) && is_array($preflight['declaration'])
                ? $this->declarationOutput($preflight['declaration'])
                : null,
            'declaration_error' => $preflight['declaration_error'] ?? null,
            'backup_required' => (bool) ($preflight['backup_required'] ?? false),
            'backup' => isset($preflight['backup']) && is_array($preflight['backup'])
                ? $this->backupEvidenceSummary($preflight['backup'])
                : null,
        ];
    }

    private function versionOutput(array $version): array
    {
        return array_intersect_key($version, array_fill_keys([
            'UUID_VERSION',
            'VERSION_CODE',
            'GIT_REVISION',
            'ARTIFACT_HASH',
            'CONFIG_HASH',
            'DATABASE_VERSION',
            'STATUS',
            'CREATED_BY',
            'CREATED_AT',
            'ACTIVATED_AT',
        ], true));
    }

    private function declarationOutput(array $declaration): array
    {
        return array_intersect_key($declaration, array_fill_keys([
            'UUID_DECLARATION',
            'UUID_VERSION',
            'DECLARATION_VERSION',
            'DOCUMENT_HASH',
            'STORAGE_KEY',
            'APPROVED_BY',
            'APPROVED_AT',
            'STATUS',
            'CREATED_AT',
        ], true));
    }

    private function activationOutput(array $activation): array
    {
        return array_intersect_key($activation, array_fill_keys([
            'UUID_ACTIVATION',
            'UUID_VERSION',
            'PREVIOUS_UUID_VERSION',
            'UUID_DECLARATION',
            'UUID_BACKUP_EVIDENCE',
            'ACTOR_ID',
            'ACTOR_ROLE',
            'REASON_CODE',
            'CORRELATION_ID',
            'ENVIRONMENT',
            'RUNTIME_GIT_REVISION',
            'RUNTIME_ARTIFACT_HASH',
            'RUNTIME_CONFIG_HASH',
            'RUNTIME_DATABASE_VERSION',
            'STATUS',
            'CREATED_AT',
        ], true));
    }

    private function backupEvidenceSummary(?array $backup): ?array
    {
        if ($backup === null) {
            return null;
        }

        $allowed = [
            'UUID_EVIDENCE',
            'OPERATION_TYPE',
            'ENVIRONMENT',
            'STATUS',
            'INTEGRITY_RESULT',
            'RPO_MINUTES',
            'RTO_MINUTES',
            'STARTED_AT',
            'FINISHED_AT',
        ];

        return array_intersect_key($backup, array_fill_keys($allowed, true));
    }

    private function inspectDeclarationFile(string $storageKey): array
    {
        $root = trim((string) (($this->config['version_governance']['declaration_root'] ?? '')));
        if ($root === '') {
            throw SifException::unavailable('SIF declaration storage is not configured');
        }

        $rootReal = realpath($root);
        if ($rootReal === false || !is_dir($rootReal)) {
            throw SifException::unavailable('SIF declaration storage is not available');
        }

        $releaseRoot = realpath(dirname(__DIR__, 2));
        if ($releaseRoot !== false
            && ($rootReal === $releaseRoot
                || str_starts_with($rootReal, $releaseRoot . DIRECTORY_SEPARATOR))
        ) {
            throw SifException::validation('SIF declaration storage must be outside the release tree');
        }

        $storageKey = str_replace('\\', '/', trim($storageKey));
        if ($storageKey === '' || str_starts_with($storageKey, '/') || str_contains('/' . $storageKey . '/', '/../')) {
            throw SifException::validation('Invalid declaration storage key');
        }

        $file = realpath($rootReal . '/' . $storageKey);
        if ($file === false || !str_starts_with($file, $rootReal . DIRECTORY_SEPARATOR) || !is_file($file)) {
            throw SifException::notFound('Declaration document not found in private storage');
        }

        return [
            'path' => $file,
            'hash' => hash_file('sha256', $file),
            'bytes' => filesize($file),
        ];
    }

    private function appendMutationEvents(
        \PDO $db,
        array $actor,
        string $role,
        array $operation,
        string $action,
        string $resourceType,
        string $resourceId,
        ?array $before,
        array $after
    ): void {
        $occurredAt = $this->now();
        $beforeHash = $this->snapshotHash($before);
        $afterHash = $this->snapshotHash($after);

        $this->auditEvents->append($db, [
            'request_id' => $operation['request_id'],
            'correlation_id' => $operation['correlation_id'],
            'action' => $action,
            'result' => 'SUCCESS',
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'source_environment' => (string) ($this->config['env'] ?? 'local'),
            'source_channel' => (string) ($actor['source_channel'] ?? 'SIF_PANEL'),
            'actor_type' => 'USER',
            'actor_id' => $this->actorId($actor),
            'actor_role' => $role,
            'reason_code' => $operation['reason_code'],
            'before_hash' => $beforeHash,
            'after_hash' => $afterHash,
            'changeset' => ['before' => $before, 'after' => $after],
            'occurred_at' => $occurredAt,
        ]);

        $this->operationalEvents->append($db, [
            'operation_type' => $action,
            'source_type' => $resourceType,
            'source_id' => $resourceId,
            'fiscal_impact' => 'NONE',
            'economic_impact' => 'NONE',
            'status' => 'RECORDED',
            'reason_code' => $operation['reason_code'],
            'before_snapshot' => $before,
            'after_snapshot' => $after,
            'actor_type' => 'USER',
            'actor_id' => $this->actorId($actor),
            'actor_role' => $role,
            'source_channel' => (string) ($actor['source_channel'] ?? 'SIF_PANEL'),
            'correlation_id' => $operation['correlation_id'],
            'occurred_at' => $occurredAt,
        ]);
    }

    private function operationInput(array $input, string $defaultReason): array
    {
        $correlation = trim((string) ($input['correlation_id'] ?? ''));
        $idempotency = trim((string) ($input['idempotency_key'] ?? ''));
        $requestId = trim((string) ($input['request_id'] ?? $correlation));
        $reason = strtoupper(trim((string) ($input['reason_code'] ?? $defaultReason)));

        if ($correlation === '' || strlen($correlation) > 120) {
            throw SifException::validation('Invalid SIF version correlation id');
        }
        if ($idempotency === '' || strlen($idempotency) > 140) {
            throw SifException::validation('Invalid SIF version idempotency key');
        }
        if ($requestId === '' || strlen($requestId) > 120) {
            throw SifException::validation('Invalid SIF version request id');
        }
        if (preg_match('/^[A-Z0-9_.-]{1,80}$/D', $reason) !== 1) {
            throw SifException::validation('Invalid SIF version reason code');
        }

        return [
            'correlation_id' => $correlation,
            'idempotency_key' => $idempotency,
            'request_id' => $requestId,
            'reason_code' => $reason,
        ];
    }

    private function assertRead(array $actor): string
    {
        $read = $this->roles('read_roles');
        $manage = $this->roles('manage_roles');
        return $this->effectiveRole($actor, array_values(array_unique(array_merge($read, $manage))), 'read');
    }

    private function assertManage(array $actor): string
    {
        return $this->effectiveRole($actor, $this->roles('manage_roles'), 'manage');
    }

    private function roles(string $key): array
    {
        $roles = [];
        foreach ((array) ($this->config['version_governance'][$key] ?? []) as $role) {
            $value = strtoupper(trim((string) $role));
            if ($value !== '') {
                $roles[$value] = true;
            }
        }
        $result = array_keys($roles);
        sort($result, SORT_STRING);
        return $result;
    }

    private function effectiveRole(array $actor, array $allowed, string $scope): string
    {
        if ($allowed === []) {
            throw SifException::forbidden('SIF version ' . $scope . ' roles are not configured');
        }

        $roles = [];
        foreach ((array) ($actor['roles'] ?? []) as $role) {
            $value = strtoupper(trim((string) $role));
            if ($value !== '') {
                $roles[$value] = true;
            }
        }
        $matches = array_values(array_intersect(array_keys($roles), $allowed));
        sort($matches, SORT_STRING);
        if ($matches === []) {
            throw SifException::forbidden('SIF version ' . $scope . ' permission denied');
        }

        return $matches[0];
    }

    private function actorId(array $actor): string
    {
        $actorId = trim((string) ($actor['actor_id'] ?? ''));
        if ($actorId === '' || strlen($actorId) > 120) {
            throw SifException::unauthorized('Invalid SIF version actor');
        }
        return $actorId;
    }

    private function nullableUuid(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $uuid = strtolower(trim((string) $value));
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $uuid) !== 1) {
            throw SifException::validation('Invalid backup evidence UUID');
        }
        return $uuid;
    }

    private function same(string $expected, mixed $actual): bool
    {
        $actual = $actual === null ? '' : (string) $actual;
        return $expected !== '' && $actual !== '' && hash_equals(strtolower($expected), strtolower($actual));
    }

    private function snapshotHash(?array $value): ?string
    {
        if ($value === null) {
            return null;
        }
        return hash(
            'sha256',
            json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))->format('Y-m-d H:i:s.u');
    }
}
