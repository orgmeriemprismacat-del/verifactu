<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\PaymentActionEventRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Service\InternalApiAuthenticator;
use Prisma\Sif\Service\ManualPaymentPayloadBuilder;
use Prisma\Sif\Service\ManualPaymentService;
use Prisma\Sif\Service\ManualTransferCommandService;
use Prisma\Sif\Service\PaymentActionGateway;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Service\PaymentService;

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
        (string) ($internalApi['manual_transfer_signed_path'] ?? '/api/payments/manual-transfer.php')
    );

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

    $paymentService = new PaymentService(
        new TransactionRunner($db),
        new PaymentPayloadValidator(),
        new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
    );

    $manualService = new ManualPaymentService(
        new ManualPaymentInvoiceRepository(),
        new ManualPaymentPayloadBuilder(),
        $paymentService
    );

    $auditGateway = new PaymentActionGateway(
        $db,
        new TransactionRunner($db),
        new PaymentActionEventRepository(new UuidGenerator())
    );

    $service = new ManualTransferCommandService(
        $manualService,
        (array) (($config['payments']['manual_transfer_roles'] ?? [])),
        $auditGateway,
        (string) ($config['env'] ?? 'development')
    );

    JsonResponse::send($service->register($db, $actor, $payload));
} catch (\Throwable $exception) {
    $code = $exception->getCode();
    $httpStatus = is_int($code) && $code >= 400 && $code <= 599 ? $code : 500;
    $status = match ($httpStatus) {
        409 => 'CONFLICT',
        503 => 'PENDING_RETRY',
        default => 'ERROR',
    };

    JsonResponse::send([
        'ok' => false,
        'status' => $status,
        'error' => $exception->getMessage(),
    ], $httpStatus);
}
