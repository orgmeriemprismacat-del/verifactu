<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\FiscalSequenceRepository;
use Prisma\Sif\Repository\InvoiceRepository;
use Prisma\Sif\Repository\LegacyUsocSnapshotRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\InvoiceService;
use Prisma\Sif\Service\LegacyUsocInvoicePayloadBuilder;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysUsocInvoiceService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to process Redsys USOC invoices with SIF_ENV=production.\n");
    exit(1);
}

[$dsOrder, $usocAmount] = parseRedsysUsocArgs(array_slice($argv, 1));
if ($dsOrder === '' || $usocAmount === null) {
    usage('process');
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
    $service = new RedsysUsocInvoiceService(
        $notifications,
        new LegacyUsocSnapshotRepository(),
        new LegacyUsocInvoicePayloadBuilder(),
        new RedsysInvoicePayloadBuilder($notifications),
        $invoiceService
    );

    $result = $service->issueStudentFromValidatedNotification($sifDb, $legacyDb, $dsOrder, $usocAmount);
    $result['entity_invoice_pending'] = $result['entity_invoice_pending'] ?? [
        'source_type' => 'USOC_ENTITAT',
        'requires_explicit_billing' => true,
        'entity_amount' => $usocAmount,
    ];
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

function parseRedsysUsocArgs(array $args): array
{
    $dsOrder = '';
    $usocAmount = null;

    foreach ($args as $arg) {
        $arg = (string) $arg;
        if (str_starts_with($arg, '--usoc-amount=')) {
            $usocAmount = amount(substr($arg, strlen('--usoc-amount=')));
            continue;
        }

        if (str_starts_with($arg, '--entity-amount=')) {
            $usocAmount = amount(substr($arg, strlen('--entity-amount=')));
            continue;
        }

        if (!str_starts_with($arg, '--') && $dsOrder === '') {
            $dsOrder = trim($arg);
        }
    }

    return [$dsOrder, $usocAmount];
}

function amount(mixed $value): string
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        throw SifException::validation('Invalid USOC entity amount');
    }

    $amount = (float) $value;
    if ($amount <= 0.0) {
        throw SifException::validation('Invalid USOC entity amount');
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
