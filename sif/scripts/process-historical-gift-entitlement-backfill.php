<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\CommercialEntitlementRepository;
use Prisma\Sif\Service\GiftEntitlementIssuerService;
use Prisma\Sif\Service\HistoricalGiftEntitlementBackfillService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
$env = (string) ($config['env'] ?? 'local');
if ($env === 'production') {
    fwrite(STDERR, "Refusing historical gift entitlement backfill with SIF_ENV=production.\n");
    exit(1);
}

$giftId = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with((string) $arg, '--gift-id=')) {
        $value = substr((string) $arg, strlen('--gift-id='));
        if (!ctype_digit($value) || (int) $value <= 0) {
            fwrite(STDERR, "Invalid --gift-id.\n");
            exit(1);
        }
        $giftId = (int) $value;
    }
}
if ($giftId === null) {
    fwrite(STDERR, "Usage: php sif/scripts/process-historical-gift-entitlement-backfill.php --gift-id=123\n");
    exit(1);
}

try {
    $sifDb = ConnectionFactory::make($config);
    $legacyDb = ConnectionFactory::makeLegacy($config);
    $entitlements = new CommercialEntitlementRepository(new UuidGenerator());
    $service = new HistoricalGiftEntitlementBackfillService(
        $entitlements,
        new GiftEntitlementIssuerService(new UuidGenerator(), $entitlements)
    );

    $result = $service->backfillOne($sifDb, $legacyDb, $giftId);
    $result['environment'] = $env;

    echo json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ), PHP_EOL;
    exit(0);
} catch (\Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'gift_id' => $giftId,
        'error' => $exception->getMessage(),
        'code' => $exception->getCode(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(1);
}
