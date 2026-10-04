<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Repository\PaymentActionEventRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Service\ExistingInvoiceEnrollmentFundAllocationService;
use Prisma\Sif\Service\ExistingInvoiceLegacyProjectionService;
use Prisma\Sif\Service\ExistingInvoicePaymentCommandService;
use Prisma\Sif\Service\ExistingInvoicePaymentPreviewService;
use Prisma\Sif\Service\ExistingInvoicePaymentNotificationService;
use Prisma\Sif\Service\InternalApiAuthenticator;
use Prisma\Sif\Service\ManualPaymentPayloadBuilder;
use Prisma\Sif\Service\ManualPaymentService;
use Prisma\Sif\Service\PaymentActionGateway;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Service\PaymentService;

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
        (string) ($internalApi['payment_signed_path'] ?? '/api/payments/register.php')
    );

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

    $roles = array_values(array_filter(array_map(
        static fn (mixed $role): string => strtoupper(trim((string) $role)),
        (array) ($actor['roles'] ?? [])
    )));
    $allowedRoles = array_values(array_filter(array_map(
        static fn (mixed $role): string => strtoupper(trim((string) $role)),
        (array) ($config['payments']['write_roles'] ?? [])
    )));
    if ($allowedRoles === [] || array_intersect($roles, $allowedRoles) === []) {
        throw SifException::forbidden('Payment registration role is not authorized');
    }

    $service = new PaymentService(
        new TransactionRunner($db),
        new PaymentPayloadValidator(),
        new PaymentRepository(
            new UuidGenerator(),
            new PaymentStatusCalculator()
        )
    );

    $action = strtolower(trim((string) ($payload['action'] ?? '')));

    if ($action === 'preview_existing_invoice') {
        $selector = $payload['selector'] ?? null;
        if (!is_array($selector)) {
            throw SifException::validation('Existing invoice preview requires selector object');
        }

        $result = (new ExistingInvoicePaymentPreviewService(
            new ManualPaymentInvoiceRepository(),
            new ExistingInvoiceLegacyProjectionService()
        ))->preview($db, $selector);
    } elseif ($action === 'register_existing_invoice') {
        $payment = $payload['payment'] ?? null;
        if (!is_array($payment)) {
            throw SifException::validation('Existing invoice payment requires payment object');
        }

        $idempotencyKey = trim((string) ($payment['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            throw SifException::validation('Existing invoice payment requires idempotency key');
        }

        $effectiveRoles = array_values(array_intersect($roles, $allowedRoles));
        $effectiveRole = (string) ($effectiveRoles[0] ?? '');
        if ($effectiveRole === '') {
            throw SifException::forbidden('Payment registration role is not authorized');
        }

        $configuredEnvironment = strtoupper(trim((string) ($config['env'] ?? '')));
        $sourceEnvironment = match ($configuredEnvironment) {
            'PROD', 'PRODUCTION' => 'PRODUCTION',
            'PRE', 'PREPRODUCTION', 'PRE-PRODUCTION', 'STAGING' => 'PREPRODUCTION',
            'TEST', 'TESTING' => 'TEST',
            'MIGRATION' => 'MIGRATION',
            'LOCAL', 'DEV', 'DEVELOPMENT' => 'DEVELOPMENT',
            default => throw SifException::validation('Unsupported SIF environment for payment audit'),
        };

        $auditContext = [
            'request_id' => (string) ($actor['request_id'] ?? ''),
            'correlation_id' => $idempotencyKey,
            'action' => 'CREATE',
            'source_environment' => $sourceEnvironment,
            'source_channel' => 'INTRANET',
            'actor_type' => 'HUMAN',
            'actor_id' => (string) ($actor['actor_id'] ?? ''),
            'actor_role' => $effectiveRole,
            'reason_code' => 'UC002_EXISTING_INVOICE_PAYMENT',
            'payment_idempotency_key' => $idempotencyKey,
            'occurred_at' => (new \DateTimeImmutable(
                'now',
                new \DateTimeZone('Europe/Madrid')
            ))->format('Y-m-d H:i:s.u'),
        ];

        $command = new ExistingInvoicePaymentCommandService(
            new ManualPaymentService(
                new ManualPaymentInvoiceRepository(),
                new ManualPaymentPayloadBuilder(),
                $service
            ),
            new ExistingInvoiceLegacyProjectionService(),
            new ExistingInvoiceEnrollmentFundAllocationService(
                new EnrollmentFundMovementRepository(new UuidGenerator())
            ),
            new ExistingInvoicePaymentNotificationService(
                new NotificationOutboxRepository(new UuidGenerator())
            )
        );

        $result = (new PaymentActionGateway(
            $db,
            new TransactionRunner($db),
            new PaymentActionEventRepository(new UuidGenerator())
        ))->run(
            $auditContext,
            static fn (\PDO $transactionDb): array => $command->register(
                $transactionDb,
                $payload
            )
        );
    } elseif ($action === '') {
        // Backwards-compatible low-level registration for trusted internal callers.
        $result = $service->registerPayment($payload);
    } else {
        throw SifException::validation('Unknown payment registration action');
    }

    $result['actor_id'] = (string) ($actor['actor_id'] ?? '');
    $result['request_id'] = (string) ($actor['request_id'] ?? '');

    JsonResponse::send($result);
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}
