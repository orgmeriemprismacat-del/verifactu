<?php

declare(strict_types=1);

require dirname(__DIR__, 4) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\InternalApiRequestRepository;
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
            $internalApi['gift_redemption_notification_signed_path']
            ?? '/api/gifts/redemption/notifications.php'
        )
    );

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

    assertGiftNotificationRole(
        $actor,
        (array) (($config['gift_redemption'] ?? [])['manage_roles'] ?? [])
    );

    $action = strtolower(trim((string) ($payload['action'] ?? '')));
    $uuidNotification = requiredNotificationUuid(
        $payload['uuid_notification'] ?? null
    );
    $service = new NotificationOutboxDeliveryService(new UuidGenerator());
    assertGiftNotificationScope($db, $uuidNotification);

    if ($action === 'claim') {
        JsonResponse::send([
            'ok' => true,
            'claim' => $service->claim($db, $uuidNotification, 'SMTP'),
        ]);
        return;
    }

    if ($action === 'complete') {
        $uuidAttempt = requiredNotificationUuid(
            $payload['uuid_delivery_attempt'] ?? null
        );
        if (!array_key_exists('accepted', $payload)
            || !is_bool($payload['accepted'])
        ) {
            throw SifException::validation(
                'Notification completion accepted flag is required'
            );
        }

        JsonResponse::send([
            'ok' => true,
            'delivery' => $service->complete(
                $db,
                $uuidNotification,
                $uuidAttempt,
                (bool) $payload['accepted'],
                optionalNotificationString(
                    $payload['provider_ref'] ?? null,
                    120
                ),
                optionalNotificationString(
                    $payload['error_code'] ?? null,
                    80
                )
            ),
        ]);
        return;
    }

    throw SifException::validation('Unknown gift notification action');
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}

function assertGiftNotificationRole(array $actor, array $allowedRoles): void
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
        throw SifException::forbidden(
            'Gift notification delivery role is not authorized'
        );
    }
}

function assertGiftNotificationScope(\PDO $db, string $uuidNotification): void
{
    $statement = $db->prepare(
        'SELECT TEMPLATE_CODE
         FROM notification_outbox
         WHERE UUID_NOTIFICATION = ?'
    );
    $statement->execute([$uuidNotification]);
    $templateCode = $statement->fetchColumn();

    if ($templateCode === false) {
        throw SifException::notFound('Notification outbox row not found');
    }
    if (!str_starts_with(
        strtoupper(trim((string) $templateCode)),
        'GIFT_REDEEM_'
    )) {
        throw SifException::forbidden(
            'Notification is outside gift redemption scope'
        );
    }
}

function requiredNotificationUuid(mixed $value): string
{
    $value = trim((string) $value);
    if (preg_match(
        '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-4[0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/D',
        $value
    ) !== 1) {
        throw SifException::validation('Invalid notification UUID');
    }

    return strtolower($value);
}

function optionalNotificationString(mixed $value, int $maxLength): ?string
{
    if ($value === null) {
        return null;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    if (strlen($value) > $maxLength) {
        throw SifException::validation('Notification metadata is too long');
    }

    return $value;
}
