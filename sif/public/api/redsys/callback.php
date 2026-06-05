<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\RedsysCallbackService;
use Prisma\Sif\Service\RedsysSignatureValidator;

try {
    $config = require dirname(__DIR__, 3) . '/config/sif.php';
    $db = ConnectionFactory::make($config);
    $validator = new RedsysSignatureValidator((string) ($config['redsys']['merchant_key'] ?? ''));
    $service = new RedsysCallbackService(new RedsysNotificationRepository());
    $payload = $validator->decodeAndVerify($_POST, $_GET);

    JsonResponse::send($service->receiveCallback($db, $payload, true));
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}
