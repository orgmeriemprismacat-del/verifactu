<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyGiftSnapshotRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\LegacyGiftInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to preview Redsys gift invoices with SIF_ENV=production.\n");
    exit(1);
}

[$dsOrder, $selector] = parseGiftArgs(array_slice($argv, 1));
if ($dsOrder === '' || $selector === null) {
    fwrite(STDERR, "Usage: php sif/scripts/preview-redsys-gift.php DS_ORDER (--gift-id=ID|--gift-code=CODI)\n");
    exit(1);
}

try {
    $sifDb = ConnectionFactory::make($config);
    $legacyDb = ConnectionFactory::makeLegacy($config);
    $notifications = new RedsysNotificationRepository();
    $notification = $notifications->findByDsOrder($sifDb, $dsOrder);

    if ($notification === null) {
        throw SifException::validation('Redsys notification not found');
    }

    if ((string) $notification['STATUS'] !== 'VALIDATED') {
        throw SifException::conflict('Redsys notification is not validated');
    }

    $snapshot = loadGiftSnapshot($legacyDb, $selector);
    assertGiftAmountMatchesNotification($snapshot, $notification);
    $basePayload = (new LegacyGiftInvoicePayloadBuilder())->build($snapshot);
    $payload = (new RedsysInvoicePayloadBuilder($notifications))
        ->buildFromValidatedNotification($sifDb, $dsOrder, $basePayload);

    echo json_encode([
        'ok' => true,
        'dry_run' => true,
        'ds_order' => $dsOrder,
        'gift_selector' => $selector,
        'payload' => $payload,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(0);
} catch (\Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'dry_run' => true,
        'error' => $exception->getMessage(),
        'code' => $exception->getCode(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(1);
}

function parseGiftArgs(array $args): array
{
    $dsOrder = '';
    $selector = null;

    foreach ($args as $arg) {
        $arg = (string) $arg;
        if (str_starts_with($arg, '--gift-id=')) {
            $selector = ['type' => 'id', 'value' => substr($arg, strlen('--gift-id='))];
            continue;
        }

        if (str_starts_with($arg, '--gift-code=')) {
            $selector = ['type' => 'code', 'value' => substr($arg, strlen('--gift-code='))];
            continue;
        }

        if (!str_starts_with($arg, '--') && $dsOrder === '') {
            $dsOrder = trim($arg);
        }
    }

    return [$dsOrder, $selector];
}

function loadGiftSnapshot(\PDO $legacyDb, array $selector): array
{
    $repository = new LegacyGiftSnapshotRepository();
    if (($selector['type'] ?? '') === 'id') {
        return $repository->loadById($legacyDb, (int) ($selector['value'] ?? 0));
    }

    return $repository->loadByCode($legacyDb, (string) ($selector['value'] ?? ''));
}

function assertGiftAmountMatchesNotification(array $snapshot, array $notification): void
{
    $gift = $snapshot['gift'] ?? [];
    $giftAmount = number_format((float) ($gift['IMPORT'] ?? 0), 2, '.', '');
    $notificationAmount = number_format((float) ($notification['IMPORT'] ?? 0), 2, '.', '');

    if ($giftAmount !== $notificationAmount) {
        throw SifException::conflict('Redsys gift amount does not match gift amount');
    }
}
