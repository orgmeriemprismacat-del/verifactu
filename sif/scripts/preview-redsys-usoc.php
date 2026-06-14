<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyUsocSnapshotRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\LegacyUsocInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to preview Redsys USOC invoices with SIF_ENV=production.\n");
    exit(1);
}

[$dsOrder, $usocAmount] = parseRedsysUsocArgs(array_slice($argv, 1));
if ($dsOrder === '' || $usocAmount === null) {
    usage('preview');
}

try {
    $sifDb = ConnectionFactory::make($config);
    $legacyDb = ConnectionFactory::makeLegacy($config);
    $notifications = new RedsysNotificationRepository();
    $notification = validatedNotification($notifications, $sifDb, $dsOrder);
    $idpag = idpag($notification);
    $studentAmount = amount($notification, 'Invalid Redsys USOC student amount');
    $snapshot = (new LegacyUsocSnapshotRepository())->loadByIdpag($legacyDb, $idpag, $studentAmount, $usocAmount);
    $basePayload = (new LegacyUsocInvoicePayloadBuilder())->buildStudentPayload($snapshot);
    $payload = (new RedsysInvoicePayloadBuilder($notifications))
        ->buildFromValidatedNotification($sifDb, $dsOrder, $basePayload);

    echo json_encode([
        'ok' => true,
        'dry_run' => true,
        'ds_order' => $dsOrder,
        'usoc_amount' => $usocAmount,
        'entity_invoice_pending' => [
            'source_type' => 'USOC_ENTITAT',
            'requires_explicit_billing' => true,
            'entity_amount' => $snapshot['usoc']['entity_amount'] ?? $usocAmount,
            'student_invoice_uuid' => null,
            'idpag' => $idpag,
        ],
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

function parseRedsysUsocArgs(array $args): array
{
    $dsOrder = '';
    $usocAmount = null;

    foreach ($args as $arg) {
        $arg = (string) $arg;
        if (str_starts_with($arg, '--usoc-amount=')) {
            $usocAmount = amount(substr($arg, strlen('--usoc-amount=')), 'Invalid USOC entity amount');
            continue;
        }

        if (str_starts_with($arg, '--entity-amount=')) {
            $usocAmount = amount(substr($arg, strlen('--entity-amount=')), 'Invalid USOC entity amount');
            continue;
        }

        if (!str_starts_with($arg, '--') && $dsOrder === '') {
            $dsOrder = trim($arg);
        }
    }

    return [$dsOrder, $usocAmount];
}

function validatedNotification(RedsysNotificationRepository $notifications, \PDO $sifDb, string $dsOrder): array
{
    $notification = $notifications->findByDsOrder($sifDb, $dsOrder);
    if ($notification === null) {
        throw SifException::validation('Redsys notification not found');
    }

    if ((string) $notification['STATUS'] !== 'VALIDATED') {
        throw SifException::conflict('Redsys notification is not validated');
    }

    return $notification;
}

function idpag(array $notification): int
{
    if (!array_key_exists('IDPAG', $notification) || $notification['IDPAG'] === null || $notification['IDPAG'] === '') {
        throw SifException::validation('Validated Redsys USOC notification requires IDPAG');
    }

    $idpag = (int) $notification['IDPAG'];
    if ($idpag <= 0) {
        throw SifException::validation('Invalid Redsys USOC IDPAG');
    }

    return $idpag;
}

function amount(mixed $value, string $message): string
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        throw SifException::validation($message);
    }

    $amount = (float) $value;
    if ($amount <= 0.0) {
        throw SifException::validation($message);
    }

    return number_format($amount, 2, '.', '');
}

function usage(string $script): void
{
    fwrite(
        STDERR,
        "Usage: php sif/scripts/{$script}-redsys-usoc.php DS_ORDER --usoc-amount=AMOUNT\n"
    );
    exit(1);
}
