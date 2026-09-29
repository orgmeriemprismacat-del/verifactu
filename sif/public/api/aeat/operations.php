<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\{AeatOperationsReadRepository, InternalApiRequestRepository};
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
        (string) ($internalApi['aeat_operations_signed_path'] ?? '/api/aeat/operations.php')
    );

    $allowed = array_values(array_filter(array_map(
        static fn (string $role): string => strtoupper(trim($role)),
        (array) (($config['aeat']['read_roles'] ?? []))
    )));
    if (array_intersect((array) ($actor['roles'] ?? []), $allowed) === []) {
        throw SifException::forbidden('AEAT operations role is not authorized');
    }

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

    $repository = new AeatOperationsReadRepository();
    $action = strtolower(trim((string) ($payload['action'] ?? '')));

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

    throw SifException::validation('Unknown AEAT operations action');
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}
