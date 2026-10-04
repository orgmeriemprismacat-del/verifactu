<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\FiscalCorrectionDecisionRepository;
use Prisma\Sif\Repository\FiscalSequenceRepository;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Repository\InvoiceRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Repository\RectificationRepository;
use Prisma\Sif\Repository\SifAuditEventRepository;
use Prisma\Sif\Service\FiscalCorrectionDecisionGuard;
use Prisma\Sif\Service\FiscalCorrectionDecisionResolver;
use Prisma\Sif\Service\InternalApiAuthenticator;
use Prisma\Sif\Service\InternalRectificationScopeResolver;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\InvoiceService;
use Prisma\Sif\Service\ManualRectificationPayloadBuilder;
use Prisma\Sif\Service\ManualRectificationService;
use Prisma\Sif\Service\PayloadIdempotencyValidator;
use Prisma\Sif\Service\RectificationCommandService;

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
    $rectificationConfig = $config['rectification'] ?? [];

    if (($rectificationConfig['enabled'] ?? false) !== true) {
        throw SifException::forbidden('UC-005 rectification execution is disabled');
    }

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
        (string) (
            $internalApi['rectification_signed_path']
            ?? '/api/factures/rectify.php'
        )
    );

    $actor = (new InternalRectificationScopeResolver(
        (array) ($rectificationConfig['write_roles'] ?? [])
    ))->resolve($actor);

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

    $uuidFactura = trim((string) ($payload['uuid_factura'] ?? ''));
    $correction = $payload['correction'] ?? null;
    $classificationEventUuid = trim((string) (
        $payload['classification_event_uuid'] ?? ''
    ));

    if (!is_array($correction)) {
        throw SifException::validation('Rectification correction block is required');
    }
    if ($classificationEventUuid === '') {
        throw SifException::validation('Rectification UC-74 classification event UUID is required');
    }

    $fingerprints = new PayloadIdempotencyValidator();
    $invoiceService = new InvoiceService(
        new TransactionRunner($db),
        new InvoicePayloadValidator(),
        new FiscalSequenceRepository(),
        new InvoiceRepository(new UuidGenerator(), new HashCalculator()),
        null,
        null,
        $fingerprints
    );

    $manualRectification = new ManualRectificationService(
        new ManualPaymentInvoiceRepository(),
        new RectificationRepository(),
        new ManualRectificationPayloadBuilder(),
        $invoiceService
    );

    $classification = (new FiscalCorrectionDecisionResolver(
        new FiscalCorrectionDecisionRepository(),
        new FiscalCorrectionDecisionGuard()
    ))->resolve(
        $db,
        $classificationEventUuid,
        $uuidFactura,
        $correction
    );

    $commands = new RectificationCommandService(
        $db,
        new ManualPaymentInvoiceRepository(),
        new ManualRectificationPayloadBuilder(),
        $manualRectification,
        new FiscalCorrectionDecisionGuard(),
        $fingerprints,
        new SifAuditEventRepository(new UuidGenerator()),
        new OperationalEventRepository(new UuidGenerator()),
        (string) ($config['env'] ?? 'unknown')
    );

    $context = [
        'correlation_id' => $payload['correlation_id'] ?? ($actor['request_id'] ?? null),
        'causation_id' => $payload['causation_id'] ?? null,
    ];

    $action = strtolower(trim((string) ($payload['action'] ?? '')));

    if ($action === 'preview') {
        JsonResponse::send(
            $commands->preview(
                $actor,
                $uuidFactura,
                $correction,
                $classification,
                $context
            )
        );
        return;
    }

    if ($action === 'confirm') {
        JsonResponse::send(
            $commands->confirm(
                $actor,
                $uuidFactura,
                $correction,
                $classification,
                (string) ($payload['expected_fingerprint'] ?? ''),
                $context
            )
        );
        return;
    }

    throw SifException::validation('Unknown rectification action');
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}
