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

$baseDir = dirname(__DIR__);
$config = require $baseDir . '/config/sif.php';
$checks = [
    'inventory_script_read_only' => true,
    'backfill_service_present' => is_file(
        $baseDir . '/src/Service/HistoricalGiftEntitlementBackfillService.php'
    ),
    'processor_present' => is_file(
        $baseDir . '/scripts/process-historical-gift-entitlement-backfill.php'
    ),
    'sif_database_connectivity' => false,
    'legacy_database_connectivity' => false,
    'commercial_operation_table' => false,
    'commercial_entitlement_table' => false,
    'commercial_entitlement_event_table' => false,
    'factura_table' => false,
    'fact_rels_table' => false,
    'payment_transaction_table' => false,
    'payment_allocation_table' => false,
    'legacy_regal_table' => false,
    'unused_historical_gifts_covered' => false,
    'ready_to_backfill_queue_empty' => false,
    'unused_review_queue_empty' => false,
];
$errors = [];
$inventory = null;
$sifDb = null;
$legacyDb = null;

try {
    $sifDb = ConnectionFactory::make($config);
    $checks['sif_database_connectivity'] = true;
    foreach ([
        'commercial_operation',
        'commercial_entitlement',
        'commercial_entitlement_event',
        'factura',
        'fact_rels',
        'payment_transaction',
        'payment_allocation',
    ] as $table) {
        $checks[$table . '_table'] = tableExists($sifDb, $table);
    }
} catch (\Throwable $exception) {
    $errors['sif_database'] = $exception->getMessage();
}

try {
    $legacyDb = ConnectionFactory::makeLegacy($config);
    $checks['legacy_database_connectivity'] = true;
    $checks['legacy_regal_table'] = tableExists($legacyDb, 'regal');
} catch (\Throwable $exception) {
    $errors['legacy_database'] = $exception->getMessage();
}

if ($sifDb instanceof \PDO
    && $legacyDb instanceof \PDO
    && $checks['commercial_entitlement_table']
    && $checks['factura_table']
    && $checks['fact_rels_table']
    && $checks['payment_transaction_table']
    && $checks['payment_allocation_table']
    && $checks['legacy_regal_table']
) {
    try {
        $entitlements = new CommercialEntitlementRepository(new UuidGenerator());
        $inventory = (new HistoricalGiftEntitlementBackfillService(
            $entitlements,
            new GiftEntitlementIssuerService(new UuidGenerator(), $entitlements)
        ))->inventory($sifDb, $legacyDb);

        $summary = (array) ($inventory['summary'] ?? []);
        $checks['unused_historical_gifts_covered'] =
            (int) ($summary['blocking_unused'] ?? -1) === 0;
        $checks['ready_to_backfill_queue_empty'] =
            (int) ($summary['ready_to_backfill'] ?? -1) === 0;

        $unusedNeedsReview = 0;
        foreach ((array) ($inventory['items'] ?? []) as $item) {
            if (($item['legacy_used'] ?? true) === false
                && !in_array(
                    (string) ($item['status'] ?? ''),
                    [
                        'ENTITLEMENT_PRESENT',
                        'READY_TO_BACKFILL',
                        'UNPAID_LEGACY_GIFT_NO_RIGHT',
                    ],
                    true
                )
            ) {
                $unusedNeedsReview++;
            }
        }
        $checks['unused_review_queue_empty'] = $unusedNeedsReview === 0;
    } catch (\Throwable $exception) {
        $errors['inventory'] = $exception->getMessage();
    }
}

$failed = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));
$decision = $failed === [] ? 'GO' : 'NO-GO';
$result = [
    'ok' => $decision === 'GO',
    'scope' => 'historical_gift_entitlement_preflight',
    'production_authorized' => false,
    'go_no_go_decision' => $decision,
    'environment' => (string) ($config['env'] ?? 'local'),
    'checks' => $checks,
];

if (is_array($inventory)) {
    $result['inventory_summary'] = $inventory['summary'];
}
if ($failed !== []) {
    $result['failed'] = $failed;
}
if ($errors !== []) {
    $result['errors'] = $errors;
}

echo json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
), PHP_EOL;
exit($decision === 'GO' ? 0 : 1);

function tableExists(\PDO $db, string $table): bool
{
    $statement = $db->prepare(
        'SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $statement->execute([$table]);

    return $statement->fetchColumn() !== false;
}
