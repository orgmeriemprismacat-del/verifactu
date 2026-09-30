<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\IncidentPanelSession;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\IncidentActionRepository;
use Prisma\Sif\Repository\IncidentRepository;
use Prisma\Sif\Service\IncidentLifecycleService;

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    JsonResponse::send(['ok' => false, 'error' => 'Method not allowed'], 405);
    return;
}

try {
    $config = require dirname(__DIR__, 3) . '/config/sif.php';
    $panelConfig = $config['panel'] ?? [];
    $session = new IncidentPanelSession((string) ($panelConfig['session_name'] ?? 'SIFPANELSESSID'));
    $session->start();
    $actor = $session->actor();
    $session->assertCsrf((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody === false ? '' : $rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

    if (strtolower(trim((string) ($payload['action'] ?? ''))) === 'logout') {
        $session->destroy();
        JsonResponse::send(['ok' => true]);
        return;
    }

    $db = ConnectionFactory::make($config);
    $incidentConfig = $config['incidents'] ?? [];
    $service = new IncidentLifecycleService(
        $db,
        new TransactionRunner($db),
        new IncidentRepository(),
        new IncidentActionRepository(),
        (array) ($incidentConfig['read_roles'] ?? []),
        (array) ($incidentConfig['manage_roles'] ?? [])
    );

    $action = strtolower(trim((string) ($payload['action'] ?? '')));
    if ($action === 'summary') {
        JsonResponse::send($service->summary($actor));
        return;
    }
    if ($action === 'list') {
        $filters = $payload['filters'] ?? [];
        if (!is_array($filters)) {
            throw SifException::validation('Invalid incident filters');
        }
        $configuredMax = max(1, min(200, (int) ($incidentConfig['max_results'] ?? 100)));
        $requested = (int) ($payload['limit'] ?? $configuredMax);
        JsonResponse::send($service->list($actor, $filters, max(1, min($configuredMax, $requested))));
        return;
    }
    if ($action === 'view') {
        JsonResponse::send($service->view($actor, (int) ($payload['incident_id'] ?? 0)));
        return;
    }

    $incidentId = (int) ($payload['incident_id'] ?? 0);
    if ($incidentId < 1) {
        throw SifException::validation('Invalid incident id');
    }

    if ($action === 'assign') {
        JsonResponse::send($service->assign($actor, $incidentId, $payload));
        return;
    }
    if ($action === 'evidence') {
        JsonResponse::send($service->addEvidence($actor, $incidentId, $payload));
        return;
    }
    if ($action === 'resolve') {
        JsonResponse::send($service->resolve($actor, $incidentId, $payload));
        return;
    }
    if ($action === 'dismiss') {
        JsonResponse::send($service->dismiss($actor, $incidentId, $payload));
        return;
    }
    if ($action === 'reopen') {
        JsonResponse::send($service->reopen($actor, $incidentId, $payload));
        return;
    }

    throw SifException::validation('Unknown incident panel action');
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}
