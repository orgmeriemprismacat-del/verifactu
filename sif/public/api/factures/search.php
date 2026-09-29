<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\InternalRequestAuthenticator;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\InvoiceReadRepository;
use Prisma\Sif\Service\InternalInvoiceScopeResolver;
use Prisma\Sif\Service\InvoiceQueryService;
use Prisma\Sif\Service\ResolvedInvoiceVisibilityPolicy;

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        throw SifException::validation('Invoice search endpoint only accepts GET');
    }

    $config = require dirname(__DIR__, 3) . '/config/sif.php';
    $internal = $config['internal_api'] ?? [];

    $authenticator = new InternalRequestAuthenticator(
        (string) ($internal['client_id'] ?? ''),
        (string) ($internal['secret'] ?? ''),
        (int) ($internal['max_clock_skew_seconds'] ?? 300)
    );
    $actor = $authenticator->authenticate($_SERVER);

    $scopeResolver = new InternalInvoiceScopeResolver(
        (array) ($internal['invoice_full_read_roles'] ?? []),
        (array) ($internal['invoice_minimal_read_roles'] ?? [])
    );
    $actor = $scopeResolver->resolve($actor);

    $criteria = [];
    foreach ([
        'uuid_factura',
        'num_visible',
        'billing_nif',
        'billing_email',
        'factura_relacionada',
        'source_type',
        'source_id',
    ] as $key) {
        if (array_key_exists($key, $_GET) && $_GET[$key] !== '') {
            $criteria[$key] = $_GET[$key];
        }
    }

    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;

    $db = ConnectionFactory::make($config);
    $service = new InvoiceQueryService(
        $db,
        new InvoiceReadRepository(),
        new ResolvedInvoiceVisibilityPolicy()
    );

    JsonResponse::send($service->search($actor, $criteria, $limit));
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}
