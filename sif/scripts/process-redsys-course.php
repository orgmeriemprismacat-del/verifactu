<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\FiscalSequenceRepository;
use Prisma\Sif\Repository\InvoiceRepository;
use Prisma\Sif\Repository\LegacyCourseSnapshotRepository;
use Prisma\Sif\Repository\LegacySyncRepository;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\CourseLegacyPaymentSyncService;
use Prisma\Sif\Service\CoursePaymentNotificationService;
use Prisma\Sif\Service\DiscountSnapshotFileReader;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\InvoiceService;
use Prisma\Sif\Service\LegacyCourseInvoicePayloadBuilder;
use Prisma\Sif\Service\LegacySyncService;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Service\RedsysCourseInvoiceService;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to process Redsys course invoices with SIF_ENV=production.\n");
    exit(1);
}

$args = array_slice($argv, 1);
$syncLegacy = in_array('--sync-legacy', $args, true);
$dsOrder = '';
$discountFile = null;
foreach ($args as $arg) {
    $arg = (string) $arg;
    if (str_starts_with($arg, '--discount-file=')) {
        $discountFile = trim(substr($arg, strlen('--discount-file=')));
        continue;
    }

    if (!str_starts_with($arg, '--')) {
        $dsOrder = trim($arg);
        break;
    }
}

if ($dsOrder === '') {
    fwrite(STDERR, "Usage: php sif/scripts/process-redsys-course.php DS_ORDER [--sync-legacy] [--discount-file=discount.json]\n");
    exit(1);
}

try {
    $sifDb = ConnectionFactory::make($config);
    $legacyDb = ConnectionFactory::makeLegacy($config);
    $discountSnapshot = (new DiscountSnapshotFileReader())->read($discountFile);
    $notifications = new RedsysNotificationRepository();
    $legacySnapshots = new LegacyCourseSnapshotRepository();
    $invoiceService = new InvoiceService(
        new TransactionRunner($sifDb),
        new InvoicePayloadValidator(),
        new FiscalSequenceRepository(),
        new InvoiceRepository(new UuidGenerator(), new HashCalculator()),
        new PaymentPayloadValidator(),
        new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
    );
    $service = new RedsysCourseInvoiceService(
        $notifications,
        $legacySnapshots,
        new LegacyCourseInvoicePayloadBuilder(),
        new RedsysInvoicePayloadBuilder($notifications),
        $invoiceService
    );

    $result = $service->issueFromValidatedNotification($sifDb, $legacyDb, $dsOrder, $discountSnapshot);
    $legacySync = $result['legacy_sync'] ?? ['relations' => [], 'estat_cobrament' => 'PAID'];

    if ($syncLegacy && ($result['ok'] ?? false) === true) {
        $relations = $legacySync['relations'] ?? [];
        (new LegacySyncService(new LegacySyncRepository()))->syncAfterSifSuccess(
            $legacyDb,
            $relations,
            (string) $result['uuid_factura'],
            (string) $result['num_visible'],
            (string) ($legacySync['estat_cobrament'] ?? 'PAID')
        );

        $notification = $notifications->findByDsOrder($sifDb, $dsOrder);
        $idpag = is_array($notification) ? (int) ($notification['IDPAG'] ?? 0) : 0;
        $idInsc = 0;
        foreach ($relations as $relation) {
            if (($relation['source_type'] ?? '') === 'INSCRIPCIO' && isset($relation['source_id'])) {
                $idInsc = (int) $relation['source_id'];
                break;
            }
        }

        if ($idpag <= 0 || $idInsc <= 0) {
            throw SifException::conflict('Could not resolve course identity for economic legacy sync');
        }

        $result['legacy_payment_sync'] = (new CourseLegacyPaymentSyncService())->sync(
            $sifDb,
            $legacyDb,
            $idpag,
            $idInsc,
            (string) $result['uuid_factura'],
            (string) $result['num_visible']
        );

        $amount = is_array($notification) && is_numeric($notification['IMPORT'] ?? null)
            ? number_format((float) $notification['IMPORT'], 2, '.', '')
            : '';
        if ($amount === '') {
            throw SifException::conflict('Could not resolve course amount for notification outbox');
        }
        $notificationSnapshot = $legacySnapshots->loadByIdpag($legacyDb, $idpag, $amount);
        $result['notification_outbox'] = (new CoursePaymentNotificationService(
            new NotificationOutboxRepository(new UuidGenerator())
        ))->enqueue(
            $sifDb,
            $dsOrder,
            $notificationSnapshot,
            $result,
            $result['legacy_payment_sync']
        );
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
