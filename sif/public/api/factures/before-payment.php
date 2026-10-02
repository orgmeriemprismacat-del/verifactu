<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\FiscalSequenceRepository;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Repository\InvoiceBeforePaymentBillingPartyRepository;
use Prisma\Sif\Repository\InvoiceBeforePaymentCoverageRepository;
use Prisma\Sif\Repository\InvoiceBeforePaymentSelectionRepository;
use Prisma\Sif\Repository\InvoiceRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Service\InternalApiAuthenticator;
use Prisma\Sif\Service\InternalInvoiceBeforePaymentScopeResolver;
use Prisma\Sif\Service\InvoiceBeforePaymentCommandService;
use Prisma\Sif\Service\InvoiceBeforePaymentLegacyPreparationService;
use Prisma\Sif\Service\InvoiceBeforePaymentPayloadBuilder;
use Prisma\Sif\Service\InvoiceBeforePaymentServerPayloadAssembler;
use Prisma\Sif\Service\InvoiceBeforePaymentService;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\InvoiceService;
use Prisma\Sif\Service\PayloadIdempotencyValidator;

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
    $sifDb = ConnectionFactory::make($config);

    $internalApi = $config['internal_api'] ?? [];
    $actor = (new InternalApiAuthenticator(
        $sifDb,
        new InternalApiRequestRepository(),
        (string) ($internalApi['key_id'] ?? ''),
        (string) ($internalApi['secret'] ?? ''),
        (int) ($internalApi['max_clock_skew_seconds'] ?? 300)
    ))->authenticate(
        $_SERVER,
        $rawBody,
        'POST',
        (string) (
            $internalApi['invoice_before_payment_signed_path']
            ?? '/api/factures/before-payment.php'
        )
    );

    $writeConfig = $config['invoice_before_payment'] ?? [];
    $actor = (new InternalInvoiceBeforePaymentScopeResolver(
        (array) ($writeConfig['write_roles'] ?? [])
    ))->resolve($actor);

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

    $inscriptionIds = $payload['inscription_ids'] ?? null;
    if (!is_array($inscriptionIds) || $inscriptionIds === []) {
        throw SifException::validation('Invoice before payment requires inscription_ids');
    }

    $entityId = (int) ($payload['entity_id'] ?? 0);
    if ($entityId <= 0) {
        throw SifException::validation('Invalid invoice before payment entity_id');
    }

    $context = [];
    if (array_key_exists('observations', $payload)) {
        $observations = trim((string) $payload['observations']);
        if (mb_strlen($observations, 'UTF-8') > 500) {
            throw SifException::validation('Invoice before payment observations are too long');
        }
        if ($observations !== '') {
            $context['observations'] = $observations;
        }
    }

    $legacyWebDb = ConnectionFactory::makeLegacy($config);
    $legacyIntranetDb = ConnectionFactory::makeLegacyIntranet($config);
    $fingerprints = new PayloadIdempotencyValidator();

    $preparation = new InvoiceBeforePaymentLegacyPreparationService(
        new InvoiceBeforePaymentSelectionRepository(),
        new InvoiceBeforePaymentBillingPartyRepository(),
        new InvoiceBeforePaymentServerPayloadAssembler(),
        new InvoiceBeforePaymentPayloadBuilder(),
        $fingerprints
    );

    $invoiceService = new InvoiceService(
        new TransactionRunner($sifDb),
        new InvoicePayloadValidator(),
        new FiscalSequenceRepository(),
        new InvoiceRepository(new UuidGenerator(), new HashCalculator()),
        null,
        null,
        $fingerprints,
        new InvoiceBeforePaymentCoverageRepository(),
        new OperationalEventRepository(new UuidGenerator())
    );

    $commands = new InvoiceBeforePaymentCommandService(
        $legacyWebDb,
        $legacyIntranetDb,
        $preparation,
        new InvoiceBeforePaymentService(
            new InvoiceBeforePaymentPayloadBuilder(),
            $invoiceService
        )
    );

    $action = strtolower(trim((string) ($payload['action'] ?? '')));

    if ($action === 'preview') {
        $preview = $commands->preview(
            $inscriptionIds,
            $entityId,
            (string) $actor['actor_id'],
            $context
        );
        unset($preview['payload']);
        JsonResponse::send($preview);
        return;
    }

    if ($action === 'confirm') {
        JsonResponse::send(
            $commands->confirm(
                $inscriptionIds,
                $entityId,
                (string) $actor['actor_id'],
                (string) ($payload['expected_fingerprint'] ?? ''),
                $context
            )
        );
        return;
    }

    throw SifException::validation('Unknown invoice-before-payment action');
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}
