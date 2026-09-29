<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\{ConnectionFactory, TransactionRunner};
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\{AeatOperationsReadRepository, FiscalQueueRepository, IncidentRepository, InternalApiRequestRepository};
use Prisma\Sif\Service\{AeatPreflight, AeatReviewReconciliationService, InternalApiAuthenticator};

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
        (string) ($internalApi['aeat_operations_signed_path'] ?? '/api/aeat/operations.php')
    );

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

    $roles = array_map(
        static fn (mixed $role): string => strtoupper(trim((string) $role)),
        (array) ($actor['roles'] ?? [])
    );
    $readRoles = array_values(array_filter(array_map(
        static fn (mixed $role): string => strtoupper(trim((string) $role)),
        (array) ($config['aeat']['read_roles'] ?? [])
    )));
    $reconcileRoles = array_values(array_filter(array_map(
        static fn (mixed $role): string => strtoupper(trim((string) $role)),
        (array) ($config['aeat']['reconcile_roles'] ?? [])
    )));

    $action = strtolower(trim((string) ($payload['action'] ?? '')));
    $isReconcile = $action === 'reconcile';
    $requiredRoles = $isReconcile ? $reconcileRoles : $readRoles;
    if ($requiredRoles === [] || array_intersect($roles, $requiredRoles) === []) {
        throw SifException::forbidden(
            $isReconcile
                ? 'AEAT reconciliation role is not authorized'
                : 'AEAT operations role is not authorized'
        );
    }

    $repository = new AeatOperationsReadRepository();

    if ($action === 'summary') {
        JsonResponse::send(['ok' => true, 'data' => $repository->summary($db)]);
        return;
    }
    if ($action === 'list') {
        $status = isset($payload['status']) ? (string) $payload['status'] : null;
        $limit = (int) ($payload['limit'] ?? 50);
        JsonResponse::send(['ok' => true, 'data' => $repository->listQueue($db, $status, $limit)]);
        return;
    }
    if ($action === 'detail') {
        JsonResponse::send([
            'ok' => true,
            'data' => $repository->detail($db, (int) ($payload['queue_id'] ?? 0)),
        ]);
        return;
    }
    if ($action === 'preflight') {
        JsonResponse::send([
            'ok' => true,
            'data' => (new AeatPreflight())->check((array) ($config['aeat'] ?? [])),
        ]);
        return;
    }
    if ($action === 'reconcile') {
        $service = new AeatReviewReconciliationService(
            new TransactionRunner($db),
            new FiscalQueueRepository(),
            new IncidentRepository()
        );
        JsonResponse::send($service->reconcile(
            (int) ($payload['queue_id'] ?? 0),
            (string) ($payload['attempt_uuid'] ?? ''),
            (string) ($actor['actor_id'] ?? '')
        ));
        return;
    }

    throw SifException::validation('Unknown AEAT operations action');
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}
