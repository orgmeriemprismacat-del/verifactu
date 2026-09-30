<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Service\InternalApiAuthenticator;
use Prisma\Sif\Service\NovicePromotionSecretaryDecisionProjector;

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
        (string) ($internalApi['novice_promotion_signed_path'] ?? '/api/novice-promotion/manage.php')
    );

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

    assertNoviceManageRole(
        $actor,
        (array) (($config['novice_promotion'] ?? [])['manage_roles'] ?? [])
    );

    $action = strtolower(trim((string) ($payload['action'] ?? '')));
    if ($action !== 'project_decision') {
        throw SifException::validation('Unknown novice promotion action');
    }

    requiredRequestId($payload['request_id'] ?? null);
    $idInsc = positiveInt($payload['id_insc'] ?? null, 'Invalid novice enrollment ID');

    $stmt = $db->prepare(
        "SELECT UUID_OPERATION
         FROM commercial_operation
         WHERE SOURCE_TYPE = 'CURS'
           AND SOURCE_ID = ?
           AND PRODUCT_TYPE = 'CURS'
           AND PRODUCT_CODE = 'JASOM'
         ORDER BY CREATED_AT, UUID_OPERATION"
    );
    $stmt->execute([(string) $idInsc]);
    $operations = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($operations) !== 1) {
        throw SifException::conflict('A single staged JASOM operation is required for novice decision projection.');
    }

    $legacyDb = ConnectionFactory::makeLegacy($config);
    $decision = (new NovicePromotionSecretaryDecisionProjector(new UuidGenerator()))
        ->projectDecision(
            $db,
            $legacyDb,
            (string) $operations[0],
            (string) ($actor['actor_id'] ?? '')
        );

    JsonResponse::send([
        'ok' => true,
        'decision' => $decision,
    ]);
} catch (Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}

function assertNoviceManageRole(array $actor, array $allowedRoles): void
{
    $actorRoles = array_values(array_filter(array_map(
        static fn (mixed $role): string => strtoupper(trim((string) $role)),
        (array) ($actor['roles'] ?? [])
    )));
    $allowed = array_values(array_filter(array_map(
        static fn (mixed $role): string => strtoupper(trim((string) $role)),
        $allowedRoles
    )));

    if ($allowed === [] || array_intersect($actorRoles, $allowed) === []) {
        throw SifException::forbidden('Novice promotion manage role is not authorized');
    }
}

function requiredRequestId(mixed $value): string
{
    $requestId = trim((string) $value);
    if (
        $requestId === ''
        || strlen($requestId) > 120
        || preg_match('/^[A-Za-z0-9._:-]+$/D', $requestId) !== 1
    ) {
        throw SifException::validation('Invalid novice decision request id');
    }

    return $requestId;
}

function positiveInt(mixed $value, string $message): int
{
    if (!is_numeric($value) || (int) $value <= 0) {
        throw SifException::validation($message);
    }

    return (int) $value;
}
