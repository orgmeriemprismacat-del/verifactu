<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\DebtClaimCaseRepository;
use Prisma\Sif\Repository\DebtSnapshotRepository;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Service\DebtClaimCoordinator;
use Prisma\Sif\Service\InternalApiAuthenticator;

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    JsonResponse::send(['ok' => false, 'error' => 'Method not allowed'], 405);
    return;
}
$raw = file_get_contents('php://input');
if ($raw === false) {
    JsonResponse::send(['ok' => false, 'error' => 'Could not read request body'], 400);
    return;
}

try {
    $config = require dirname(__DIR__, 3) . '/config/sif.php';
    $db = ConnectionFactory::make($config);
    $internal = $config['internal_api'] ?? [];
    $actor = (new InternalApiAuthenticator(
        $db,
        new InternalApiRequestRepository(),
        (string) ($internal['key_id'] ?? ''),
        (string) ($internal['secret'] ?? ''),
        (int) ($internal['max_clock_skew_seconds'] ?? 300)
    ))->authenticate(
        $_SERVER,
        $raw,
        'POST',
        (string) ($internal['debt_claim_signed_path'] ?? '/api/debt-claims/manage.php')
    );
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

    $settings = $config['debt_claims'] ?? [];
    $uuids = new UuidGenerator();
    $service = new DebtClaimCoordinator(
        $db,
        new TransactionRunner($db),
        new DebtSnapshotRepository(),
        new DebtClaimCaseRepository($uuids),
        new NotificationOutboxRepository($uuids),
        new OperationalEventRepository($uuids),
        (array) ($settings['read_roles'] ?? []),
        (array) ($settings['manage_roles'] ?? [])
    );

    $action = strtolower(trim((string) ($payload['action'] ?? '')));
    $result = match ($action) {
        'preview' => $service->preview($actor, $payload),
        'record_notice' => $service->recordNotice($actor, $payload),
        'reconcile_after_payment' => $service->reconcileAfterPayment($actor, $payload),
        default => throw SifException::validation('Unknown debt claim action'),
    };
    JsonResponse::send($result);
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}
