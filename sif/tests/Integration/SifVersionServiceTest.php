<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\MigrationRunner;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\BackupRestoreEvidenceRepository;
use Prisma\Sif\Repository\SifAuditEventRepository;
use Prisma\Sif\Repository\SifDeclarationRepository;
use Prisma\Sif\Repository\SifVersionActivationRepository;
use Prisma\Sif\Repository\SifVersionRepository;
use Prisma\Sif\Service\RuntimeVersionInspector;
use Prisma\Sif\Service\SifVersionService;
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

            $activationInput = $this->operation('ACT-1', 'APPROVED_RELEASE');
            $activation = $service->activate($actor, $uuid, $activationInput);
            Assert::same(false, $activation['reused']);

            $view = $service->view($actor, $uuid);
            Assert::same('ACTIVE', $view['version']['STATUS']);
            Assert::same(1, count($view['activations']));

            $activationReplay = $service->activate($actor, $uuid, $activationInput);
            Assert::same(true, $activationReplay['reused']);

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
        } finally {
            $this->removeTree($dir);
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
        }
    }

    private function service(\PDO $db): array
    {
        $dir = sys_get_temp_dir() . '/sif-uc010-integration-' . bin2hex(random_bytes(8));
        mkdir($dir . '/src', 0700, true);
        mkdir($dir . '/declarations', 0700, true);

        file_put_contents($dir . '/src/runtime.php', '<?php echo "runtime";');
        file_put_contents($dir . '/declarations/declaracio-v1.pdf', '%PDF-UC010-test');

        $files = ['src/runtime.php' => hash_file('sha256', $dir . '/src/runtime.php')];
        $manifestPath = $dir . '/manifest.json';
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
            'require_backup_evidence' => false,
        ];

        $service = new SifVersionService(
            $db,
            new TransactionRunner($db),
            new SifVersionRepository(),
            new SifDeclarationRepository(),
            new SifVersionActivationRepository(),
            new BackupRestoreEvidenceRepository(),
            new SifAuditEventRepository(),
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
