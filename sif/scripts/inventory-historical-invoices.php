<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Service\HistoricalInvoiceInventoryService;
use Prisma\Sif\Service\PrivateDocumentStore;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
$legacyId = null;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with((string) $arg, '--legacy-id=')) {
        $value = substr((string) $arg, strlen('--legacy-id='));
        if (!ctype_digit($value) || (int) $value <= 0) {
            fwrite(STDERR, "Invalid --legacy-id.\n");
            exit(1);
        }
        $legacyId = (int) $value;
    }
}

try {
    $sifDb = ConnectionFactory::make($config);
    $legacyDb = ConnectionFactory::makeLegacy($config);

    $store = null;
    $documentRoot = trim((string) ($config['documents']['root'] ?? ''));
    if ($documentRoot !== '') {
        $store = new PrivateDocumentStore(
            $documentRoot,
            (int) ($config['documents']['max_bytes'] ?? 20971520)
        );
    }

    $result = (new HistoricalInvoiceInventoryService())->inventory(
        $sifDb,
        $legacyDb,
        $legacyId,
        $store
    );
    $result['scope'] = 'historical_invoice_inventory';
    $result['environment'] = (string) ($config['env'] ?? 'local');
    $result['production_authorized'] = false;

    echo json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ), PHP_EOL;
    exit(0);
} catch (\Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'read_only' => true,
        'scope' => 'historical_invoice_inventory',
        'error' => $exception->getMessage(),
        'code' => $exception->getCode(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(1);
}
