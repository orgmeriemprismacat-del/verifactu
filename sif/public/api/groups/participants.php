<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Service\GroupParticipantAdditionDecisionService;
use Prisma\Sif\Service\GroupParticipantAdditionPreviewService;
use Prisma\Sif\Service\GroupParticipantChangeFingerprint;
use Prisma\Sif\Service\GroupParticipantRemovalDecisionService;
use Prisma\Sif\Service\GroupParticipantRemovalPreviewService;
use Prisma\Sif\Service\InternalApiAuthenticator;

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
        (string) (
            $internalApi['group_participant_change_signed_path']
            ?? '/api/groups/participants.php'
        )
    );

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

    $action = strtolower(trim((string) ($payload['action'] ?? '')));
    $allowedActions = ['preview_add', 'plan_add', 'preview_remove', 'plan_remove'];
    if (!in_array($action, $allowedActions, true)) {
        throw SifException::validation('Unknown group participant action');
    }

    $changeConfig = $config['group_participant_change'] ?? [];
    $roles = str_starts_with($action, 'plan_')
        ? (array) ($changeConfig['manage_roles'] ?? [])
        : (array) ($changeConfig['preview_roles'] ?? []);
    assertGroupParticipantRole($actor, $roles);

    $uuidFactura = requiredString(
        $payload['uuid_factura'] ?? null,
        'Missing group invoice UUID'
    );
    $fingerprints = new GroupParticipantChangeFingerprint();

    if ($action === 'preview_add' || $action === 'plan_add') {
        $candidate = $payload['candidate'] ?? null;
        if (!is_array($candidate)) {
            throw SifException::validation('Invalid group participant candidate');
        }

        $preview = (new GroupParticipantAdditionPreviewService())
            ->preview($db, $uuidFactura, $candidate);
        $preview['fingerprint'] = $fingerprints->calculate($preview);

        if ($action === 'preview_add') {
            JsonResponse::send([
                'ok' => true,
                'action' => $action,
                'preview' => $preview,
            ]);
            return;
        }

        $decision = $payload['decision'] ?? null;
        if (!is_array($decision)) {
            throw SifException::validation('Invalid group participant addition decision');
        }

        JsonResponse::send([
            'ok' => true,
            'action' => $action,
            'preview' => $preview,
            'plan' => (new GroupParticipantAdditionDecisionService())
                ->plan($preview, $decision),
        ]);
        return;
    }

    $idInsc = positiveInt(
        $payload['id_insc'] ?? null,
        'Invalid group participant enrollment ID'
    );
    $funds = new EnrollmentFundMovementRepository(new UuidGenerator());
    $preview = (new GroupParticipantRemovalPreviewService($funds))
        ->preview($db, $uuidFactura, $idInsc);
    $preview['fingerprint'] = $fingerprints->calculate($preview);

    if ($action === 'preview_remove') {
        JsonResponse::send([
            'ok' => true,
            'action' => $action,
            'preview' => $preview,
        ]);
        return;
    }

    $decision = $payload['decision'] ?? null;
    if (!is_array($decision)) {
        throw SifException::validation('Invalid group participant removal decision');
    }

    JsonResponse::send([
        'ok' => true,
        'action' => $action,
        'preview' => $preview,
        'plan' => (new GroupParticipantRemovalDecisionService())
            ->plan($preview, $decision),
    ]);
} catch (Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}

function assertGroupParticipantRole(array $actor, array $allowedRoles): void
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
        throw SifException::forbidden('Group participant change role is not authorized');
    }
}

function requiredString(mixed $value, string $message): string
{
    $value = trim((string) $value);
    if ($value === '' || mb_strlen($value, 'UTF-8') > 120) {
        throw SifException::validation($message);
    }

    return $value;
}

function positiveInt(mixed $value, string $message): int
{
    if (!is_numeric($value) || (int) $value <= 0) {
        throw SifException::validation($message);
    }

    return (int) $value;
}
