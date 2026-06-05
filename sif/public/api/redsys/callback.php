<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\RedsysCallbackService;

$payload = JsonResponse::fromInput();
if ($payload === null) {
    return;
}

try {
    $config = require dirname(__DIR__, 3) . '/config/sif.php';
    $db = ConnectionFactory::make($config);
    $service = new RedsysCallbackService(new RedsysNotificationRepository());

    // Wire the current PrisMa Redsys signature validation here before activating the channel.
    $signatureValid = false;

    JsonResponse::send($service->receiveCallback($db, $payload, $signatureValid));
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}
