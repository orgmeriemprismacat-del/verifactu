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
$environment = strtolower(trim((string) ($config['env'] ?? 'local')));
if (in_array($environment, ['prod', 'production'], true)) {
    fwrite(STDERR, "Historical invoice preflight refuses SIF_ENV=production.\n");
    exit(1);
}

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

    $documentRoot = trim((string) ($config['documents']['root'] ?? ''));
    $documentStore = null;
    $documentStorageReady = false;
    if ($documentRoot !== '') {
        try {
            $documentStore = new PrivateDocumentStore(
                $documentRoot,
                (int) ($config['documents']['max_bytes'] ?? 20971520)
            );
            $documentStorageReady = true;
        } catch (\Throwable) {
            $documentStore = null;
        }
    }

    $inventory = (new HistoricalInvoiceInventoryService())->inventory(
        $sifDb,
        $legacyDb,
        $legacyId,
        $documentStore
    );

    $summary = (array) ($inventory['summary'] ?? []);
    $guardEnabled = filter_var(
        getenv('SIF_BLOCK_LEGACY_INVOICE_MUTATIONS') ?: '0',
        FILTER_VALIDATE_BOOLEAN
    );
    $queryEnabled = filter_var(
        getenv('SIF_UC007_QUERY_ENABLED') ?: '0',
        FILTER_VALIDATE_BOOLEAN
    );

    $checks = [
        'sif_connection' => true,
        'legacy_connection' => true,
        'source_rows_valid' => (int) ($summary['invalid_legacy'] ?? 0) === 0,
        'existing_imports_unambiguous' => (int) ($summary['ambiguous'] ?? 0) === 0,
        'existing_imports_consistent' => (int) ($summary['mismatch'] ?? 0) === 0,
        'document_storage_configured' => $documentStorageReady,
        'legacy_mutation_guard_enabled' => $guardEnabled,
        'uc007_query_enabled' => $queryEnabled,
        'production_authorized' => false,
    ];

    $readyForMigration = $checks['source_rows_valid']
        && $checks['existing_imports_unambiguous']
        && $checks['existing_imports_consistent']
        && $checks['document_storage_configured']
        && $checks['legacy_mutation_guard_enabled']
        && $checks['uc007_query_enabled'];

    $result = [
        'ok' => true,
        'read_only' => true,
        'scope' => 'historical_invoice_preflight',
        'environment' => (string) ($config['env'] ?? 'local'),
        'legacy_id' => $legacyId,
        'checks' => $checks,
        'ready_for_controlled_migration' => $readyForMigration,
        'ready_to_close_reconciliation' => (bool) ($inventory['fully_reconciled'] ?? false),
        'inventory' => [
            'summary' => $summary,
            'groups' => $inventory['groups'] ?? [],
            'document_verification_enabled' => $inventory['document_verification_enabled'] ?? false,
        ],
    ];

    echo json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ), PHP_EOL;

    exit($readyForMigration ? 0 : 2);
} catch (\Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'read_only' => true,
        'scope' => 'historical_invoice_preflight',
        'error' => $exception->getMessage(),
        'code' => $exception->getCode(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(1);
}
