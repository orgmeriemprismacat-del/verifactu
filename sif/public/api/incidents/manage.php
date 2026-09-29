<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\IncidentActionRepository;
use Prisma\Sif\Repository\IncidentRepository;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Service\IncidentLifecycleService;
use Prisma\Sif\Service\InternalApiAuthenticator;

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    JsonResponse::send(['ok' => false, 'error' => 'Method not allowed'], 405);
    return;
}

$rawBody = file_get_contents('php://input');
if ($rawBody === false) {
    JsonResponse::send(['ok' => false, 'error' => 'Could not read request body'], 400);
    return;
}

try {
    $config = require dirname(__DIR__, 3) . '/config/sif.php';
    $db = ConnectionFactory::make($config);

    $internalApi = $config['internal_api'] ?? [];
    $actor = (new InternalApiAuthenticator(
        $db,
        new InternalApiRequestRepository(),
        (string) ($internalApi['key_id'] ?? ''),
        (string) ($internalApi['secret'] ?? ''),
        (int) ($internalApi['max_clock_skew_seconds'] ?? 300)
    ))->authenticate(
        $_SERVER,
        $rawBody,
        'POST',
        (string) ($internalApi['incident_signed_path'] ?? '/api/incidents/manage.php')
    );

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

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

    if ($action === 'list') {
        $filters = $payload['filters'] ?? [];
        if (!is_array($filters)) {
            throw SifException::validation('Invalid incident filters');
        }
        $configuredMax = max(1, min(100, (int) ($incidentConfig['max_results'] ?? 50)));
        $requested = (int) ($payload['limit'] ?? $configuredMax);
        JsonResponse::send($service->list($actor, $filters, max(1, min($configuredMax, $requested))));
        return;
    }

    if ($action === 'view') {
        JsonResponse::send($service->view($actor, (int) ($payload['incident_id'] ?? 0)));
        return;
    }

    if ($action === 'open') {
        JsonResponse::send($service->open($actor, $payload));
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

    throw SifException::validation('Unknown incident action');
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}
