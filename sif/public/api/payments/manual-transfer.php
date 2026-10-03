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
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Repository\PaymentActionEventRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Repository\SifAuditEventRepository;
use Prisma\Sif\Service\GeneratedInvoiceLegacyPaymentSyncService;
use Prisma\Sif\Service\InternalApiAuthenticator;
use Prisma\Sif\Service\ManualPaymentPayloadBuilder;
use Prisma\Sif\Service\ManualPaymentService;
use Prisma\Sif\Service\ManualTransferCommandService;
use Prisma\Sif\Service\ManualTransferLegacyProjectionService;
use Prisma\Sif\Service\ManualTransferNotificationService;
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

    $paymentEvents = new PaymentActionEventRepository(new UuidGenerator());
    $auditGateway = new PaymentActionGateway(
        $db,
        new TransactionRunner($db),
        $paymentEvents
    );

    $service = new ManualTransferCommandService(
        $manualService,
        (array) (($config['payments']['manual_transfer_roles'] ?? [])),
        $auditGateway,
        (string) ($config['env'] ?? 'development'),
        new OperationalEventRepository(new UuidGenerator()),
        new SifAuditEventRepository(new UuidGenerator())
    );

    $result = $service->register($db, $actor, $payload);

    $legacyProjection = new ManualTransferLegacyProjectionService(
        $db,
        $paymentEvents,
        new GeneratedInvoiceLegacyPaymentSyncService(),
        (string) ($config['env'] ?? 'development')
    );

    $legacyDbConfig = $config['legacy_db'] ?? [];
    if (trim((string) ($legacyDbConfig['dsn'] ?? '')) === '') {
        $legacyProjection->recordUnavailable(
            $actor,
            $payload,
            $result,
            'LEGACY_DB_NOT_CONFIGURED'
        );
        $result['payment_status'] = $result['status'] ?? null;
        $result['status'] = 'PENDING_RETRY';
        $result['legacy_sync'] = [
            'status' => 'PENDING_RETRY',
            'error' => 'Legacy DB is not configured for payment projection',
        ];
        JsonResponse::send($result, 202);
        return;
    }

    try {
        $legacyDb = ConnectionFactory::makeLegacy($config);
        $sync = $legacyProjection->project(
            $legacyDb,
            $actor,
            $payload,
            $result
        );

        $result['legacy_sync'] = [
            'status' => 'SYNCED',
            'details' => $sync,
        ];

        try {
            $legacyIntranetConfig = $config['legacy_intranet_db'] ?? [];
            $legacyIntranetDb = trim((string) ($legacyIntranetConfig['dsn'] ?? '')) !== ''
                ? ConnectionFactory::makeLegacyIntranet($config)
                : null;

            $notificationBundle = (new ManualTransferNotificationService(
                new NotificationOutboxRepository(new UuidGenerator())
            ))->enqueue(
                $db,
                $legacyDb,
                $legacyIntranetDb,
                $result,
                $sync,
                $payload
            );

            $result['notification_outbox'] = $notificationBundle;

            if (($notificationBundle['responsible_skip_reason'] ?? null)
                === 'LEGACY_INTRANET_DB_NOT_CONFIGURED'
            ) {
                $result['payment_status'] = $result['status'] ?? null;
                $result['status'] = 'PENDING_RETRY';
                JsonResponse::send($result, 202);
                return;
            }

            JsonResponse::send($result);
        } catch (\Throwable $notificationException) {
            $result['payment_status'] = $result['status'] ?? null;
            $result['status'] = 'PENDING_RETRY';
            $result['notification_outbox'] = [
                'status' => 'PENDING_RETRY',
                'error' => $notificationException->getMessage(),
            ];
            JsonResponse::send($result, 202);
        }
    } catch (\Throwable $syncException) {
        $result['payment_status'] = $result['status'] ?? null;
        $result['status'] = 'PENDING_RETRY';
        $result['legacy_sync'] = [
            'status' => 'PENDING_RETRY',
            'error' => $syncException->getMessage(),
        ];
        JsonResponse::send($result, 202);
    }
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
