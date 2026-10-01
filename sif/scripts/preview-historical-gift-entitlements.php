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
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--gift-id=')) {
        $value = substr($argument, strlen('--gift-id='));
        if (!ctype_digit($value) || (int) $value <= 0) {
            fwrite(STDERR, "Invalid --gift-id.\n");
            exit(2);
        }
        $giftId = (int) $value;
    }
}

try {
    $config = require dirname(__DIR__) . '/config/sif.php';
    $sifDb = ConnectionFactory::make($config);
    $legacyDb = ConnectionFactory::makeLegacy($config);
    $entitlements = new CommercialEntitlementRepository(new UuidGenerator());
    $service = new HistoricalGiftEntitlementBackfillService(
        $entitlements,
        new GiftEntitlementIssuerService(new UuidGenerator(), $entitlements)
    );

    $result = $service->inventory($sifDb, $legacyDb, $giftId);
    $result['dry_run'] = true;
    $result['mutated'] = false;

    echo json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ), PHP_EOL;
    exit($result['ok'] ? 0 : 1);
} catch (Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'dry_run' => true,
        'mutated' => false,
        'error_class' => get_class($exception),
        'error' => $exception->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(1);
}
