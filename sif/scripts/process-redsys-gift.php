<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\FiscalSequenceRepository;
use Prisma\Sif\Repository\InvoiceRepository;
use Prisma\Sif\Repository\LegacyGiftSnapshotRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\InvoiceService;
use Prisma\Sif\Service\LegacyGiftInvoicePayloadBuilder;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Service\RedsysGiftInvoiceService;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to process Redsys gift invoices with SIF_ENV=production.\n");
    exit(1);
}

[$dsOrder, $selector] = parseGiftArgs(array_slice($argv, 1));
if ($dsOrder === '' || $selector === null) {
    fwrite(STDERR, "Usage: php sif/scripts/process-redsys-gift.php DS_ORDER (--gift-id=ID|--gift-code=CODI)\n");
    exit(1);
}

try {
    $sifDb = ConnectionFactory::make($config);
    $legacyDb = ConnectionFactory::makeLegacy($config);
    $notifications = new RedsysNotificationRepository();
    $invoiceService = new InvoiceService(
        new TransactionRunner($sifDb),
        new InvoicePayloadValidator(),
        new FiscalSequenceRepository(),
        new InvoiceRepository(new UuidGenerator(), new HashCalculator()),
        new PaymentPayloadValidator(),
        new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
    );
    $service = new RedsysGiftInvoiceService(
        $notifications,
        new LegacyGiftSnapshotRepository(),
        new LegacyGiftInvoicePayloadBuilder(),
        new RedsysInvoicePayloadBuilder($notifications),
        $invoiceService
    );

    if (($selector['type'] ?? '') === 'id') {
        $result = $service->issueByGiftIdFromValidatedNotification(
            $sifDb,
            $legacyDb,
            $dsOrder,
            (int) ($selector['value'] ?? 0)
        );
    } else {
        $result = $service->issueByGiftCodeFromValidatedNotification(
            $sifDb,
            $legacyDb,
            $dsOrder,
            (string) ($selector['value'] ?? '')
        );
    }

    $result['legacy_sync_executed'] = false;
    unset($result['legacy_sync']);

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(0);
} catch (\Throwable $exception) {
    echo json_encode([
        'ok' => false,
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
