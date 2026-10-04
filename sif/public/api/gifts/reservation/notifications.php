<?php

declare(strict_types=1);

require dirname(__DIR__, 4) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Repository\LegacyGiftSnapshotRepository;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Service\GiftReservationNotificationService;
use Prisma\Sif\Service\InternalApiAuthenticator;
use Prisma\Sif\Service\NotificationOutboxDeliveryService;

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
            $internalApi['gift_reservation_notification_signed_path']
            ?? '/api/gifts/reservation/notifications.php'
        )
    );

    if (!in_array('PAYMENT_CHANNEL', (array) ($actor['roles'] ?? []), true)) {
        throw SifException::forbidden('PAYMENT_CHANNEL role is required');
    }

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

    $action = strtolower(trim((string) ($payload['action'] ?? '')));
    if ($action === 'enqueue') {
        $giftId = positiveGiftReservationInt($payload['gift_id'] ?? null);
        $legacyDb = ConnectionFactory::makeLegacy($config);
        $snapshot = (new LegacyGiftSnapshotRepository())->loadById($legacyDb, $giftId);
        $bundle = (new GiftReservationNotificationService(
            new NotificationOutboxRepository(new UuidGenerator())
        ))->enqueueBundle($db, $snapshot);

        JsonResponse::send(['ok' => true, 'bundle' => $bundle]);
        return;
    }

    $uuidNotification = requiredGiftReservationUuid(
        $payload['uuid_notification'] ?? null
    );
    assertGiftReservationNotificationScope($db, $uuidNotification);
    $delivery = new NotificationOutboxDeliveryService(new UuidGenerator());

    if ($action === 'claim') {
        JsonResponse::send([
            'ok' => true,
            'claim' => $delivery->claim($db, $uuidNotification, 'SMTP'),
        ]);
        return;
    }

    if ($action === 'complete') {
        if (!array_key_exists('accepted', $payload) || !is_bool($payload['accepted'])) {
            throw SifException::validation('Notification accepted flag is required');
        }

        JsonResponse::send([
            'ok' => true,
            'delivery' => $delivery->complete(
                $db,
                $uuidNotification,
                requiredGiftReservationUuid($payload['uuid_delivery_attempt'] ?? null),
                (bool) $payload['accepted'],
                null,
                ($payload['accepted'] ?? false) ? null : 'SMTP_SEND_FAILED'
            ),
        ]);
        return;
    }

    throw SifException::validation('Unknown gift reservation notification action');
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}

function positiveGiftReservationInt(mixed $value): int
{
    if (!is_numeric($value) || (int) $value <= 0) {
        throw SifException::validation('Invalid gift reservation ID');
    }

    return (int) $value;
}

function requiredGiftReservationUuid(mixed $value): string
{
    $value = strtolower(trim((string) $value));
    if (preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',
        $value
    ) !== 1) {
        throw SifException::validation('Invalid gift reservation notification UUID');
    }

    return $value;
}

function assertGiftReservationNotificationScope(\PDO $db, string $uuidNotification): void
{
    $stmt = $db->prepare(
        'SELECT TEMPLATE_CODE FROM notification_outbox WHERE UUID_NOTIFICATION = ?'
    );
    $stmt->execute([$uuidNotification]);
    $template = $stmt->fetchColumn();

    if ($template === false) {
        throw SifException::notFound('Notification outbox row not found');
    }
    if (!str_starts_with(strtoupper((string) $template), 'GIFT_RESERVATION_')) {
        throw SifException::forbidden('Notification is outside gift reservation scope');
    }
}
