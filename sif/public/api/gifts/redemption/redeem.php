<?php

declare(strict_types=1);

require dirname(__DIR__, 4) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\CommercialEntitlementRepository;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Service\GiftEnrollmentStager;
use Prisma\Sif\Service\GiftRedemptionNotificationBundleService;
use Prisma\Sif\Service\GiftRedemptionOrchestrator;
use Prisma\Sif\Service\GiftRedemptionService;
use Prisma\Sif\Service\GiftRedemptionTrustedContextResolver;
use Prisma\Sif\Service\InternalApiAuthenticator;
use Prisma\Sif\Service\LegacyGiftUsageReconciler;

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
    $config = require dirname(__DIR__, 4) . '/config/sif.php';
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
            $internalApi['gift_redemption_signed_path']
            ?? '/api/gifts/redemption/redeem.php'
        )
    );

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

    assertGiftRedemptionRole(
        $actor,
        (array) (($config['gift_redemption'] ?? [])['manage_roles'] ?? [])
    );

    $enrollmentId = positiveGiftEnrollmentId($payload['enrollment_id'] ?? null);
    $giftCode = requiredGiftString($payload['gift_code'] ?? null, 'gift_code', 200);

    $legacyDb = ConnectionFactory::makeLegacy($config);
    $entitlements = new CommercialEntitlementRepository(new UuidGenerator());
    $execution = (new GiftRedemptionOrchestrator(
        new GiftRedemptionTrustedContextResolver($entitlements),
        new GiftEnrollmentStager(new UuidGenerator(), $entitlements),
        new GiftRedemptionService(
            $entitlements,
            new EnrollmentFundMovementRepository(new UuidGenerator())
        ),
        new LegacyGiftUsageReconciler()
    ))->execute(
        $db,
        $legacyDb,
        $enrollmentId,
        $giftCode,
        'WEB',
        (string) ($actor['request_id'] ?? ''),
        (string) ($actor['actor_id'] ?? '')
    );

    $stage = (array) $execution['stage'];
    $notificationBundle = (new GiftRedemptionNotificationBundleService(
        new NotificationOutboxRepository(new UuidGenerator())
    ))->enqueue(
        $db,
        $legacyDb,
        $enrollmentId,
        $execution
    );

    JsonResponse::send([
        'ok' => true,
        'stage' => [
            'uuid_operation' => (string) $stage['uuid_operation'],
            'uuid_entitlement' => (string) $stage['uuid_entitlement'],
            'enrollment_id' => (int) $stage['enrollment_id'],
            'status' => (string) $stage['status'],
            'idempotency_reused' => (bool) $stage['idempotency_reused'],
        ],
        'redemption' => $execution['redemption'],
        'legacy_reconciliation' => $execution['legacy_reconciliation'],
        'notification_bundle' => $notificationBundle,
    ]);
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}

function assertGiftRedemptionRole(array $actor, array $allowedRoles): void
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
        throw SifException::forbidden('Gift redemption role is not authorized');
    }
}

function positiveGiftEnrollmentId(mixed $value): int
{
    if (!is_numeric($value) || (int) $value <= 0) {
        throw SifException::validation('Invalid gift enrollment ID');
    }

    return (int) $value;
}

function requiredGiftString(mixed $value, string $field, int $maxLength): string
{
    $value = trim((string) $value);
    if ($value === '' || strlen($value) > $maxLength) {
        throw SifException::validation('Invalid gift redemption field: ' . $field);
    }

    return $value;
}
