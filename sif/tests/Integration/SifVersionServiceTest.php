<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\MigrationRunner;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\BackupRestoreEvidenceRepository;
use Prisma\Sif\Repository\SifAuditEventRepository;
use Prisma\Sif\Repository\SifDeclarationRepository;
use Prisma\Sif\Repository\SifVersionActivationRepository;
use Prisma\Sif\Repository\SifVersionRepository;
use Prisma\Sif\Service\RuntimeVersionInspector;
use Prisma\Sif\Service\SifVersionService;
use Prisma\Sif\Service\SifVersionEvidenceVerifier;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class SifVersionServiceTest
{
    public function testRegisterDeclarationPreflightActivationAndReplay(): void
    {
        $db = TestDatabase::fresh();
        [$service, $dir, $config] = $this->service($db);

        try {
            $actor = ['actor_id' => 'meriem', 'roles' => ['SIF_ADMIN'], 'source_channel' => 'TEST'];
            $registerInput = $this->operation('REGISTER-1', 'RELEASE_CANDIDATE') + [
                'version_code' => '2026.10.03-test1',
            ];

            $created = $service->registerCurrentRuntime($actor, $registerInput);
            Assert::same(false, $created['reused']);
            Assert::same('DRAFT', $created['version']['STATUS']);
            $uuid = $created['version']['UUID_VERSION'];

            $replayed = $service->registerCurrentRuntime($actor, $registerInput);
            Assert::same(true, $replayed['reused']);
            Assert::same($uuid, $replayed['version']['UUID_VERSION']);

            $declarationInput = $this->operation('DECL-1', 'DECLARATION_APPROVAL') + [
                'declaration_version' => 'v1',
                'storage_key' => 'declaracio-v1.pdf',
            ];
            $declaration = $service->attachDeclaration($actor, $uuid, $declarationInput);
            Assert::same(false, $declaration['reused']);
            Assert::same('APPROVED', $declaration['declaration']['STATUS']);

            $declarationReplay = $service->attachDeclaration($actor, $uuid, $declarationInput);
            Assert::same(true, $declarationReplay['reused']);

            $preflight = $service->preflight($actor, $uuid);
            Assert::same(true, $preflight['preflight']['ok']);
            foreach ([
                'uc010_single_active_unique_index',
                'uc010_version_status_check',
                'uc010_activation_status_check',
                'uc010_trigger_activation_no_update',
                'uc010_trigger_activation_no_delete',
            ] as $schemaCheck) {
                Assert::same(true, $preflight['preflight']['runtime']['schema_checks'][$schemaCheck] ?? false);
            }

            $activationInput = $this->operation('ACT-1', 'APPROVED_RELEASE');
            $activation = $service->activate($actor, $uuid, $activationInput);
            Assert::same(false, $activation['reused']);

            $view = $service->view($actor, $uuid);
            Assert::same('ACTIVE', $view['version']['STATUS']);
            Assert::same(1, count($view['activations']));
            $activationEvidence = json_decode(
                (string) $view['activations'][0]['RUNTIME_EVIDENCE_JSON'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            Assert::same(
                $declaration['declaration']['DOCUMENT_HASH'],
                $activationEvidence['declaration']['document_hash']
            );

            $activationReplay = $service->activate($actor, $uuid, $activationInput);
            Assert::same(true, $activationReplay['reused']);

            Assert::throws(
                SifException::class,
                fn () => $service->activate($actor, $uuid, $this->operation('ACT-NEW-KEY', 'APPROVED_RELEASE')),
                409
            );

            $declarationReplayAfterActivation = $service->attachDeclaration(
                $actor,
                $uuid,
                $declarationInput
            );
            Assert::same(true, $declarationReplayAfterActivation['reused']);

            Assert::throws(
                SifException::class,
                fn () => $service->attachDeclaration(
                    $actor,
                    $uuid,
                    $this->operation('DECL-AFTER-ACTIVE', 'DECLARATION_APPROVAL') + [
                        'declaration_version' => 'v2',
                        'storage_key' => 'declaracio-v1.pdf',
                    ]
                ),
                409
            );

            $state = $db->query('SELECT * FROM sif_version_state WHERE ID = 1')->fetch(\PDO::FETCH_ASSOC);
            Assert::same($uuid, $state['ACTIVE_UUID_VERSION']);
            Assert::same('1', (string) $state['LOCK_VERSION']);

            Assert::same(3, (int) $db->query(
                "SELECT COUNT(*) FROM sif_audit_event
                 WHERE ACTION IN ('VERSION_REGISTERED','DECLARATION_APPROVED','VERSION_ACTIVATED')"
            )->fetchColumn());
            Assert::same(3, (int) $db->query(
                "SELECT COUNT(*) FROM operational_event
                 WHERE OPERATION_TYPE IN ('VERSION_REGISTERED','DECLARATION_APPROVED','VERSION_ACTIVATED')"
            )->fetchColumn());

            $evidence = (new SifVersionEvidenceVerifier(
                new RuntimeVersionInspector(
                    $dir,
                    new MigrationRunner(dirname(__DIR__, 2) . '/database')
                ),
                $config
            ))->verify($db, $uuid);
            Assert::same(true, $evidence['ok']);
            Assert::same(false, $evidence['production_authorized']);
            Assert::same(true, $evidence['checks']['singleton_points_to_version']);
            Assert::same(true, $evidence['checks']['activation_snapshot_declaration_hash_matches']);
        } finally {
            $this->removeTree($dir);
            $this->removeTree($dir . '-evidence');
        }
    }

    public function testDatabaseGuardsSingleActiveAndImmutableActivationJournal(): void
    {
        $db = TestDatabase::fresh();
        [$service, $dir] = $this->service($db);

        try {
            $actor = ['actor_id' => 'meriem', 'roles' => ['SIF_ADMIN'], 'source_channel' => 'TEST'];

            $first = $service->registerCurrentRuntime(
                $actor,
                $this->operation('REGISTER-DB-GUARD-1', 'RELEASE_CANDIDATE') + [
                    'version_code' => '2026.10.04-db-guard-1',
                ]
            );
            $firstUuid = $first['version']['UUID_VERSION'];

            $declaration = $service->attachDeclaration(
                $actor,
                $firstUuid,
                $this->operation('DECL-DB-GUARD-1', 'DECLARATION_APPROVAL') + [
                    'declaration_version' => 'v1',
                    'storage_key' => 'declaracio-v1.pdf',
                ]
            );
            $service->activate(
                $actor,
                $firstUuid,
                $this->operation('ACT-DB-GUARD-1', 'APPROVED_RELEASE')
            );

            $second = $service->registerCurrentRuntime(
                $actor,
                $this->operation('REGISTER-DB-GUARD-2', 'RELEASE_CANDIDATE') + [
                    'version_code' => '2026.10.04-db-guard-2',
                ]
            );
            $secondUuid = $second['version']['UUID_VERSION'];

            Assert::throws(
                \PDOException::class,
                fn () => $db->prepare(
                    "UPDATE sif_version SET STATUS = 'ACTIVE', ACTIVATED_AT = NOW(6) WHERE UUID_VERSION = ?"
                )->execute([$secondUuid])
            );

            $activationUuid = (string) $db->query(
                "SELECT UUID_ACTIVATION FROM sif_version_activation ORDER BY ID DESC LIMIT 1"
            )->fetchColumn();

            Assert::throws(
                \PDOException::class,
                fn () => $db->prepare(
                    "UPDATE sif_version_activation SET STATUS = 'CORRUPTED' WHERE UUID_ACTIVATION = ?"
                )->execute([$activationUuid])
            );

            Assert::throws(
                \PDOException::class,
                fn () => $db->prepare(
                    "DELETE FROM sif_version_activation WHERE UUID_ACTIVATION = ?"
                )->execute([$activationUuid])
            );

            Assert::same('ACTIVE', (string) $db->query(
                "SELECT STATUS FROM sif_version WHERE UUID_VERSION = " . $db->quote($firstUuid)
            )->fetchColumn());
            Assert::same('DRAFT', (string) $db->query(
                "SELECT STATUS FROM sif_version WHERE UUID_VERSION = " . $db->quote($secondUuid)
            )->fetchColumn());
            Assert::same(
                $declaration['declaration']['UUID_DECLARATION'],
                (string) $db->query(
                    "SELECT UUID_DECLARATION FROM sif_version_activation WHERE UUID_ACTIVATION = " . $db->quote($activationUuid)
                )->fetchColumn()
            );
        } finally {
            $this->removeTree($dir);
            $this->removeTree($dir . '-evidence');
        }
    }

    public function testActivationFailsClosedWhenDeployedBytesDrift(): void
    {
        $db = TestDatabase::fresh();
        [$service, $dir] = $this->service($db);

        try {
            $actor = ['actor_id' => 'meriem', 'roles' => ['SIF_ADMIN'], 'source_channel' => 'TEST'];
            $created = $service->registerCurrentRuntime(
                $actor,
                $this->operation('REGISTER-DRIFT', 'RELEASE_CANDIDATE') + ['version_code' => '2026.10.03-drift']
            );
            $uuid = $created['version']['UUID_VERSION'];

            $service->attachDeclaration(
                $actor,
                $uuid,
                $this->operation('DECL-DRIFT', 'DECLARATION_APPROVAL') + [
                    'declaration_version' => 'v1',
                    'storage_key' => 'declaracio-v1.pdf',
                ]
            );

            file_put_contents($dir . '/src/runtime.php', '<?php echo "modified";');

            $registerReplayAfterDrift = $service->registerCurrentRuntime(
                $actor,
                $this->operation('REGISTER-DRIFT', 'RELEASE_CANDIDATE') + ['version_code' => '2026.10.03-drift']
            );
            Assert::same(true, $registerReplayAfterDrift['reused']);
            Assert::same($uuid, $registerReplayAfterDrift['version']['UUID_VERSION']);

            $preflight = $service->preflight($actor, $uuid);
            Assert::same(false, $preflight['preflight']['ok']);
            Assert::same(true, in_array('runtime_complete', $preflight['preflight']['failed'], true));

            Assert::throws(
                SifException::class,
                fn () => $service->activate($actor, $uuid, $this->operation('ACT-DRIFT', 'APPROVED_RELEASE')),
                409
            );

            Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM sif_version_activation')->fetchColumn());
        } finally {
            $this->removeTree($dir);
            $this->removeTree($dir . '-evidence');
        }
    }

    public function testPreflightReturnsOnlyMinimalBackupEvidenceProjection(): void
    {
        $db = TestDatabase::fresh();
        [$service, $dir, $config] = $this->service($db, true);

        try {
            $actor = ['actor_id' => 'meriem', 'roles' => ['SIF_ADMIN'], 'source_channel' => 'TEST'];
            $created = $service->registerCurrentRuntime(
                $actor,
                $this->operation('REGISTER-BACKUP', 'RELEASE_CANDIDATE') + ['version_code' => '2026.10.04-backup']
            );
            $uuid = $created['version']['UUID_VERSION'];

            $service->attachDeclaration(
                $actor,
                $uuid,
                $this->operation('DECL-BACKUP', 'DECLARATION_APPROVAL') + [
                    'declaration_version' => 'v1',
                    'storage_key' => 'declaracio-v1.pdf',
                ]
            );

            $backupUuid = '11111111-2222-4333-8444-555555555555';
            $db->prepare(
                'INSERT INTO backup_restore_evidence (
                    UUID_EVIDENCE, OPERATION_TYPE, ENVIRONMENT, SCOPE_JSON,
                    BACKUP_REFERENCE, BACKUP_HASH, STATUS, RPO_MINUTES, RTO_MINUTES,
                    INTEGRITY_RESULT, EXECUTED_BY, CORRELATION_ID, STARTED_AT,
                    FINISHED_AT, EVIDENCE_JSON
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(6), NOW(6), ?)'
            )->execute([
                $backupUuid,
                'BACKUP',
                'test',
                '{"scope":"sif"}',
                '/private/backups/sif.sql',
                str_repeat('b', 64),
                'SUCCESS',
                10,
                20,
                'OK',
                'operator',
                'CORR-BACKUP',
                '{"private_detail":"must-not-leak"}',
            ]);

            $preflight = $service->preflight($actor, $uuid, $backupUuid);
            Assert::same(true, $preflight['preflight']['ok']);
            Assert::same($backupUuid, $preflight['preflight']['backup']['UUID_EVIDENCE']);
            Assert::same(false, array_key_exists('EVIDENCE_JSON', $preflight['preflight']['backup']));
            Assert::same(false, array_key_exists('BACKUP_REFERENCE', $preflight['preflight']['backup']));
            Assert::same(false, array_key_exists('EXECUTED_BY', $preflight['preflight']['backup']));

            $activation = $service->activate(
                $actor,
                $uuid,
                $this->operation('ACT-BACKUP', 'APPROVED_RELEASE') + [
                    'backup_evidence_uuid' => $backupUuid,
                ]
            );
            $activationEvidence = json_decode(
                (string) $activation['activation']['RUNTIME_EVIDENCE_JSON'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            Assert::same($backupUuid, $activationEvidence['backup']['UUID_EVIDENCE']);
            Assert::same(false, array_key_exists('EVIDENCE_JSON', $activationEvidence['backup']));
            Assert::same(false, array_key_exists('BACKUP_REFERENCE', $activationEvidence['backup']));

            $evidence = (new SifVersionEvidenceVerifier(
                new RuntimeVersionInspector(
                    $dir,
                    new MigrationRunner(dirname(__DIR__, 2) . '/database')
                ),
                $config
            ))->verify($db, $uuid);
            Assert::same(true, $evidence['ok']);
            Assert::same(true, $evidence['backup_required']);
            Assert::same(true, $evidence['checks']['backup_evidence_required']);
            Assert::same(true, $evidence['checks']['backup_evidence_acceptable']);
        } finally {
            $this->removeTree($dir);
            $this->removeTree($dir . '-evidence');
        }
    }

    public function testManagePermissionAndDeclarationTraversalFailClosed(): void
    {
        $db = TestDatabase::fresh();
        [$service, $dir] = $this->service($db);

        try {
            $reader = ['actor_id' => 'auditor', 'roles' => ['SIF_AUDITOR'], 'source_channel' => 'TEST'];
            $admin = ['actor_id' => 'meriem', 'roles' => ['SIF_ADMIN'], 'source_channel' => 'TEST'];

            $runtime = $service->runtime($reader);
            Assert::same(true, $runtime['ok']);

            Assert::throws(
                SifException::class,
                fn () => $service->registerCurrentRuntime(
                    $reader,
                    $this->operation('READER-WRITE', 'RELEASE_CANDIDATE') + ['version_code' => 'forbidden']
                ),
                403
            );

            $created = $service->registerCurrentRuntime(
                $admin,
                $this->operation('ADMIN-REGISTER', 'RELEASE_CANDIDATE') + ['version_code' => '2026.10.03-path']
            );

            Assert::throws(
                SifException::class,
                fn () => $service->attachDeclaration(
                    $admin,
                    $created['version']['UUID_VERSION'],
                    $this->operation('BAD-PATH', 'DECLARATION_APPROVAL') + [
                        'declaration_version' => 'v1',
                        'storage_key' => '../outside.pdf',
                    ]
                ),
                422
            );
        } finally {
            $this->removeTree($dir);
            $this->removeTree($dir . '-evidence');
        }
    }

    private function service(\PDO $db, bool $requireBackupEvidence = false): array
    {
        $dir = sys_get_temp_dir() . '/sif-uc010-integration-' . bin2hex(random_bytes(8));
        $evidenceDir = $dir . '-evidence';
        mkdir($dir . '/src', 0700, true);
        mkdir($dir . '/declarations', 0700, true);
        mkdir($evidenceDir, 0700, true);

        file_put_contents($dir . '/src/runtime.php', '<?php echo "runtime";');
        file_put_contents($dir . '/declarations/declaracio-v1.pdf', '%PDF-UC010-test');

        $files = ['src/runtime.php' => hash_file('sha256', $dir . '/src/runtime.php')];
        $manifestPath = $evidenceDir . '/manifest.json';
        file_put_contents($manifestPath, json_encode(['schema' => 1, 'files' => $files], JSON_THROW_ON_ERROR));

        $config = require dirname(__DIR__, 2) . '/config/sif.php';
        $config['env'] = 'test';
        $config['version_governance'] = [
            'read_roles' => ['SIF_AUDITOR'],
            'manage_roles' => ['SIF_ADMIN'],
            'runtime_git_revision' => str_repeat('a', 40),
            'release_manifest_path' => $manifestPath,
            'declaration_root' => $dir . '/declarations',
            'activation_enabled' => true,
            'require_backup_evidence' => $requireBackupEvidence,
        ];

        $service = new SifVersionService(
            $db,
            new TransactionRunner($db),
            new SifVersionRepository(),
            new SifDeclarationRepository(),
            new SifVersionActivationRepository(),
            new BackupRestoreEvidenceRepository(),
            new SifAuditEventRepository(new UuidGenerator()),
            new RuntimeVersionInspector(
                $dir,
                new MigrationRunner(dirname(__DIR__, 2) . '/database')
            ),
            $config
        );

        return [$service, $dir, $config];
    }

    private function operation(string $id, string $reason): array
    {
        return [
            'request_id' => 'REQ-' . $id,
            'correlation_id' => 'CORR-' . $id,
            'idempotency_key' => 'IDEMP-' . $id,
            'reason_code' => $reason,
        ];
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
