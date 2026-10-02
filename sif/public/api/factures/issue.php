<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\FiscalSequenceRepository;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Repository\InvoiceRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Service\InternalApiAuthenticator;
use Prisma\Sif\Service\InternalInvoiceIssuePayloadPolicy;
use Prisma\Sif\Service\InternalInvoiceIssueScopeResolver;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\InvoiceService;
use Prisma\Sif\Service\PaymentPayloadValidator;

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
        (string) ($internalApi['invoice_issue_signed_path'] ?? '/api/factures/issue.php')
    );

    $issueConfig = $config['invoice_issue'] ?? [];
    $actor = (new InternalInvoiceIssueScopeResolver(
        (array) ($issueConfig['write_roles'] ?? [])
    ))->resolve($actor);

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

    $issuer = $config['issuer'] ?? [];
    $environment = strtoupper(trim((string) ($config['env'] ?? 'local')));
    $aeatConfig = $config['aeat'] ?? [];
    $payload = (new InternalInvoiceIssuePayloadPolicy(
        (string) ($issuer['nif'] ?? ''),
        (string) ($issuer['name'] ?? ''),
        in_array($environment, ['PROD', 'PRODUCTION', 'PREPROD', 'PREPRODUCTION'], true),
        [
            'system_name' => (string) ($aeatConfig['system_name'] ?? ''),
            'system_id' => (string) ($aeatConfig['system_id'] ?? ''),
            'system_version' => (string) ($aeatConfig['system_version'] ?? ''),
            'installation_id' => (string) ($aeatConfig['installation_id'] ?? ''),
        ]
    ))->prepare($payload, $actor);

    $service = new InvoiceService(
        new TransactionRunner($db),
        new InvoicePayloadValidator(),
        new FiscalSequenceRepository(),
        new InvoiceRepository(new UuidGenerator(), new HashCalculator()),
        new PaymentPayloadValidator(),
        new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
    );

    JsonResponse::send($service->issueInvoice($payload));
} catch (SifException $exception) {
    JsonResponse::fromThrowable($exception);
} catch (\Throwable $exception) {
    JsonResponse::send(['ok' => false, 'error' => 'Internal server error'], 500);
}
