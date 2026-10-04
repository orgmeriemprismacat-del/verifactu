<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Service\DiscountSnapshotFileReader;
use Prisma\Sif\Service\LegacyCourseInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to preview Redsys course invoices with SIF_ENV=production.\n");
    exit(1);
}

$args = array_slice($argv, 1);
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
    fwrite(STDERR, "Usage: php sif/scripts/preview-redsys-course.php DS_ORDER [--discount-file=discount.json]\n");
    exit(1);
}

try {
    $sifDb = ConnectionFactory::make($config);
    $discountSnapshot = (new DiscountSnapshotFileReader())->read($discountFile);
    $notifications = new RedsysNotificationRepository();
    $notification = $notifications->findByDsOrder($sifDb, $dsOrder);

    if ($notification === null) {
        throw SifException::validation('Redsys notification not found');
    }

    if ((string) $notification['STATUS'] !== 'VALIDATED') {
        throw SifException::conflict('Redsys notification is not validated');
    }

    $intent = (new RedsysPaymentIntentRepository())->findByDsOrder($sifDb, $dsOrder);
    if (!is_array($intent) || strtoupper(trim((string) ($intent['SOURCE_TYPE'] ?? ''))) !== 'CURS') {
        throw SifException::validation('CURS Redsys intent not found');
    }
    $snapshot = json_decode((string) ($intent['SNAPSHOT_JSON'] ?? ''), true);
    if (!is_array($snapshot)) {
        throw SifException::validation('Invalid CURS Redsys intent snapshot');
    }
    if ($discountSnapshot !== null) {
        $snapshot['discount'] = $discountSnapshot;
    }

    $basePayload = (new LegacyCourseInvoicePayloadBuilder())->build($snapshot);
    $payload = (new RedsysInvoicePayloadBuilder($notifications))
        ->buildFromValidatedNotification($sifDb, $dsOrder, $basePayload);

    echo json_encode([
        'ok' => true,
        'dry_run' => true,
        'ds_order' => $dsOrder,
        'idpag' => (int) ($intent['IDPAG'] ?? 0),
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
