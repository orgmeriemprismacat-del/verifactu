<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Repository\LegacyGiftSnapshotRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Service\InternalApiAuthenticator;
use Prisma\Sif\Service\RedsysDsOrderGenerator;
use Prisma\Sif\Service\RedsysGiftPaymentIntentService;
use Prisma\Sif\Service\RedsysPaymentIntentService;

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
    $sifDb = ConnectionFactory::make($config);
    $legacyDb = ConnectionFactory::makeLegacy($config);

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
        (string) ($internalApi['redsys_gift_intent_signed_path'] ?? '/api/redsys/gift-intent.php')
    );

    if (!in_array('PAYMENT_CHANNEL', (array) ($actor['roles'] ?? []), true)) {
        throw SifException::forbidden('PAYMENT_CHANNEL role is required');
    }

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

    $service = new RedsysGiftPaymentIntentService(
        new LegacyGiftSnapshotRepository(),
        new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        ),
        new RedsysDsOrderGenerator()
    );

    JsonResponse::send([
        'ok' => true,
        'intent' => $service->create($sifDb, $legacyDb, [
            'gift_code' => $payload['gift_code'] ?? null,
            'terminal' => $payload['terminal'] ?? null,
            'created_by' => (string) ($actor['actor_id'] ?? 'pay-prisma-cat'),
        ]),
    ]);
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}
