<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Repository\FiscalSequenceRepository;
use Prisma\Sif\Repository\InvoiceRepository;
use Prisma\Sif\Repository\LegacyPackSnapshotRepository;
use Prisma\Sif\Repository\LegacySyncRepository;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\InvoiceService;
use Prisma\Sif\Service\LegacyPackInvoicePayloadBuilder;
use Prisma\Sif\Service\LegacySyncService;
use Prisma\Sif\Service\PackEnrollmentFundAllocationService;
use Prisma\Sif\Service\PackPaymentNotificationService;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysPackInvoiceService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to process Redsys pack invoices with SIF_ENV=production.\n");
    exit(1);
}

$args = array_slice($argv, 1);
$syncLegacy = in_array('--sync-legacy', $args, true);
$dsOrder = '';
foreach ($args as $arg) {
    if (!str_starts_with((string) $arg, '--')) {
        $dsOrder = trim((string) $arg);
        break;
    }
}

if ($dsOrder === '') {
    fwrite(STDERR, "Usage: php sif/scripts/process-redsys-pack.php DS_ORDER [--sync-legacy]\n");
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
    $intent = (new RedsysPaymentIntentRepository())->findByDsOrder($sifDb, $dsOrder);
    if (!is_array($intent) || strtoupper(trim((string) ($intent['SOURCE_TYPE'] ?? ''))) !== 'PACK') {
        throw new RuntimeException('PACK Redsys intent not found');
    }
    $snapshot = json_decode((string) ($intent['SNAPSHOT_JSON'] ?? ''), true);
    if (!is_array($snapshot)) {
        throw new RuntimeException('Invalid PACK Redsys intent snapshot');
    }

    $service = new RedsysPackInvoiceService(
        $notifications,
        new LegacyPackSnapshotRepository(),
        new LegacyPackInvoicePayloadBuilder(),
        new RedsysInvoicePayloadBuilder($notifications),
        $invoiceService,
        new PackPaymentNotificationService(
            new NotificationOutboxRepository(new UuidGenerator())
        ),
        new PackEnrollmentFundAllocationService(
            new EnrollmentFundMovementRepository(new UuidGenerator())
        )
    );

    $result = $service->issueFromIntentSnapshot($sifDb, $dsOrder, $snapshot);
    $legacySync = $result['legacy_sync'] ?? ['relations' => [], 'estat_cobrament' => 'PAID'];

    if ($syncLegacy && ($result['ok'] ?? false) === true) {
        $legacySyncService = new LegacySyncService(new LegacySyncRepository());
        $legacySyncService->syncAfterSifSuccess(
            $legacyDb,
            $legacySync['relations'] ?? [],
            (string) $result['uuid_factura'],
            (string) $result['num_visible'],
            (string) ($legacySync['estat_cobrament'] ?? 'PAID')
        );
        if (($legacySync['mode'] ?? '') === 'PACK_FULL_PAYMENT') {
            $movementDate = trim((string) ($legacySync['movement_date'] ?? ''));
            if ($movementDate === '') {
                throw new RuntimeException('Missing pack payment movement date for legacy sync');
            }
            $legacySyncService->syncPackFullPayment(
                $legacyDb,
                $legacySync['relations'] ?? [],
                $movementDate
            );
        }
        $result['legacy_sync_executed'] = true;
    } else {
        $result['legacy_sync_executed'] = false;
    }

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
