<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\MigrationRunner;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Http\VersionPanelSession;
use Prisma\Sif\Repository\BackupRestoreEvidenceRepository;
use Prisma\Sif\Repository\SifAuditEventRepository;
use Prisma\Sif\Repository\SifDeclarationRepository;
use Prisma\Sif\Repository\SifVersionActivationRepository;
use Prisma\Sif\Repository\SifVersionRepository;
use Prisma\Sif\Service\RuntimeVersionInspector;
use Prisma\Sif\Service\SifVersionService;

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    JsonResponse::send(['ok' => false, 'error' => 'Method not allowed'], 405);
    return;
}

try {
    $baseDir = dirname(__DIR__, 3);
    $config = require $baseDir . '/config/sif.php';
    $session = new VersionPanelSession(
        (string) ($config['panel']['session_name'] ?? 'SIFPANELSESSID'),
        (int) ($config['panel']['version_session_ttl_seconds'] ?? 1800)
    );
    $session->start();
    $actor = $session->actor();
    $session->assertCsrf((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

    $payload = JsonResponse::fromInput();
    if ($payload === null) {
        return;
    }

    $action = strtolower(trim((string) ($payload['action'] ?? '')));
    if ($action === 'logout') {
        $session->destroy();
        JsonResponse::send(['ok' => true]);
        return;
    }

    $db = ConnectionFactory::make($config);
    $service = new SifVersionService(
        $db,
        new TransactionRunner($db),
        new SifVersionRepository(),
        new SifDeclarationRepository(),
        new SifVersionActivationRepository(),
        new BackupRestoreEvidenceRepository(),
        new SifAuditEventRepository(new UuidGenerator()),
        new RuntimeVersionInspector($baseDir, new MigrationRunner($baseDir . '/database')),
        $config
    );

    if ($action === 'runtime') {
        JsonResponse::send($service->runtime($actor));
        return;
    }
    if ($action === 'list') {
        JsonResponse::send($service->list($actor, (int) ($payload['limit'] ?? 50)));
        return;
    }
    if ($action === 'register_current') {
        JsonResponse::send($service->registerCurrentRuntime($actor, $payload));
        return;
    }

    $uuidVersion = strtolower(trim((string) ($payload['uuid_version'] ?? '')));
    if ($uuidVersion === '') {
        throw SifException::validation('SIF version UUID is required');
    }

    if ($action === 'view') {
        JsonResponse::send($service->view($actor, $uuidVersion));
        return;
    }
    if ($action === 'attach_declaration') {
        JsonResponse::send($service->attachDeclaration($actor, $uuidVersion, $payload));
        return;
    }
    if ($action === 'preflight') {
        $backup = trim((string) ($payload['backup_evidence_uuid'] ?? ''));
        JsonResponse::send($service->preflight($actor, $uuidVersion, $backup === '' ? null : $backup));
        return;
    }
    if ($action === 'activate') {
        JsonResponse::send($service->activate($actor, $uuidVersion, $payload));
        return;
    }

    throw SifException::validation('Unknown SIF version action');
} catch (\Throwable $exception) {
    if ($exception instanceof SifException) {
        JsonResponse::fromThrowable($exception);
        return;
    }

    error_log('[UC-010] Internal version governance error: ' . $exception->getMessage());
    JsonResponse::send(['ok' => false, 'error' => 'Internal SIF version governance error'], 500);
}
