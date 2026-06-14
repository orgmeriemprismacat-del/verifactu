<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyCourseSnapshotRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
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
    $legacyDb = ConnectionFactory::makeLegacy($config);
    $discountSnapshot = (new DiscountSnapshotFileReader())->read($discountFile);
    $notifications = new RedsysNotificationRepository();
    $notification = $notifications->findByDsOrder($sifDb, $dsOrder);

    if ($notification === null) {
        throw SifException::validation('Redsys notification not found');
    }

    if ((string) $notification['STATUS'] !== 'VALIDATED') {
        throw SifException::conflict('Redsys notification is not validated');
    }

    $idpag = idpag($notification);
    $amount = amount($notification);
    $snapshot = (new LegacyCourseSnapshotRepository())->loadByIdpag($legacyDb, $idpag, $amount);
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
        'idpag' => $idpag,
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

function idpag(array $notification): int
{
    if (!array_key_exists('IDPAG', $notification) || $notification['IDPAG'] === null || $notification['IDPAG'] === '') {
        throw SifException::validation('Validated Redsys course notification requires IDPAG');
    }

    $idpag = (int) $notification['IDPAG'];
    if ($idpag <= 0) {
        throw SifException::validation('Invalid Redsys course IDPAG');
    }

    return $idpag;
}

function amount(array $notification): string
{
    if (!array_key_exists('IMPORT', $notification) || !is_numeric($notification['IMPORT'])) {
        throw SifException::validation('Invalid Redsys course amount');
    }

    return number_format((float) $notification['IMPORT'], 2, '.', '');
}
