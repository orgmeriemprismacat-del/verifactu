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
use Prisma\Sif\Repository\LegacyGiftSnapshotRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\InvoiceService;
use Prisma\Sif\Service\ManualGiftInvoicePayloadBuilder;
use Prisma\Sif\Service\ManualGiftInvoiceService;
use Prisma\Sif\Service\PaymentPayloadValidator;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to process manual gift invoices with SIF_ENV=production.\n");
    exit(1);
}

[$selector, $input] = parseManualGiftArgs(array_slice($argv, 1));
if ($selector === null) {
    usage('process');
}

try {
    $sifDb = ConnectionFactory::make($config);
    $legacyDb = ConnectionFactory::makeLegacy($config);
    $invoiceService = new InvoiceService(
        new TransactionRunner($sifDb),
        new InvoicePayloadValidator(),
        new FiscalSequenceRepository(),
        new InvoiceRepository(new UuidGenerator(), new HashCalculator()),
        new PaymentPayloadValidator(),
        new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
    );
    $service = new ManualGiftInvoiceService(
        new LegacyGiftSnapshotRepository(),
        new ManualGiftInvoicePayloadBuilder(),
        $invoiceService
    );

    if (($selector['type'] ?? '') === 'id') {
        $result = $service->issueByGiftIdFromManualPayment(
            $legacyDb,
            (int) ($selector['value'] ?? 0),
            $input
        );
    } else {
        $result = $service->issueByGiftCodeFromManualPayment(
            $legacyDb,
            (string) ($selector['value'] ?? ''),
            $input
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

function parseManualGiftArgs(array $args): array
{
    $selector = null;
    $positionals = [];

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

        if (!str_starts_with($arg, '--')) {
            $positionals[] = $arg;
        }
    }

    $input = [
        'amount' => amount($positionals[0] ?? null),
        'movement_date' => movementDate($positionals[1] ?? null),
    ];

    foreach ([
        'reference' => ['--reference=', '--referencia=', '--referencia-bancaria='],
        'bank' => ['--bank=', '--banc='],
        'notes' => ['--notes=', '--obs=', '--observations='],
        'created_by' => ['--created-by=', '--user=', '--usuari='],
    ] as $key => $prefixes) {
        $value = optionValue($args, $prefixes);
        if ($value !== null) {
            $input[$key] = $value;
        }
    }

    return [$selector, $input];
}

function amount(mixed $value): string
{
    if ($value === null || $value === '') {
        usage('process');
    }

    if (!is_numeric($value)) {
        throw SifException::validation('Invalid payment amount');
    }

    $amount = (float) $value;
    if ($amount <= 0.0) {
        throw SifException::validation('Invalid payment amount');
    }

    return number_format($amount, 2, '.', '');
}

function movementDate(mixed $value): string
{
    if ($value === null || trim((string) $value) === '') {
        usage('process');
    }

    return trim((string) $value);
}

function optionValue(array $args, array $prefixes): ?string
{
    foreach ($args as $arg) {
        foreach ($prefixes as $prefix) {
            if (str_starts_with((string) $arg, $prefix)) {
                $value = trim(substr((string) $arg, strlen($prefix)));

                return $value === '' ? null : $value;
            }
        }
    }

    return null;
}

function usage(string $script): void
{
    fwrite(
        STDERR,
        "Usage: php sif/scripts/{$script}-manual-gift.php (--gift-id=ID|--gift-code=CODI) AMOUNT MOVEMENT_DATE [--reference=REF] [--bank=BANK] [--notes=TEXT] [--created-by=USER]\n"
    );
    exit(1);
}
