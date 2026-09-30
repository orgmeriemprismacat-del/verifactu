<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Repository\InvoiceReadRepository;
use Prisma\Sif\Service\CourseChangeImpactClassifier;
use Prisma\Sif\Service\CourseChangePreviewGateway;
use Prisma\Sif\Service\CourseChangePreviewService;
use Prisma\Sif\Service\InternalApiAuthenticator;

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
        (string) ($internalApi['course_change_signed_path'] ?? '/api/course-changes/preview.php')
    );

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

    $courseChangeConfig = $config['course_change'] ?? [];
    $gateway = new CourseChangePreviewGateway(
        new CourseChangePreviewService(
            $db,
            new InvoiceReadRepository(),
            new CourseChangeImpactClassifier()
        ),
        (array) ($courseChangeConfig['preview_roles'] ?? [])
    );

    JsonResponse::send($gateway->preview($actor, $payload));
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}
