<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\DocumentAccessRepository;
use Prisma\Sif\Repository\FiscalDocumentAccessRepository;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Repository\InvoiceReadRepository;
use Prisma\Sif\Service\InternalApiAuthenticator;
use Prisma\Sif\Service\InternalInvoiceScopeResolver;
use Prisma\Sif\Service\InvoiceDocumentAccessService;
use Prisma\Sif\Service\PrivateDocumentStore;
use Prisma\Sif\Service\ResolvedDocumentAuthorizationPolicy;

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
        (string) ($internalApi['document_signed_path'] ?? '/api/documents/download.php')
    );

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

    $documentId = (int) ($payload['document_id'] ?? 0);
    if ($documentId <= 0) {
        throw SifException::validation('Invalid document id');
    }

    $queryConfig = $config['invoice_query'] ?? [];
    $actor = (new InternalInvoiceScopeResolver(
        (array) ($queryConfig['full_read_roles'] ?? []),
        (array) ($queryConfig['minimal_read_roles'] ?? [])
    ))->resolve($actor);

    $documentsConfig = $config['documents'] ?? [];
    $service = new InvoiceDocumentAccessService(
        $db,
        new DocumentAccessRepository(),
        new InvoiceReadRepository(),
        new ResolvedDocumentAuthorizationPolicy(),
        new PrivateDocumentStore(
            (string) ($documentsConfig['root'] ?? ''),
            (int) ($documentsConfig['max_bytes'] ?? 20971520)
        ),
        new FiscalDocumentAccessRepository(new UuidGenerator())
    );

    $result = $service->download($actor, $documentId);
    $document = $result['document'];
    $bytes = $result['bytes'];

    $type = strtoupper((string) ($document['type'] ?? ''));
    $mime = match ($type) {
        'PDF' => 'application/pdf',
        'XML' => 'application/xml; charset=utf-8',
        'QR' => 'image/png',
        default => 'application/octet-stream',
    };

    $extension = match ($type) {
        'PDF' => 'pdf',
        'XML' => 'xml',
        'QR' => 'png',
        default => strtolower($type !== '' ? $type : 'bin'),
    };

    $number = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($document['num_visible'] ?? 'factura'));
    $filename = trim($number, '-') . '.' . $extension;

    http_response_code(200);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-SIF-Document-Id: ' . (int) $document['id']);
    header('X-SIF-Document-Type: ' . $type);
    header('X-SIF-Invoice-UUID: ' . (string) $document['uuid_factura']);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $bytes;
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}
