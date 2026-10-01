<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\CommercialEntitlementRepository;
use Prisma\Sif\Service\GiftEntitlementIssuerService;
use Prisma\Sif\Service\HistoricalGiftEntitlementBackfillService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script is CLI-only.\n");
    exit(2);
}

$giftId = null;
$apply = false;
$productionConfirmation = null;

foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--gift-id=')) {
        $value = substr($argument, strlen('--gift-id='));
        if (!ctype_digit($value) || (int) $value <= 0) {
            fwrite(STDERR, "Invalid --gift-id.\n");
            exit(2);
        }
        $giftId = (int) $value;
        continue;
    }

    if ($argument === '--apply') {
        $apply = true;
        continue;
    }

    if (str_starts_with($argument, '--confirm-production=')) {
        $productionConfirmation = substr(
            $argument,
            strlen('--confirm-production=')
        );
    }
}

if ($giftId === null || !$apply) {
    fwrite(
        STDERR,
        "Usage: php process-historical-gift-entitlement.php --gift-id=<ID> --apply"
        . " [--confirm-production=UC018-GIFT-<ID>]\n"
    );
    exit(2);
}

try {
    $config = require dirname(__DIR__) . '/config/sif.php';
    $env = strtolower((string) ($config['env'] ?? 'local'));

    if ($env === 'production'
        && $productionConfirmation !== 'UC018-GIFT-' . $giftId
    ) {
        throw new RuntimeException(
            'Production backfill requires exact --confirm-production=UC018-GIFT-'
            . $giftId
        );
    }

    $sifDb = ConnectionFactory::make($config);
    $legacyDb = ConnectionFactory::makeLegacy($config);
    $entitlements = new CommercialEntitlementRepository(new UuidGenerator());
    $service = new HistoricalGiftEntitlementBackfillService(
        $entitlements,
        new GiftEntitlementIssuerService(new UuidGenerator(), $entitlements)
    );

    $before = $service->inventory($sifDb, $legacyDb, $giftId);
    $result = $service->backfill($sifDb, $legacyDb, $giftId);
    $after = $service->inventory($sifDb, $legacyDb, $giftId);

    echo json_encode([
        'ok' => true,
        'environment' => $env,
        'gift_id' => $giftId,
        'before' => $before,
        'result' => $result,
        'after' => $after,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'gift_id' => $giftId,
        'error_class' => get_class($exception),
        'error' => $exception->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(1);
}
