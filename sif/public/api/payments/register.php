<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Service\InternalApiAuthenticator;
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
        (string) ($internalApi['payment_register_signed_path'] ?? '/api/payments/register.php')
    );

    $allowed = [];
    foreach ((array) (($config['payment_register'] ?? [])['write_roles'] ?? []) as $role) {
        $value = strtoupper(trim((string) $role));
        if ($value !== '') {
            $allowed[$value] = true;
        }
    }

    $actorRoles = [];
    foreach ((array) ($actor['roles'] ?? []) as $role) {
        $value = strtoupper(trim((string) $role));
        if ($value !== '') {
            $actorRoles[$value] = true;
        }
    }

    if ($allowed === [] || array_intersect(array_keys($actorRoles), array_keys($allowed)) === []) {
        throw SifException::forbidden('Payment register role is not authorized');
    }

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

    $service = new PaymentService(
        new TransactionRunner($db),
        new PaymentPayloadValidator(),
        new PaymentRepository(
            new UuidGenerator(),
            new PaymentStatusCalculator()
        )
    );

    JsonResponse::send($service->registerPayment($payload));
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}
