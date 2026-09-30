<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Repository\RedsysCallbackQueueRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Service\InternalApiAuthenticator;
use Prisma\Sif\Service\RedsysCoursePaymentStatusService;

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
        (string) ($internalApi['redsys_course_status_signed_path'] ?? '/api/redsys/course-status.php')
    );

    if (!in_array('PAYMENT_CHANNEL', (array) ($actor['roles'] ?? []), true)) {
        throw SifException::forbidden('PAYMENT_CHANNEL role is required');
    }

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

    $dsOrder = trim((string) ($payload['ds_order'] ?? ''));
    $idpagRaw = trim((string) ($payload['idpag'] ?? ''));
    if ($idpagRaw === '' || !ctype_digit($idpagRaw)) {
        throw SifException::validation('Invalid IDPAG');
    }

    $service = new RedsysCoursePaymentStatusService(
        new RedsysPaymentIntentRepository(),
        new RedsysNotificationRepository(),
        new RedsysCallbackQueueRepository(new UuidGenerator())
    );

    JsonResponse::send([
        'ok' => true,
        'payment' => $service->status($db, $dsOrder, (int) $idpagRaw),
    ]);
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}
