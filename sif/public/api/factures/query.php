<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Repository\InvoiceReadRepository;
use Prisma\Sif\Service\InternalApiAuthenticator;
use Prisma\Sif\Service\InternalInvoiceScopeResolver;
use Prisma\Sif\Service\InvoiceQueryGateway;
use Prisma\Sif\Service\InvoiceQueryService;
use Prisma\Sif\Service\ResolvedInvoiceVisibilityPolicy;

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

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
    $authenticator = new InternalApiAuthenticator(
        $db,
        new InternalApiRequestRepository(),
        (string) ($internalApi['key_id'] ?? ''),
        (string) ($internalApi['secret'] ?? ''),
        (int) ($internalApi['max_clock_skew_seconds'] ?? 300)
    );

    $actor = $authenticator->authenticate(
        $_SERVER,
        $rawBody,
        'POST',
        (string) ($internalApi['signed_path'] ?? '/api/factures/query.php')
    );

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

    $queryConfig = $config['invoice_query'] ?? [];
    $gateway = new InvoiceQueryGateway(
        new InternalInvoiceScopeResolver(
            (array) ($queryConfig['full_read_roles'] ?? []),
            (array) ($queryConfig['minimal_read_roles'] ?? [])
        ),
        new InvoiceQueryService(
            $db,
            new InvoiceReadRepository(),
            new ResolvedInvoiceVisibilityPolicy()
        )
    );

    $action = strtolower(trim((string) ($payload['action'] ?? '')));
    if ($action === 'view') {
        JsonResponse::send(
            $gateway->view($actor, (string) ($payload['uuid_factura'] ?? ''))
        );
        return;
    }

    if ($action === 'search') {
        $criteria = $payload['criteria'] ?? [];
        if (!is_array($criteria)) {
            throw SifException::validation('Invalid invoice search criteria');
        }

        $configuredMax = max(1, min(100, (int) ($queryConfig['max_results'] ?? 50)));
        $requestedLimit = (int) ($payload['limit'] ?? $configuredMax);
        $limit = max(1, min($configuredMax, $requestedLimit));

        JsonResponse::send(
            $gateway->search($actor, $criteria, $limit)
        );
        return;
    }

    throw SifException::validation('Unknown invoice query action');
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}
